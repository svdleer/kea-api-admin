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
        
        // Send to all servers
        $serversToContact = $keaServers;
        
        $responses = [];
        $errors = [];
        $successfulServers = []; // Track servers that succeeded for potential rollback
        
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
                
                // Rollback successful servers if we have any
                if (!empty($successfulServers)) {
                    $this->rollbackCommand($command, $arguments, $successfulServers);
                }
                continue;
            }
            
            curl_close($ch);
            
            $decoded = json_decode($response, true);
            $responses[] = $decoded;
            
            // Check if this server's response was successful
            // Result 0 = success, Result 3 = empty/not found (valid for list commands)
            $result = $decoded[0]['result'] ?? null;
            $isSuccess = ($result === 0) || ($result === 3 && $command === 'subnet6-list');
            
            if (!isset($decoded[0]['result']) || !$isSuccess) {
                $error = "Kea command '{$command}' failed on {$server['name']}: " . ($decoded[0]['text'] ?? 'Unknown error');
                error_log($error);
                $errors[] = $error;
                
                // Rollback successful servers
                if (!empty($successfulServers)) {
                    $this->rollbackCommand($command, $arguments, $successfulServers);
                }
            } else {
                error_log("Kea command '{$command}' succeeded on {$server['name']}");
                $successfulServers[] = $server;
            }
        }
        
        // If ANY server failed, throw an exception to keep all servers in sync
        if (!empty($errors)) {
            throw new Exception("Kea command failed on one or more servers. All servers must succeed to maintain sync: " . implode("; ", $errors));
        }
        
        // Return the first successful response (for backward compatibility)
        foreach ($responses as $response) {
            $result = $response[0]['result'] ?? null;
            $isSuccess = ($result === 0) || ($result === 3 && $command === 'subnet6-list');
            if ($isSuccess) {
                return $response;
            }
        }
        
        // If no successful response, return the first response
        return $responses[0] ?? [];
    }

    /**
     * Rollback a command on servers that succeeded before a failure occurred
     */
    private function rollbackCommand($originalCommand, $originalArguments, $serversToRollback)
    {
        if (empty($serversToRollback)) {
            return;
        }

        error_log("DHCP Model: Rolling back command '{$originalCommand}' on " . count($serversToRollback) . " servers");
        
        // Determine the rollback command based on the original command
        $rollbackCommand = null;
        $rollbackArguments = [];
        
        switch ($originalCommand) {
            case 'subnet6-add':
                // Delete the subnet we just added
                $rollbackCommand = 'subnet6-del';
                if (isset($originalArguments['subnet6'][0]['id'])) {
                    $rollbackArguments = ['id' => $originalArguments['subnet6'][0]['id']];
                }
                break;
                
            case 'subnet6-delta-add':
                // This is trickier - we'd need to know what was there before
                // For now, just log it
                error_log("DHCP Model: Cannot automatically rollback subnet6-delta-add - manual intervention may be required");
                return;
                
            case 'subnet6-del':
                // Can't rollback a deletion without the full subnet data
                error_log("DHCP Model: Cannot rollback subnet6-del - subnet is already deleted");
                return;
                
            default:
                error_log("DHCP Model: No rollback strategy for command '{$originalCommand}'");
                return;
        }
        
        if (!$rollbackCommand) {
            return;
        }
        
        // Execute rollback on each successful server
        foreach ($serversToRollback as $server) {
            $data = [
                "command" => $rollbackCommand,
                "service" => [$this->keaService],
                "arguments" => $rollbackArguments
            ];
            
            $ch = curl_init($server['api_url']);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            
            if (curl_errno($ch)) {
                error_log("DHCP Model: Rollback failed on {$server['name']}: " . curl_error($ch));
            } else {
                $decoded = json_decode($response, true);
                if (isset($decoded[0]['result']) && $decoded[0]['result'] === 0) {
                    error_log("DHCP Model: Successfully rolled back on {$server['name']}");
                } else {
                    error_log("DHCP Model: Rollback command failed on {$server['name']}: " . ($decoded[0]['text'] ?? 'Unknown error'));
                }
            }
            
            curl_close($ch);
        }
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

    /**
     * Persist Kea configuration to file
     * In Kea 3.x without MySQL backend, changes are in-memory only until written to file
     */
    private function saveKeaConfig()
    {
        error_log("DHCP Model: Persisting config to file with config-write");
        try {
            $arguments = [
                "filename" => "/opt/kea/etc/kea/kea-dhcp6.conf"
            ];
            $response = $this->sendKeaCommand('config-write', $arguments);
            error_log("DHCP Model: Config-write response: " . json_encode($response));
            
            // Reload config from file to apply changes
            error_log("DHCP Model: Reloading config from file with config-reload");
            $reloadResponse = $this->sendKeaCommand('config-reload');
            error_log("DHCP Model: Config-reload response: " . json_encode($reloadResponse));
            
            return true;
        } catch (Exception $e) {
            error_log("DHCP Model: Warning - config-write failed: " . $e->getMessage());
            // Don't throw - write failure shouldn't fail the main operation, but log it prominently
            error_log("DHCP Model: WARNING - Changes are in-memory only and will be lost on Kea restart!");
            return false;
        }
    }

    /**
     * Backup current Kea configuration before making changes
     * Keeps only the last 12 backups per server
     */
    private function backupKeaConfig($operation = 'unknown')
    {
        try {
            error_log("DHCP Model: Creating config backup before operation: {$operation}");
            
            // Get all active Kea servers
            $stmt = $this->db->prepare("SELECT id, name, api_url FROM kea_servers WHERE is_active = 1");
            $stmt->execute();
            $servers = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            foreach ($servers as $server) {
                // Get current config from this server
                $ch = curl_init($server['api_url']);
                $data = [
                    "command" => "config-get",
                    "service" => [$this->keaService]
                ];
                
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                
                $response = curl_exec($ch);
                curl_close($ch);
                
                if (!$response) {
                    error_log("DHCP Model: Failed to get config from {$server['name']} for backup");
                    continue;
                }
                
                $decoded = json_decode($response, true);
                if (!isset($decoded[0]['result']) || $decoded[0]['result'] !== 0) {
                    error_log("DHCP Model: Config-get failed for {$server['name']}");
                    continue;
                }
                
                // Store backup
                $insertStmt = $this->db->prepare("
                    INSERT INTO kea_config_backups (server_id, config_json, created_by, operation)
                    VALUES (:server_id, :config_json, :created_by, :operation)
                ");
                
                $insertStmt->execute([
                    ':server_id' => $server['id'],
                    ':config_json' => json_encode($decoded[0]['arguments']),
                    ':created_by' => $_SESSION['user_name'] ?? 'system',
                    ':operation' => $operation
                ]);
                
                error_log("DHCP Model: Backed up config for {$server['name']}");
                
                // Clean up old backups - keep only last 12 per server
                $cleanupStmt = $this->db->prepare("
                    DELETE FROM kea_config_backups
                    WHERE server_id = :server_id
                    AND id NOT IN (
                        SELECT id FROM (
                            SELECT id FROM kea_config_backups
                            WHERE server_id = :server_id
                            ORDER BY created_at DESC
                            LIMIT 12
                        ) tmp
                    )
                ");
                
                $cleanupStmt->execute([':server_id' => $server['id']]);
                
                $deletedCount = $cleanupStmt->rowCount();
                if ($deletedCount > 0) {
                    error_log("DHCP Model: Cleaned up {$deletedCount} old backups for {$server['name']}");
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log("DHCP Model: Warning - backup failed: " . $e->getMessage());
            // Don't throw - backup failure shouldn't prevent the operation
            return false;
        }
    }

    /**
     * Check if vendor option definitions exist in Kea configuration
     * Returns true if definitions exist, false otherwise
     */
    private function checkVendorOptionDefinitions()
    {
        try {
            error_log("DHCP Model: Checking for vendor-4491 option definitions");
            $response = $this->sendKeaCommand('config-get');
            
            if (!isset($response[0]['arguments']['Dhcp6']['option-def'])) {
                error_log("DHCP Model: No option-def section found in config");
                return false;
            }
            
            $optionDefs = $response[0]['arguments']['Dhcp6']['option-def'];
            
            // Check if vendor-4491 space definitions exist
            $hasVendorDefs = false;
            foreach ($optionDefs as $def) {
                if (isset($def['space']) && $def['space'] === 'vendor-4491') {
                    $hasVendorDefs = true;
                    break;
                }
            }
            
            error_log("DHCP Model: Vendor-4491 option definitions " . ($hasVendorDefs ? "found" : "NOT found"));
            return $hasVendorDefs;
            
        } catch (Exception $e) {
            error_log("DHCP Model: Error checking option definitions: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate that a subnet doesn't overlap with existing subnets
     * @param string $subnet The subnet to validate (e.g., "2001:db8::/64")
     * @param int|null $excludeSubnetId Subnet ID to exclude from check (for updates)
     * @throws Exception if subnet overlaps
     */
    private function validateSubnetOverlap($subnet, $excludeSubnetId = null)
    {
        error_log("DHCP Model: Validating subnet {$subnet} for overlaps");
        
        try {
            // Get all existing subnets from Kea
            $response = $this->sendKeaCommand('subnet6-list', []);
            
            if (!isset($response[0]['arguments']['subnets'])) {
                // No existing subnets, so no overlap
                return;
            }
            
            $existingSubnets = $response[0]['arguments']['subnets'];
            
            // Parse the new subnet
            list($newNetwork, $newPrefix) = explode('/', $subnet);
            $newPrefixInt = intval($newPrefix);
            
            foreach ($existingSubnets as $existing) {
                // Skip if this is the same subnet we're updating
                if ($excludeSubnetId !== null && isset($existing['id']) && $existing['id'] == $excludeSubnetId) {
                    continue;
                }
                
                if (!isset($existing['subnet'])) {
                    continue;
                }
                
                list($existingNetwork, $existingPrefix) = explode('/', $existing['subnet']);
                $existingPrefixInt = intval($existingPrefix);
                
                // Check for overlap by comparing network addresses
                // Two subnets overlap if one contains the other
                if ($this->ipv6SubnetsOverlap($newNetwork, $newPrefixInt, $existingNetwork, $existingPrefixInt)) {
                    throw new Exception("Subnet {$subnet} overlaps with existing subnet {$existing['subnet']} (ID: {$existing['id']})");
                }
            }
            
            error_log("DHCP Model: No overlaps found for subnet {$subnet}");
            
        } catch (Exception $e) {
            // Re-throw overlap exceptions
            if (strpos($e->getMessage(), 'overlaps') !== false) {
                throw $e;
            }
            // Log other errors but don't fail validation
            error_log("DHCP Model: Warning - overlap validation failed: " . $e->getMessage());
        }
    }

    /**
     * Check configuration sync status across all Kea servers
     * Returns array with sync status and details
     */
    public function getConfigSyncStatus()
    {
        try {
            // Get all active Kea servers
            $stmt = $this->db->prepare("SELECT id, name, api_url FROM kea_servers WHERE is_active = 1");
            $stmt->execute();
            $servers = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // If only 1 server, no sync check needed
            if (count($servers) <= 1) {
                return [
                    'in_sync' => true,
                    'server_count' => count($servers),
                    'message' => 'Single server configuration'
                ];
            }
            
            $configs = [];
            $errors = [];
            
            // Get config from each server
            foreach ($servers as $server) {
                $ch = curl_init($server['api_url']);
                $data = [
                    "command" => "config-get",
                    "service" => [$this->keaService]
                ];
                
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                
                $response = curl_exec($ch);
                
                if (curl_errno($ch)) {
                    $errors[] = "{$server['name']}: " . curl_error($ch);
                    curl_close($ch);
                    continue;
                }
                
                curl_close($ch);
                
                $decoded = json_decode($response, true);
                if (!isset($decoded[0]['result']) || $decoded[0]['result'] !== 0) {
                    $errors[] = "{$server['name']}: Config-get failed";
                    continue;
                }
                
                // Store subnet6 configuration for comparison
                $configs[$server['name']] = $decoded[0]['arguments']['Dhcp6']['subnet6'] ?? [];
            }
            
            if (empty($configs)) {
                return [
                    'in_sync' => false,
                    'server_count' => count($servers),
                    'message' => 'Failed to retrieve config from any server',
                    'errors' => $errors
                ];
            }
            
            // Compare all configs
            $baseConfig = reset($configs);
            $baseServerName = key($configs);
            $inSync = true;
            $differences = [];
            
            foreach ($configs as $serverName => $config) {
                if ($serverName === $baseServerName) continue;
                
                if (json_encode($config) !== json_encode($baseConfig)) {
                    $inSync = false;
                    $differences[] = "{$serverName} differs from {$baseServerName}";
                }
            }
            
            return [
                'in_sync' => $inSync,
                'server_count' => count($servers),
                'checked_servers' => array_keys($configs),
                'message' => $inSync ? 'All servers in sync' : 'Configuration mismatch detected',
                'differences' => $differences,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            error_log("DHCP Model: Error checking config sync: " . $e->getMessage());
            return [
                'in_sync' => false,
                'server_count' => 0,
                'message' => 'Error checking sync status',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check if two IPv6 subnets overlap
     */
    private function ipv6SubnetsOverlap($net1, $prefix1, $net2, $prefix2)
    {
        // Convert IPv6 addresses to binary for comparison
        $bin1 = inet_pton($net1);
        $bin2 = inet_pton($net2);
        
        if ($bin1 === false || $bin2 === false) {
            error_log("DHCP Model: Invalid IPv6 address in overlap check");
            return false;
        }
        
        // Use the smaller prefix length for comparison
        $comparePrefix = min($prefix1, $prefix2);
        
        // Compare the bits up to the prefix length
        $bytes = intval($comparePrefix / 8);
        $bits = $comparePrefix % 8;
        
        // Compare full bytes
        if ($bytes > 0 && substr($bin1, 0, $bytes) !== substr($bin2, 0, $bytes)) {
            return false;
        }
        
        // Compare remaining bits if any
        if ($bits > 0 && $bytes < 16) {
            $mask = 0xFF << (8 - $bits);
            $byte1 = ord($bin1[$bytes]) & $mask;
            $byte2 = ord($bin2[$bytes]) & $mask;
            if ($byte1 !== $byte2) {
                return false;
            }
        }
        
        // Subnets overlap
        return true;
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
        try {
            // Validate subnet doesn't overlap with existing subnets
            $this->validateSubnetOverlap($data['subnet'], null);
            
            // Backup config before making changes
            $this->backupKeaConfig('subnet-create');
            
            $subnetId = $this->getNextAvailableSubnetId();
            
            // Create subnet without options first
            $arguments = [
                "subnet6" => [
                    [
                        "subnet" => $data['subnet'],
                        "id" => $subnetId,
                        "client-class" => "RPD",
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
    
            $response = $this->sendKeaCommand('subnet6-add', $arguments);
    
            if (!isset($response[0]['result']) || $response[0]['result'] !== 0) {
                throw new Exception("Failed to set remote subnet: " . json_encode($response));
            }

            // Add only option 61 (CCAP core) to the subnet
            // Options 34, 37, 38 are at global level and inherited
            if (!empty($data['ccap_core_address'])) {
                // First, verify that vendor option definitions exist
                if (!$this->checkVendorOptionDefinitions()) {
                    // Clean up - delete the subnet we just created
                    error_log("DHCP Model: Cleaning up - deleting subnet ID $subnetId due to missing vendor options");
                    try {
                        $this->sendKeaCommand('subnet6-del', ["id" => $subnetId]);
                    } catch (\Exception $deleteEx) {
                        error_log("DHCP Model: Failed to delete subnet during cleanup: " . $deleteEx->getMessage());
                    }
                    
                    error_log("DHCP Model: ERROR - Vendor option definitions (vendor-4491) not configured. Cannot create subnet with CCAP core address.");
                    throw new \Exception("VENDOR_OPTIONS_MISSING: Vendor option definitions (vendor-4491) are not configured. Please configure CableLabs vendor options in the DHCP options menu before creating subnets with CCAP Cores.");
                }
                
                $optionArgs = [
                    "subnet6" => [[
                        "id" => $subnetId,
                        "subnet" => $data['subnet'],
                        "option-data" => [[
                            'code' => 61,
                            'space' => 'vendor-4491',
                            'csv-format' => true,
                            'data' => $data['ccap_core_address'],
                            'always-send' => true
                        ]]
                    ]]
                ];

                // Retry up to 3 times if option definition doesn't exist (MySQL HA sync delay)
                $maxRetries = 3;
                $retryDelay = 1; // seconds
                $success = false;
                
                for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                    $optionResponse = $this->sendKeaCommand('subnet6-delta-add', $optionArgs);
                    
                    if (isset($optionResponse[0]['result']) && $optionResponse[0]['result'] === 0) {
                        $success = true;
                        break;
                    }
                    
                    // Check if error is about missing option definition
                    if (isset($optionResponse[0]['text']) && strpos($optionResponse[0]['text'], 'does not exist') !== false) {
                        error_log("DHCP Model: Option definition not yet synced, retry $attempt/$maxRetries");
                        if ($attempt < $maxRetries) {
                            sleep($retryDelay);
                            continue;
                        }
                    }
                    
                    error_log("DHCP Model: Failed to set option 61: " . json_encode($optionResponse));
                    break;
                }
                
                if (!$success) {
                    // If option setting failed, delete the subnet to avoid orphans
                    $this->sendKeaCommand('subnet6-del', [
                        "id" => $subnetId
                    ]);
                    throw new Exception("Failed to set option 61 after $maxRetries attempts. Subnet creation rolled back.");
                }
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
                        bvi_interface_id,
                        switch_id, 
                        kea_subnet_id, 
                        interface_number, 
                        ipv6_address, 
                        start_address, 
                        end_address, 
                        ccap_core
                    ) VALUES (
                        :id,
                        :bvi_interface_id,
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
                ':bvi_interface_id' => $bviData['id'],
                ':switch_id' => $bviData['switch_id'],
                ':kea_subnet_id' => $subnetId,
                ':interface_number' => $bviData['interface_number'],
                ':ipv6_address' => $bviData['ipv6_address'],
                ':start_address' => $data['pool_start'],
                ':end_address' => $data['pool_end'],
                ':ccap_core' => $data['ccap_core_address']
            ]);
    
            error_log("DHCP Model: Remote subnet set successfully");
            
            // Persist changes to Kea config file
            $this->saveKeaConfig();
            
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
            // Validate subnet doesn't overlap with other existing subnets
            $this->validateSubnetOverlap($data['subnet'], intval($data['subnet_id']));
            
            // Backup config before making changes
            $this->backupKeaConfig('subnet-update');
            
            // First, update subnet configuration (pools, relay) without touching options
            $arguments = [
                "subnet6" => [
                    [
                        "subnet" => $data['subnet'],
                        "id" => intval($data['subnet_id']),
                        "client-class" => "RPD",
                        "pools" => [
                            [
                                "pool" => $data['pool_start'] . " - " . $data['pool_end']
                            ]
                        ],
                        "relay" => [
                            "ip-addresses" => [$data['relay_address']]
                        ],
                        "valid-lifetime" => isset($data['valid_lifetime']) ? intval($data['valid_lifetime']) : 7200,
                        "preferred-lifetime" => isset($data['preferred_lifetime']) ? intval($data['preferred_lifetime']) : 3600,
                        "renew-timer" => isset($data['renew_timer']) ? intval($data['renew_timer']) : 1000,
                        "rebind-timer" => isset($data['rebind_timer']) ? intval($data['rebind_timer']) : 2000
                    ]
                ]
            ];
    
            $response = $this->sendKeaCommand('subnet6-update', $arguments);
    
            if (!isset($response[0]['result']) || $response[0]['result'] !== 0) {
                throw new Exception("Failed to update remote subnet: " . json_encode($response));
            }

            // Check if we're removing CCAP core option (was set before, now empty)
            if (empty($data['ccap_core_address'])) {
                // Check if this subnet had a CCAP core before
                $checkStmt = $this->db->prepare("SELECT ccap_core FROM cin_bvi_dhcp_core WHERE kea_subnet_id = :subnet_id");
                $checkStmt->execute([':subnet_id' => intval($data['subnet_id'])]);
                $existing = $checkStmt->fetch(\PDO::FETCH_ASSOC);
                
                if ($existing && !empty($existing['ccap_core'])) {
                    error_log("DHCP Model: WARNING - CCAP core option 61 is being REMOVED from subnet ID {$data['subnet_id']}. Previous value: {$existing['ccap_core']}");
                    // Note: We don't delete the option from Kea here, it stays in the config
                    // This is just a warning that the new configuration doesn't include it
                }
            }

            // Restore option 61 (CCAP core) only
            // Options 34, 37, 38 are at global level and inherited
            if (!empty($data['ccap_core_address'])) {
                // Verify that vendor option definitions exist
                if (!$this->checkVendorOptionDefinitions()) {
                    throw new Exception("Vendor option definitions (vendor-4491) are not configured in Kea. Please ensure option definitions for CableLabs vendor options are set in the Kea configuration file.");
                }
                
                $optionArgs = [
                    "subnet6" => [[
                        "id" => intval($data['subnet_id']),
                        "subnet" => $data['subnet'],
                        "option-data" => [[
                            'code' => 61,
                            'space' => 'vendor-4491',
                            'csv-format' => true,
                            'data' => $data['ccap_core_address'],
                            'always-send' => true
                        ]]
                    ]]
                ];

                // Retry up to 3 times if option definition doesn't exist (MySQL HA sync delay)
                $maxRetries = 3;
                $retryDelay = 1; // seconds
                
                for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                    $optionResponse = $this->sendKeaCommand('subnet6-delta-add', $optionArgs);
                    
                    if (isset($optionResponse[0]['result']) && $optionResponse[0]['result'] === 0) {
                        break;
                    }
                    
                    // Check if error is about missing option definition
                    if (isset($optionResponse[0]['text']) && strpos($optionResponse[0]['text'], 'does not exist') !== false) {
                        error_log("DHCP Model: Option definition not yet synced, retry $attempt/$maxRetries");
                        if ($attempt < $maxRetries) {
                            sleep($retryDelay);
                            continue;
                        }
                    }
                    
                    error_log("DHCP Model: Failed to set option 61: " . json_encode($optionResponse));
                    break;
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
            
            // Persist changes to disk
            $this->saveKeaConfig();
            
            return $data['subnet_id'];
    
        } catch (Exception $e) {
            error_log("DHCP Model: Error occurred while reconfigering remote subnet: " . $e->getMessage());
            throw $e;
        }
    }



    public function deleteSubnet($subnetId)
    {
        try {
            // Backup config before making changes
            $this->backupKeaConfig('subnet-delete');
            
            // $subnetId is the KEA subnet ID (not our database record ID)
            error_log("DHCP Model: deleteSubnet called with Kea subnet ID: $subnetId");
            
            // First, delete from Kea
            $arguments = [
                "id" => intval($subnetId)
            ];
    
            error_log("DHCP Model: Attempting to delete subnet from Kea with ID: $subnetId");
            
            $response = $this->sendKeaCommand('subnet6-del', $arguments);
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

            // Persist config changes to file if we deleted from Kea
            if ($keaDeleted) {
                $this->saveKeaConfig();
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
            $arguments = [];
    
            error_log("DHCP Model: Arguments prepared: " . json_encode($arguments));
            
            error_log("DHCP Model: Sending subnet6-list command to KEA");
            $response = $this->sendKeaCommand('subnet6-list', $arguments);
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
    
            // Check if it's a valid "no subnets" response (result code 3)
            if ($firstResponse['result'] === 3) {
                error_log("DHCP Model: No subnets found (result code 3 - valid empty response)");
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
                    'updated_at' => $customConfig['updated_at'] ?? null,
                    'valid_lifetime' => $subnet['valid-lifetime'] ?? 7200,
                    'preferred_lifetime' => $subnet['preferred-lifetime'] ?? 3600,
                    'renew_timer' => $subnet['renew-timer'] ?? 1000,
                    'rebind_timer' => $subnet['rebind-timer'] ?? 2000
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
                "id" => intval($subnetId)
            ];

            error_log("DHCP Model: Getting subnet by ID: " . $subnetId);
            
            $response = $this->sendKeaCommand('subnet6-get', $arguments);
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
                'option_data' => $subnet['option-data'],
                'valid_lifetime' => $subnet['valid-lifetime'] ?? 7200,
                'preferred_lifetime' => $subnet['preferred-lifetime'] ?? 3600,
                'renew_timer' => $subnet['renew-timer'] ?? 1000,
                'rebind_timer' => $subnet['rebind-timer'] ?? 2000
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
                "id" => intval($keaSubnetId)
            ];

            error_log("DHCP Model: Deleting orphaned subnet with Kea ID: $keaSubnetId");
            
            $response = $this->sendKeaCommand('subnet6-del', $arguments);
            error_log("DHCP Model: Kea response received: " . json_encode($response));
    
            if (isset($response[0]['result']) && $response[0]['result'] === 0 && 
                isset($response[0]['arguments']['count']) && $response[0]['arguments']['count'] > 0) {
                error_log("DHCP Model: Successfully deleted orphaned subnet from Kea");
                $this->saveKeaConfig();
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
