<?php
namespace App\Models;

use Exception;

class DHCPv6LeaseModel extends KEAModel
{
    private const CACHE_TTL = 60; // Cache for 60 seconds
    private array $cache = [];

    public function __construct()
    {
        parent::__construct();
    }

    public function getLeasesBySubnet($from, $limit, $subnetId)
    {
        try {
            error_log("DHCPv6LeaseModel::getLeasesBySubnet called with subnetId: {$subnetId}, from: {$from}, limit: {$limit}");

            // Input validation
            if (!is_numeric($limit) || $limit <= 0) {
                throw new Exception('Limit must be a positive integer');
            }
            if (!is_numeric($subnetId) || $subnetId <= 0) {
                throw new Exception('Subnet ID must be a positive integer');
            }

            $limit = (int)$limit;
            $subnetId = (int)$subnetId;

            // Check cache
            $cacheKey = "subnet_{$subnetId}_{$from}_{$limit}";
            error_log("Checking cache with key: {$cacheKey}");
            $cachedResult = $this->getCachedLeases($cacheKey);
            if ($cachedResult !== null) {
                error_log("Cache hit - returning cached result");
                return $cachedResult;
            }
            error_log("Cache miss - proceeding with API call");

            // Call Kea API directly - NO database queries
            $commandParams = [
                "operation-target" => "all",
                "server-tags" => ["all"],
                "subnet-id" => $subnetId,
                'from' => $from,
                'limit' => $limit
            ];
            error_log("Calling Kea API with parameters: " . json_encode($commandParams));

            // Make API call
            $response = $this->sendKeaCommand('lease6-get-page', $commandParams);
            error_log("Raw KEA API response: " . $response);

            $result = json_decode($response, true);
            error_log("Decoded API response: " . json_encode($result));
            
            // Validate response structure and check for errors
            if (!isset($result[0]['result'])) {
                error_log("Malformed API response - missing result field");
                throw new Exception('Malformed response from KEA API');
            }

            // Kea result codes:
            // 0 = success
            // 3 = empty (no results found) - this is NOT an error
            // Other codes = actual errors
            $resultCode = $result[0]['result'];
            if ($resultCode !== 0 && $resultCode !== 3) {
                $errorMessage = $result[0]['text'] ?? 'Invalid response from KEA API';
                error_log("API error response: {$errorMessage}");
                throw new Exception($errorMessage);
            }

            // Handle empty result (result code 3)
            if ($resultCode === 3) {
                error_log("No leases found (result code 3) - returning empty array");
                $emptyResult = [
                    'leases' => [],
                    'pagination' => [
                        'from' => $from,
                        'limit' => $limit,
                        'total' => 0,
                        'hasMore' => false
                    ]
                ];
                $this->setCachedLeases($cacheKey, $emptyResult);
                return $emptyResult;
            }

            // Extract and validate data
            $leases = $result[0]['arguments']['leases'] ?? [];
            $count = (int)($result[0]['arguments']['count'] ?? 0);
            error_log("Retrieved {$count} total leases, processing " . count($leases) . " leases");
            
            // Format leases
            $formattedLeases = array_map([$this, 'formatLease'], $leases);
            error_log("Formatted " . count($formattedLeases) . " leases");
            
            // Get the last IP address for pagination
            $lastIp = null;
            if (!empty($formattedLeases)) {
                $lastLease = end($formattedLeases);
                $lastIp = $lastLease['ip-address'] ?? null;
                
                if ($lastIp && !$this->isValidIPv6($lastIp)) {
                    error_log("Warning: Invalid IPv6 address encountered: {$lastIp}");
                    $lastIp = null;
                }
            }

            // Prepare response
            $response = [
                'leases' => $formattedLeases,
                'pagination' => [
                    'total' => $count,
                    'limit' => $limit,
                    'nextFrom' => $lastIp,
                    'hasMore' => count($formattedLeases) === $limit
                ],
                'metadata' => [
                    'switchId' => $switchId,
                    'bviId' => $bviId,
                    'timestamp' => time()
                ]
            ];

            error_log("Prepared final response: " . json_encode($response));

            // Cache the result
            $this->setCachedLeases($cacheKey, $response);
            error_log("Response cached with key: {$cacheKey}");

            return $response;

        } catch (Exception $e) {
            error_log("Error in DHCPv6LeaseModel::getLeases: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

 

    private function formatLease(array $lease): array
    {
        return [
            'ip-address' => $lease['ip-address'] ?? '',
            'duid' => $lease['duid'] ?? '',
            'hwaddr' => $lease['hwaddr'] ?? '',
            'state' => $lease['state'] ?? '',
            'cltt' => $lease['cltt'] ?? null,
            'valid-lft' => $lease['valid-lft'] ?? 0,
            'iaid' => $lease['iaid'] ?? '',
            'preferred-lft' => $lease['preferred-lft'] ?? 0,
            'subnet-id' => $lease['subnet-id'] ?? null
        ];
    }

    private function isValidIPv6($ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) 
            && !str_starts_with(strtolower($ip), 'fe80:'); // Exclude link-local addresses
    }

    private function getCachedLeases($key)
    {
        if (isset($this->cache[$key]) && (time() - $this->cache[$key]['time']) < self::CACHE_TTL) {
            return $this->cache[$key]['data'];
        }
        return null;
    }

    private function setCachedLeases($key, $data)
    {
        $this->cache[$key] = [
            'data' => $data,
            'time' => time()
        ];
    }

     

    public function deleteLease($ipAddress)
    {
        if (!filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            throw new Exception('Invalid IPv6 address');
        }
    
        $response = $this->sendKeaCommand('lease6-del', [
            'ip-address' => $ipAddress
        ]);
    
        $result = json_decode($response, true);
        error_log("Delete lease response: " . json_encode($result));
    
        if (!isset($result[0]['result'])) {
            throw new Exception('Invalid response from KEA API');
        }
    
        switch ($result[0]['result']) {
            case 0:
                return [
                    'result' => $result[0]['result'],
                    'message' => $result[0]['text'] ?? 'Lease deleted successfully'
                ];
            case 1:
                throw new Exception($result[0]['text'] ?? 'Error deleting lease');
            case 2:
                throw new Exception($result[0]['text'] ?? 'Unsupported operation');
            case 3:
                return [
                    'result' => $result[0]['result'],
                    'message' => $result[0]['text'] ?? 'Command completed successfully, but no data was affected'
                ];
            default:
                throw new Exception('Unknown response code from KEA API');
        }
    }

    public function addStaticLease($ipAddress, $hwAddress, $subnetId, $options)
    {
        if (!filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            throw new Exception('Invalid IPv6 address');
        }
    
        // Use reservation-add to create new reservations
        $commandParams = [
            'reservation' => [
                'subnet-id' => $subnetId,
                'hw-address' => $hwAddress,
                'ip-addresses' => [$ipAddress]
            ]
        ];
        
        // Add options if provided
        if (!empty($options)) {
            $commandParams['reservation']['option-data'] = $options;
        }
    
        $response = $this->sendKeaCommand('reservation-add', $commandParams);
    
        $result = json_decode($response, true);
        error_log("Add static lease response: " . json_encode($result));
    
        if (!isset($result[0]['result'])) {
            throw new Exception('Invalid response from KEA API');
        }
    
        switch ($result[0]['result']) {
            case 0:
                return [
                    'result' => $result[0]['result'],
                    'message' => $result[0]['text'] ?? 'Static lease added successfully'
                ];
            case 1:
                throw new Exception($result[0]['text'] ?? 'Error adding static lease');
            case 2:
                throw new Exception($result[0]['text'] ?? 'Unsupported operation');
            case 3:
                return [
                    'result' => $result[0]['result'],
                    'message' => $result[0]['text'] ?? 'Command completed successfully, but no data was affected'
                ];
            default:
                throw new Exception('Unknown response code from KEA API');
        }
    }

    public function getStaticLeases($subnetId)
    {
        $commandParams = [
            'operation-target' => 'all',
            'subnet-id' => intval($subnetId),
            'server-tags' => ['all']
        ];

        $response = $this->sendKeaCommand('reservation-get-all', $commandParams);

        $result = json_decode($response, true);
        error_log("Get static leases response: " . json_encode($result));

        if (!isset($result[0]['result'])) {
            throw new Exception('Invalid response from KEA API');
        }

        switch ($result[0]['result']) {
            case 0:
                return [
                    'result' => $result[0]['result'],
                    'message' => $result[0]['text'] ?? 'Static leases retrieved successfully',
                    'hosts' => $result[0]['arguments']['hosts'] ?? []
                ];
            case 1:
                throw new Exception($result[0]['text'] ?? 'Error retrieving static leases');
            case 2:
                throw new Exception($result[0]['text'] ?? 'Unsupported operation');
            case 3:
                return [
                    'result' => $result[0]['result'],
                    'message' => $result[0]['text'] ?? 'Command completed successfully, but no data was affected',
                    'hosts' => []
                ];
            default:
                throw new Exception('Unknown response code from KEA API');
        }
    }

    public function updateReservation($ipAddress, $hwAddress, $subnetId, $options, $hostname = null)
    {
        if (!filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            throw new Exception('Invalid IPv6 address');
        }
    
        // Use reservation-update command (creates or updates)
        $commandParams = [
            'reservation' => [
                'subnet-id' => $subnetId,
                'hw-address' => $hwAddress,
                'ip-addresses' => [$ipAddress]
            ]
        ];
        
        // Add hostname if provided
        if (!empty($hostname)) {
            $commandParams['reservation']['hostname'] = $hostname;
        }
        
        // Add options if provided
        if (!empty($options)) {
            $commandParams['reservation']['option-data'] = $options;
        }
    
        $response = $this->sendKeaCommand('reservation-update', $commandParams);
    
        $result = json_decode($response, true);
        error_log("Update reservation response: " . json_encode($result));
    
        if (!isset($result[0]['result'])) {
            throw new Exception('Invalid response from KEA API');
        }
    
        switch ($result[0]['result']) {
            case 0:
                return [
                    'result' => $result[0]['result'],
                    'message' => $result[0]['text'] ?? 'Reservation updated successfully'
                ];
            case 1:
                throw new Exception($result[0]['text'] ?? 'Error updating reservation');
            case 2:
                throw new Exception($result[0]['text'] ?? 'Unsupported operation');
            case 3:
                return [
                    'result' => $result[0]['result'],
                    'message' => $result[0]['text'] ?? 'Command completed successfully, but no data was affected'
                ];
            default:
                throw new Exception('Unknown response code from KEA API');
        }
    }

    public function deleteReservation($ipAddress, $subnetId)
    {
        $commandParams = [
            'subnet-id' => $subnetId,
            'ip-address' => $ipAddress,
            'operation-target' => 'default'
        ];

        error_log("KEA API COMMAND: " . json_encode([
            'command' => 'reservation-del',
            'arguments' => $commandParams
        ], JSON_PRETTY_PRINT));

        $response = $this->sendKeaCommand('reservation-del', $commandParams);
        $result = json_decode($response, true);
        error_log("Delete reservation response: " . json_encode($result));

        if (!isset($result[0]['result'])) {
            throw new Exception('Invalid response from KEA API');
        }

        // If result is 3, check with reservation-get if the reservation still exists
        if ($result[0]['result'] === 3) {
            $getParams = [
                'subnet-id' => $subnetId,
                'ip-address' => $ipAddress
            ];
            $getResponse = $this->sendKeaCommand('reservation-get', $getParams);
            $getResult = json_decode($getResponse, true);
            error_log("Reservation-get after delete result 3: " . json_encode($getResult));
            // If reservation-get returns a host, deletion failed
            if (isset($getResult[0]['arguments']['reservation'])) {
                return [
                    'success' => false,
                    'kea_response' => $result[0],
                    'message' => 'Delete returned result 3, but reservation still exists!'
                ];
            } else {
                return [
                    'success' => true,
                    'kea_response' => $result[0],
                    'message' => $result[0]['text'] ?? 'Reservation did not exist (already deleted)'
                ];
            }
        }

        // Treat result 0 (success) as success
        if ($result[0]['result'] === 0) {
            return [
                'success' => true,
                'kea_response' => $result[0],
                'message' => $result[0]['text'] ?? 'Reservation deleted successfully'
            ];
        } else {
            return [
                'success' => false,
                'kea_response' => $result[0],
                'message' => $result[0]['text'] ?? 'Error deleting reservation'
            ];
        }
    }

}
