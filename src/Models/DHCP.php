<?php

namespace App\Models;

use PDO;
use Exception;


class DHCP
{
    private $db;
    private ?string $keaApiUrl = null;
    private string $keaService;


    public function __construct($db)
    {
        $this->db = $db;
        // Lazy load Kea API URL only when needed
        $this->keaService = 'dhcp6';
    }

    /**
     * Get the URL of the active Kea server from database (lazy loaded)
     */
    private function getActiveKeaServerUrl(): string
    {
        if ($this->keaApiUrl !== null) {
            return $this->keaApiUrl;
        }
        
        try {
            $stmt = $this->db->prepare(
                "SELECT api_url FROM kea_servers 
                 WHERE is_active = TRUE 
                 ORDER BY priority ASC 
                 LIMIT 1"
            );
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && !empty($result['api_url'])) {
                $this->keaApiUrl = $result['api_url'];
                return $this->keaApiUrl;
            }
            
            // Fallback to environment variable or default
            $this->keaApiUrl = $_ENV['KEA_API_ENDPOINT'] ?? 'http://localhost:8000';
            return $this->keaApiUrl;
        } catch (Exception $e) {
            // If database query fails, fallback to environment or default
            $this->keaApiUrl = $_ENV['KEA_API_ENDPOINT'] ?? 'http://localhost:8000';
            return $this->keaApiUrl;
        }
    }

    private function sendKeaCommand($command, $arguments = [])
    {
        // Get all active Kea servers
        $stmt = $this->db->prepare("SELECT id, name, api_url FROM kea_servers WHERE is_active = 1 ORDER BY priority");
        $stmt->execute();
        $keaServers = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        if (empty($keaServers)) {
            throw new Exception("No active Kea servers configured");
        }
        
        $data = [
            "command" => $command,
            "service" => [$this->keaService],
            "arguments" => $arguments
        ];
        
        error_log("DHCP Model: Sending Kea command - Full JSON: " . json_encode($data, JSON_PRETTY_PRINT));
        
        // Send to all servers (HA MySQL backend requires both servers to be updated)
        $serversToContact = $keaServers;
        
        $responses = [];
        $errors = [];
        
        // Send command to server(s)
        foreach ($serversToContact as $server) {
            $ch = curl_init($server['api_url']);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            if (curl_errno($ch)) {
                $error = "Kea API Error on {$server['name']}: " . curl_error($ch);
                error_log($error);
                $errors[] = $error;
                curl_close($ch);
                continue;
            }
            
            curl_close($ch);
            
            $decoded = json_decode($response, true);
            $responses[] = $decoded;
            
            // Check if this server's response was successful
            if (!isset($decoded[0]['result']) || $decoded[0]['result'] !== 0) {
                $error = "Kea command '{$command}' failed on {$server['name']}: " . ($decoded[0]['text'] ?? 'Unknown error');
                error_log($error);
                $errors[] = $error;
            } else {
                error_log("Kea command '{$command}' succeeded on {$server['name']}");
            }
        }
        
        // If ALL servers failed, throw an exception
        if (count($errors) === count($serversToContact)) {
            throw new Exception("Kea command failed on all servers: " . implode("; ", $errors));
        }
        
        // Return the first successful response (for backward compatibility)
        foreach ($responses as $response) {
            if (isset($response[0]['result']) && $response[0]['result'] === 0) {
                return $response;
            }
        }
        
        // If no successful response, return the first response
        return $responses[0] ?? [];
    }

    private function reloadKeaConfig()
    {
        error_log("DHCP Model: Triggering config-reload to refresh Kea cache");
        try {
            $response = $this->sendKeaCommand('config-reload');
            error_log("DHCP Model: Config-reload response: " . json_encode($response));
            return true;
        } catch (Exception $e) {
            error_log("DHCP Model: Warning - config-reload failed: " . $e->getMessage());
            // Don't throw - reload failure shouldn't fail the main operation
            return false;
        }
    }


    private function getNextAvailableSubnetId()
    {
        error_log("DHCP Model: Starting getNextAvailableSubnetId");
        try {
            // Get current configuration
            error_log("DHCP Model: Sending config-get command to Kea");
            $response = $this->sendKeaCommand('config-get');
            error_log("DHCP Model: Received response from Kea: " . json_encode($response));
            
            if (!isset($response[0]['result']) || $response[0]['result'] !== 0) {
                error_log("DHCP Model: Invalid response from Kea - result not 0 or missing");
                throw new Exception("Failed to get configuration from Kea");
            }

            $config = $response[0]['arguments'];
            error_log("DHCP Model: Extracted config arguments: " . json_encode($config));
            
            $subnets = $config['Dhcp6']['subnet6'] ?? [];
            error_log("DHCP Model: Found " . count($subnets) . " subnets in configuration");

            // Get all existing subnet IDs
            $existingIds = [];
            foreach ($subnets as $subnet) {
                if (isset($subnet['id'])) {
                    $existingIds[] = (int)$subnet['id'];
                    error_log("DHCP Model: Found subnet ID: " . $subnet['id']);
                }
            }

            // If no subnets exist, start with ID 1
            if (empty($existingIds)) {
                error_log("DHCP Model: No existing subnets found, returning ID 1");
                return 1;
            }

            // Find the next available ID
            sort($existingIds);
            error_log("DHCP Model: Sorted existing IDs: " . json_encode($existingIds));
            $maxId = end($existingIds);
            error_log("DHCP Model: Maximum existing ID: " . $maxId);
            
            // Look for gaps in the sequence
            $previousId = 0;
            error_log("DHCP Model: Searching for gaps in ID sequence");
            foreach ($existingIds as $id) {
                error_log("DHCP Model: Checking ID: " . $id . " (previous: " . $previousId . ")");
                if ($id > $previousId + 1) {
                    $nextId = $previousId + 1;
                    error_log("DHCP Model: Found gap, returning ID: " . $nextId);
                    return $nextId;
                }
                $previousId = $id;
            }

            // If no gaps found, return max + 1
            $nextId = $maxId + 1;
            error_log("DHCP Model: No gaps found, returning max+1: " . $nextId);
            return $nextId;

        } catch (Exception $e) {
            error_log("DHCP Model: Error in getNextAvailableSubnetId: " . $e->getMessage());
            error_log("DHCP Model: Stack trace: " . $e->getTraceAsString());
            throw $e;
        }
    }

    public function hasDHCPSubnetsForBVI($bviInterfaceId)
    {
        $subnets = $this->getEnrichedSubnets();
        foreach ($subnets as $subnet) {
                error_log( "BVI ".$subnet['bvi_interface_id'] );
                error_log("DHCP Model: Checking subnet: " . json_encode($subnet));
            if ($subnet['bvi_interface_id'] == $bviInterfaceId) {  
                return true;
            }
        }
        
        return false;
    }

    public function hasDHCPSubnetsForSwitch($switchId)
    {
        $subnets = $this->getEnrichedSubnets();
        
        foreach ($subnets as $subnet) {
            if ($subnet['switch_id'] == $switchId) {
                return true;
            }
        }
        
        return false;
    }

    
    public function createSubnet($data)
    {
        error_log("DHCP Model: ====== Starting createSubnet ======");
        error_log("DHCP Model: Received data: " . json_encode($data, JSON_PRETTY_PRINT));
        error_log("DHCP Model: Data type: " . gettype($data));
        try {
            $subnetId = $this->getNextAvailableSubnetId();
            
            $arguments = [
                "remote" => [
                    "type" => "mysql"
                ],
                "server-tags" => ["all"],
                "subnets" => [
                    [
                        "subnet" => $data['subnet'],
                        "id" => $subnetId,
                        "shared-network-name" => null,
                        "pools" => [
                            [
                                "pool" => $data['pool_start'] . " - " . $data['pool_end']
                            ]
                        ],
                        "relay" => [
                            "ip-addresses" => [$data['relay_address']]
                        ],
                        "option-data" => [
                            [
                                "name" => "ccap-core",
                                "code" => 61,
                                "space" => "vendor-4491",
                                "csv-format" => true,
                                "data" => $data['ccap_core_address'],
                                "always-send" => true
                            ]
                        ]
                    ]
                ]
            ];
    
            error_log("DHCP Model: Remote-subnet-set arguments prepared: " . json_encode($arguments));
    
            $response = $this->sendKeaCommand('remote-subnet6-set', $arguments);
            error_log("DHCP Model: Kea response received: " . json_encode($response));
    
            if (!isset($response[0]['result']) || $response[0]['result'] !== 0) {
                error_log("DHCP Model: Remote-subnet-set command failed");
                throw new Exception("Failed to set remote subnet: " . json_encode($response));
            }
    
            // After successful KEA subnet creation, store in database
            // First, get the BVI interface details to ensure we have the correct data
            $bviSql = "SELECT id, switch_id, interface_number, ipv6_address 
                       FROM cin_switch_bvi_interfaces 
                       WHERE id = :bvi_interface_id";
            $bviStmt = $this->db->prepare($bviSql);
            $bviStmt->execute([':bvi_interface_id' => $data['bvi_interface_id']]);
            $bviData = $bviStmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$bviData) {
                throw new Exception("BVI interface not found with ID: " . $data['bvi_interface_id']);
            }
            
            $sql = "REPLACE INTO cin_bvi_dhcp_core (
                        id,
                        switch_id, 
                        kea_subnet_id, 
                        interface_number, 
                        ipv6_address, 
                        start_address, 
                        end_address, 
                        ccap_core
                    ) VALUES (
                        :id,
                        :switch_id,
                        :kea_subnet_id,
                        :interface_number,
                        :ipv6_address,
                        :start_address,
                        :end_address,
                        :ccap_core
                    )";
    
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':id' => $bviData['id'], // Use the BVI interface ID as the primary key
                ':switch_id' => $bviData['switch_id'],
                ':kea_subnet_id' => $subnetId,
                ':interface_number' => $bviData['interface_number'],
                ':ipv6_address' => $bviData['ipv6_address'],
                ':start_address' => $data['pool_start'],
                ':end_address' => $data['pool_end'],
                ':ccap_core' => $data['ccap_core_address']
            ]);
    
            error_log("DHCP Model: Remote subnet set successfully");
            
            // Reload Kea config to immediately refresh cache
            $this->reloadKeaConfig();
            
            return $subnetId;
    
        } catch (Exception $e) {
            error_log("DHCP Model: Error occurred while setting remote subnet: " . $e->getMessage());
            throw $e;
        }
    }

    public function updateSubnet($data)
    {
        error_log("DHCP Model: ====== Starting updateSubnet ======");
        error_log("DHCP Model: Received data: " . json_encode($data, JSON_PRETTY_PRINT));
        try {
            
            // First, update subnet configuration (pools, relay) without touching options
            $arguments = [
                "remote" => [
                    "type" => "mysql"
                ],
                "server-tags" => ["all"],
                "subnets" => [
                    [
                        "subnet" => $data['subnet'],
                        "id" => intval($data['subnet_id']),
                        "shared-network-name" => null,
                        "pools" => [
                            [
                                "pool" => $data['pool_start'] . " - " . $data['pool_end']
                            ]
                        ],
                        "relay" => [
                            "ip-addresses" => [$data['relay_address']]
                        ]
                    ]
                ]
            ];
    
            $response = $this->sendKeaCommand('remote-subnet6-set', $arguments);
    
            if (!isset($response[0]['result']) || $response[0]['result'] !== 0) {
                throw new Exception("Failed to set remote subnet: " . json_encode($response));
            }

            // Now restore ALL options (options get wiped by remote-subnet6-set)
            // Define all standard vendor options that should be on every subnet
            $standardOptions = [
                ['code' => 34, 'space' => 'vendor-4491', 'csv-format' => true, 'data' => '2001:b88:8000:f000:172:16:6:223,2001:b88:8000:f000:172:16:7:222', 'always-send' => true],
                ['code' => 37, 'space' => 'vendor-4491', 'csv-format' => true, 'data' => '2001:b88:8000:f000:172:16:6:222,2001:b88:8000:f000:172:16:7:222', 'always-send' => true],
                ['code' => 38, 'space' => 'vendor-4491', 'csv-format' => true, 'data' => '3600', 'always-send' => true],
                ['code' => 61, 'space' => 'vendor-4491', 'csv-format' => true, 'data' => $data['ccap_core_address'] ?? '', 'always-send' => !empty($data['ccap_core_address'])]
            ];

            // Restore each option one by one
            foreach ($standardOptions as $option) {
                $optionArgs = [
                    "remote" => ["type" => "mysql"],
                    "subnets" => [["id" => intval($data['subnet_id'])]],
                    "options" => [$option]
                ];

                $optionResponse = $this->sendKeaCommand('remote-option6-subnet-set', $optionArgs);

                if (!isset($optionResponse[0]['result']) || $optionResponse[0]['result'] !== 0) {
                    error_log("DHCP Model: Failed to set option " . $option['code'] . ": " . json_encode($optionResponse));
                }
            }

            // Get the BVI interface details to ensure we have the correct interface_number
            $bviSql = "SELECT id, switch_id, interface_number, ipv6_address 
                       FROM cin_switch_bvi_interfaces 
                       WHERE id = :bvi_interface_id";
            $bviStmt = $this->db->prepare($bviSql);
            $bviStmt->execute([':bvi_interface_id' => $data['bvi_interface_id']]);
            $bviData = $bviStmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$bviData) {
                throw new Exception("BVI interface not found with ID: " . $data['bvi_interface_id']);
            }
    
            // After reconfigurering KEA subnet creation, update in database
            $sql = "INSERT INTO cin_bvi_dhcp_core (
                bvi_interface_id,
                switch_id, 
                kea_subnet_id, 
                interface_number, 
                ipv6_address, 
                start_address, 
                end_address, 
                ccap_core
            ) VALUES (
                :bvi_interface_id,
                :switch_id,
                :kea_subnet_id,
                :interface_number,
                :ipv6_address,
                :start_address,
                :end_address,
                :ccap_core
            ) ON DUPLICATE KEY UPDATE 
                kea_subnet_id = VALUES(kea_subnet_id),
                ipv6_address = VALUES(ipv6_address),
                start_address = VALUES(start_address),
                end_address = VALUES(end_address),
                ccap_core = VALUES(ccap_core)";
    
    
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                ':bvi_interface_id' => $data['bvi_interface_id'],
                ':switch_id' => $bviData['switch_id'],
                ':kea_subnet_id' => $data['subnet_id'],
                ':interface_number' => $bviData['interface_number'],
                ':ipv6_address' => $bviData['ipv6_address'],
                ':start_address' => $data['pool_start'],
                ':end_address' => $data['pool_end'],
                ':ccap_core' => $data['ccap_core_address']
            ]);
    
            error_log("DHCP Model: Remote subnet reconfigured successfully");
            
            // Don't reload config - it wipes out option-data that wasn't saved to MySQL
            // $this->reloadKeaConfig();
            
            return $data['subnet_id'];
    
        } catch (Exception $e) {
            error_log("DHCP Model: Error occurred while reconfigering remote subnet: " . $e->getMessage());
            throw $e;
        }
    }



    public function deleteSubnet($subnetId)
    {
        try {
            // $subnetId is the KEA subnet ID (not our database record ID)
            error_log("DHCP Model: deleteSubnet called with Kea subnet ID: $subnetId");
            
            // First, delete from Kea
            $arguments = [
                "remote" => [
                    "type" => "mysql"
                ],
                "subnets" => [
                    [
                        "id" => intval($subnetId)
                    ]
                ]
            ];
    
            error_log("DHCP Model: Attempting to delete subnet from Kea with ID: $subnetId");
            
            $response = $this->sendKeaCommand('remote-subnet6-del-by-id', $arguments);
            error_log("DHCP Model: Kea response received: " . json_encode($response));
    
            // Check if the subnet was deleted from Kea or if it didn't exist
            $keaDeleted = false;
            if (isset($response[0]['result']) && $response[0]['result'] === 0 && 
                isset($response[0]['arguments']['count']) && $response[0]['arguments']['count'] > 0) {
                $keaDeleted = true;
                error_log("DHCP Model: Successfully deleted subnet from Kea. Response text: " . $response[0]['text']);
            } else {
                error_log("DHCP Model: Subnet not found in Kea (may have been deleted already). Response: " . ($response[0]['text'] ?? 'Unknown'));
            }
    
            // Now delete from our database using the Kea subnet ID
            error_log("DHCP Model: Deleting record from cin_bvi_dhcp_core table where kea_subnet_id = $subnetId");
            $sql = "DELETE FROM cin_bvi_dhcp_core WHERE kea_subnet_id = :kea_subnet_id";
            $deleteStmt = $this->db->prepare($sql);
            $deleteStmt->execute([':kea_subnet_id' => $subnetId]);
            
            $rowsAffected = $deleteStmt->rowCount();
            error_log("DHCP Model: Deleted {$rowsAffected} rows from cin_bvi_dhcp_core table");

            // Reload Kea config to immediately refresh cache if we deleted from Kea
            if ($keaDeleted) {
                $this->reloadKeaConfig();
            }

            error_log("DHCP Model: ====== Completed deleteSubnet successfully ======");
            return true;
    
        } catch (Exception $e) {
            error_log("DHCP Model: Error occurred while deleting subnet: " . $e->getMessage());
            
            // Still try to delete from database even if Kea deletion failed
            try {
                error_log("DHCP Model: Attempting to clean up database record despite error");
                $sql = "DELETE FROM cin_bvi_dhcp_core WHERE id = :subnet_id";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([':subnet_id' => $subnetId]);
                error_log("DHCP Model: Database record cleaned up");
            } catch (Exception $dbError) {
                error_log("DHCP Model: Could not clean up database: " . $dbError->getMessage());
            }
            
            throw $e;
        }
    }


    public function getAllSubnetsfromKEA()
    {
        error_log("DHCP Model: ====== Starting getAllSubnets ======");
        
        try {
            error_log("DHCP Model: Preparing arguments for KEA command");
            $arguments = [
                "remote" => [
                    "type" => "mysql"
                ],
                "server-tags" => ["all"],
            ];
    
            error_log("DHCP Model: Arguments prepared: " . json_encode($arguments));
            
            error_log("DHCP Model: Sending remote-subnet6-list command to KEA");
            $response = $this->sendKeaCommand('remote-subnet6-list', $arguments);
            error_log("DHCP Model: Response type: " . gettype($response));
            error_log("DHCP Model: Raw response: " . json_encode($response));
    
            if (!is_array($response) || empty($response) || !isset($response[0])) {
                error_log("DHCP Model: ERROR - Invalid response format");
                return [];
            }
    
            $firstResponse = $response[0];
    
            if (!isset($firstResponse['result'])) {
                error_log("DHCP Model: ERROR - Result code missing in response");
                throw new Exception("Missing result code in KEA response");
            }
    
            // Check if it's a valid "no subnets" response
            if ($firstResponse['result'] === 3 && 
                isset($firstResponse['arguments']['count']) && 
                $firstResponse['arguments']['count'] === 0) {
                error_log("DHCP Model: No subnets found (valid empty response)");
                return [];
            }
    
            // For other non-zero results, throw an exception
            if ($firstResponse['result'] !== 0) {
                error_log("DHCP Model: ERROR - Non-zero result code: " . $firstResponse['result']);
                throw new Exception("Failed to get subnets: " . json_encode($response));
            }
    
            $subnets = $firstResponse['arguments']['subnets'] ?? [];
            error_log("DHCP Model: Number of subnets retrieved: " . count($subnets));
            
            if (!empty($subnets)) {
                error_log("DHCP Model: First subnet example: " . json_encode($subnets[0]));
            }
    
            error_log("DHCP Model: ====== Completed getAllSubnets successfully ======");
            return $subnets;
    
        } catch (Exception $e) {
            error_log("DHCP Model: ====== ERROR in getAllSubnets ======");
            error_log("DHCP Model: Exception message: " . $e->getMessage());
            error_log("DHCP Model: Exception trace: " . $e->getTraceAsString());
            throw $e;
        }
    }
    
      

    public function getEnrichedSubnets()
    {
        error_log("DHCP Model: ====== Starting getEnrichedSubnets ======");
        
        try {
            $keaResponse = $this->getAllSubnetsfromKEA();
            error_log("DHCP Model: Raw response data: " . json_encode($keaResponse));

            // If we get null or empty array from getAllSubnetsfromKEA, return empty array
            if (empty($keaResponse)) {
                error_log("DHCP Model: No subnets found in KEA response");
                error_log("DHCP Model: ====== Completed getEnrichedSubnets. Total subnets: 0 ======");
                return [];
            }

            // Ensure we have valid subnets to process
            if (!is_array($keaResponse)) {
                error_log("DHCP Model: Invalid response format from KEA");
                return [];
            }

            error_log("DHCP Model: Processing " . count($keaResponse) . " subnets");

            $enrichedSubnets = [];
            foreach ($keaResponse as $subnet) {
                if (!is_array($subnet)) {
                    error_log("DHCP Model: Invalid subnet data, skipping");
                    continue;
                }

                error_log("DHCP Model: Processing subnet: " . json_encode($subnet));
                
                // Map the database fields to our expected structure
                $subnetId = $subnet['id'] ?? 'unknown';
                error_log("DHCP Model: Processing subnet ID: " . $subnetId);
                
                // Get custom configuration
                error_log("DHCP Model: Getting custom config for subnet " . $subnetId);
                $customConfig = $this->getBVIConfig($subnetId);
                error_log("DHCP Model: Custom config received: " . json_encode($customConfig));

                // Extract pool information from the subnet data
                $poolStart = '';
                $poolEnd = '';
                if (isset($subnet['pools']) && is_array($subnet['pools']) && !empty($subnet['pools'])) {
                    $poolParts = explode('-', $subnet['pools'][0]['pool'] ?? '');
                    $poolStart = trim($poolParts[0] ?? '');
                    $poolEnd = trim($poolParts[1] ?? '');
                }

                // Get switch hostname if we have a switch_id
                $switchHostname = null;
                if (!empty($customConfig['switch_id'])) {
                    $switchStmt = $this->db->prepare("SELECT hostname FROM cin_switches WHERE id = ?");
                    $switchStmt->execute([$customConfig['switch_id']]);
                    $switchData = $switchStmt->fetch(PDO::FETCH_ASSOC);
                    $switchHostname = $switchData['hostname'] ?? null;
                }

                $enrichedSubnet = [
                    'id' => $subnetId,
                    'subnet' => $subnet['subnet'] ?? '',
                    'pool' => [
                        'start' => $customConfig['start_address'] ?? $poolStart,
                        'end' => $customConfig['end_address'] ?? $poolEnd
                    ],
                    'bvi_interface' => $customConfig['interface_number'] ?? null,
                    'interface_number' => $customConfig['interface_number'] ?? null,  // Add for BVI calculation
                    'bvi_interface_id' => $customConfig['bvi_interface_id'] ?? null,
                    'ccap_core' => $customConfig['ccap_core'] ?? null,
                    'ipv6_address' => $customConfig['ipv6_address'] ?? null,
                    'switch_id' => $customConfig['switch_id'] ?? null,
                    'switch_hostname' => $switchHostname,  // Add switch hostname
                    'created_at' => $customConfig['created_at'] ?? null,
                    'updated_at' => $customConfig['updated_at'] ?? null
                ];
                
                error_log("DHCP Model: Enriched subnet data: " . json_encode($enrichedSubnet));
                $enrichedSubnets[] = $enrichedSubnet;
            }

            error_log("DHCP Model: ====== Completed getEnrichedSubnets. Total subnets: " . count($enrichedSubnets) . " ======");
            return $enrichedSubnets;

        } catch (Exception $e) {
            error_log("DHCP Model: ====== ERROR in getEnrichedSubnets ======");
            error_log("DHCP Model: Exception message: " . $e->getMessage());
            error_log("DHCP Model: Exception trace: " . $e->getTraceAsString());
            throw $e;
        }
    }
    
    private function getBVIConfig($subnetId)
    {
        $stmt = $this->db->prepare("
            SELECT * FROM cin_bvi_dhcp_core 
            WHERE kea_subnet_id = ?
        ");
        $stmt->execute([$subnetId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAllSubnets() {
        $query = "SELECT 
            s.subnet_id,
            s.subnet_prefix,
            c.bvi_interface_id,
            p.start_address as pool_start,
            p.end_address as pool_end,
            c.ccap_core_address
        FROM dhcp6_subnet s
        LEFT JOIN dhcp6_pool p ON s.subnet_id = p.subnet_id
        LEFT JOIN custom_dhcp6_subnet_config c ON s.subnet_id = c.subnet_id";
    
        try {
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Error fetching subnets: " . $e->getMessage());
            return [];
        }
    }

    public function beginTransaction()
    {
        return $this->db->beginTransaction();
    }

    public function commit()
    {
        return $this->db->commit();
    }

    public function rollback()
    {
        return $this->db->rollBack();
    }

    public function checkDuplicateSubnet($subnet, $excludeId = null)
    {
        $query = "SELECT subnet_id FROM dhcp6_subnet WHERE subnet_prefix = :subnet";
        $params = ['subnet' => $subnet];
    
        if ($excludeId) {
            $query .= " AND subnet_id != :exclude_id";
            $params['exclude_id'] = $excludeId;
        }
    
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        
        return $stmt;  
    }
    
    public function getSubnetById($subnetId)
    {
        try {
            $arguments = [
                "remote" => [
                    "type" => "mysql"
                ],
                "subnets" => [
                    [
                        "id" => intval($subnetId)
                    ]
                ]
            ];
    
            error_log("DHCP Model: Getting subnet by ID: " . $subnetId);
            
            $response = $this->sendKeaCommand('remote-subnet6-get-by-id', $arguments);
            error_log("DHCP Model: Kea response received: " . json_encode($response));
    
            // Check if response is valid and has count > 0
            if (!isset($response[0]['result']) || $response[0]['result'] !== 0 || 
                !isset($response[0]['arguments']['count']) || $response[0]['arguments']['count'] === 0) {
                error_log("DHCP Model: Get subnet by ID command failed or no subnet found");
                return null;
            }
    
            $subnet = $response[0]['arguments']['subnets'][0];
            
            // Format the response with the specific fields we need
            return [
                'id' => $subnet['id'],
                'subnet' => $subnet['subnet'],
                'pools' => array_map(function($pool) {
                    return [
                        'pool_start' => explode('-', $pool['pool'])[0],
                        'pool_end' => explode('-', $pool['pool'])[1]
                    ];
                }, $subnet['pools']),
                'relay_addresses' => $subnet['relay']['ip-addresses'] ?? [],
                'shared_network_name' => $subnet['shared-network-name'],
                'option_data' => $subnet['option-data']
            ];
    
        } catch (Exception $e) {
            error_log("DHCP Model: Error occurred while getting subnet by ID: " . $e->getMessage());
            throw $e;
        }
    }
    
    


    private function deleteSubnetFromDatabase($subnet_id)
    {
        try {
            // Only delete from OUR custom table, never touch Kea tables
            // Kea tables are managed by Kea API only
            $query1 = "DELETE FROM custom_dhcp6_subnet_config WHERE subnet_id = :subnet_id";
            $stmt1 = $this->db->prepare($query1);
            $stmt1->execute(['subnet_id' => $subnet_id]);
    
            // FORBIDDEN: Never delete from dhcp6_pool, dhcp6_subnet, or any Kea tables
            // Those are managed exclusively by Kea API
    
            return true;
        } catch (\PDOException $e) {
            throw new \Exception("Database error: " . $e->getMessage());
        }
    }
    
        
    

    public function deleteOrphanedSubnetFromKea($keaSubnetId)
    {
        try {
            $arguments = [
                "remote" => [
                    "type" => "mysql"
                ],
                "subnets" => [
                    [
                        "id" => intval($keaSubnetId)
                    ]
                ]
            ];
    
            error_log("DHCP Model: Deleting orphaned subnet with Kea ID: $keaSubnetId");
            
            $response = $this->sendKeaCommand('remote-subnet6-del-by-id', $arguments);
            error_log("DHCP Model: Kea response received: " . json_encode($response));
    
            if (isset($response[0]['result']) && $response[0]['result'] === 0 && 
                isset($response[0]['arguments']['count']) && $response[0]['arguments']['count'] > 0) {
                error_log("DHCP Model: Successfully deleted orphaned subnet from Kea");
                $this->reloadKeaConfig();
                return true;
            } else {
                throw new Exception("Failed to delete orphaned subnet: " . ($response[0]['text'] ?? 'Unknown error'));
            }
    
        } catch (Exception $e) {
            error_log("DHCP Model: Error deleting orphaned subnet: " . $e->getMessage());
            throw $e;
        }
    }
}
