<?php
namespace App\Models;

use Exception;

class DHCPv6LeaseModel extends KEAModel
{
    private array $cache = [];

    public function __construct()
    {
        parent::__construct();
    }

    public function getLeases($from, $limit, $switchId, $bviId)
    {
        try {
            error_log("DHCPv6LeaseModel::getLeases called with parameters:");
            error_log("from: " . print_r($from, true));
            error_log("limit: " . print_r($limit, true));
            error_log("switchId: " . print_r($switchId, true));
            error_log("bviId: " . print_r($bviId, true));

            // Input validation
            if (!is_numeric($limit)) {
                error_log("Limit validation failed - not numeric: " . print_r($limit, true));
                throw new Exception('Limit must be a numeric value');
            }

            $limit = (int)$limit;
            if ($limit <= 0) {
                error_log("Limit validation failed - not positive: {$limit}");
                throw new Exception('Limit must be a positive integer');
            }

            if (!isset($switchId) || $switchId === '' || !isset($bviId) || $bviId === '') {
                error_log("Missing required parameters - switchId: {$switchId}, bviId: {$bviId}");
                throw new Exception('Switch ID and BVI ID are required');
            }

            // Check cache
            $cacheKey = "{$switchId}_{$bviId}_{$from}_{$limit}";
            error_log("Checking cache with key: {$cacheKey}");
            $cachedResult = $this->getCachedLeases($cacheKey);
            if ($cachedResult !== null) {
                error_log("Cache hit - returning cached result");
                return $cachedResult;
            }
            error_log("Cache miss - proceeding with API call");

            // Prepare API call parameters
            $commandParams = [
                "remote" => ["type" => "mysql"],
                "server-tags" => ["all"],
                'from' => $from,
                'limit' => $limit
            ];
            error_log("Preparing KEA API call with parameters: " . json_encode($commandParams));

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

            if ($result[0]['result'] !== 0) {
                $errorMessage = $result[0]['text'] ?? 'Invalid response from KEA API';
                error_log("API error response: {$errorMessage}");
                throw new Exception($errorMessage);
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

    public function addStaticLease($ipAddress, $duid, $subnetId, $options)
    {
        if (!filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            throw new Exception('Invalid IPv6 address');
        }
    
        $commandParams = [
            'ip-address' => $ipAddress,
            'duid' => $duid,
            'subnet-id' => $subnetId,
            'options' => $options
        ];
    
        $response = $this->sendKeaCommand('lease6-add', $commandParams);
    
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
            'remote' => ['type' => 'mysql'],
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

    

}