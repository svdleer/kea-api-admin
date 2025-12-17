<?php
namespace App\Models;

use Exception;

class DHCPv6OptionsModel extends KeaModel
{
    private const KEA_SUCCESS = 0;
    private const KEA_ERROR = 1;
    private const KEA_UNSUPPORTED = 2;
    private const KEA_EMPTY = 3;


    private function validateKeaResponse(string $response, string $operation): array 
    {
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response: " . json_last_error_msg());
        }

        if (empty($decoded[0])) {
            throw new Exception("Invalid response format");
        }

        $result = $decoded[0];
        $resultCode = $result['result'];

        switch ($resultCode) {
            case self::KEA_SUCCESS:
                return $result;
            case self::KEA_ERROR:
                throw new Exception("KEA error during {$operation}: " . ($result['text'] ?? 'Unknown error'));
            case self::KEA_UNSUPPORTED:
                throw new Exception("Operation {$operation} not supported by KEA");
            case self::KEA_EMPTY:
                error_log("KEA operation {$operation} completed but no data affected");
                return $result;
            default:
                throw new Exception("Unknown KEA result code: {$resultCode}");
        }
    }

    private function backupKeaConfig(string $operation): void
    {
        try {
            error_log("DHCPv6OptionsModel: Creating config backup before operation: {$operation}");
            
            // Ensure session is started
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            
            // Get current config
            $response = $this->sendKeaCommand("config-get");
            $result = $this->validateKeaResponse($response, 'get config');
            
            // Get database connection
            $db = \App\Database\Database::getInstance();
            
            // Get the first active server ID
            $stmt = $db->query("SELECT id FROM kea_servers WHERE is_active = 1 LIMIT 1");
            $server = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$server) {
                error_log("DHCPv6OptionsModel: No active Kea server found, skipping backup");
                return;
            }
            
            // Store backup
            $stmt = $db->prepare("
                INSERT INTO kea_config_backups (server_id, config_json, created_by, operation)
                VALUES (?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $server['id'],
                json_encode($result['arguments']),
                $_SESSION['user_name'] ?? 'system',
                $operation
            ]);
            
            // Clean up old backups - keep only last 12
            $deleteStmt = $db->prepare("
                DELETE FROM kea_config_backups
                WHERE server_id = ?
                AND id NOT IN (
                    SELECT id FROM (
                        SELECT id FROM kea_config_backups
                        WHERE server_id = ?
                        ORDER BY created_at DESC
                        LIMIT 12
                    ) tmp
                )
            ");
            $deleteStmt->execute([$server['id'], $server['id']]);
            
            error_log("DHCPv6OptionsModel: Config backup created successfully");
        } catch (\Exception $e) {
            error_log("DHCPv6OptionsModel: Failed to create backup: " . $e->getMessage());
            // Don't throw - backup failure shouldn't block the operation
        }
    }

    public function createEditOption($data)
    {
        try {
        // 1. Backup current config to database
        $this->backupKeaConfig('dhcp-option-create');
        // 1. Backup current config to database
        $this->backupKeaConfig('dhcp-option-create');
        
        // 2. Get current RPD class configuration using class-get
        $response = $this->sendKeaCommand("class-get", [
            "name" => "RPD"
        ]);
        
        $result = $this->validateKeaResponse($response, 'get class');
        
        // Extract current RPD class configuration
        $rpdClass = $result['arguments'] ?? null;
        
        if (!$rpdClass) {
            throw new Exception("RPD client class not found");
        }
        
        // Ensure the name field is set (required for class-update)
        $rpdClass['name'] = 'RPD';
        
        // Initialize option-data array if it doesn't exist
        if (!isset($rpdClass['option-data'])) {
            $rpdClass['option-data'] = [];
        }
        
        // Prepare new option
        $newOption = [
            "always-send" => true,
            "csv-format" => true,
            "code" => intval($data['code']),
            "space" => $data['space'],
            "data" => $data['data']
        ];
        
        // Remove existing option with same code and space if exists
        $rpdClass['option-data'] = array_filter($rpdClass['option-data'], function($opt) use ($data) {
            return !($opt['code'] == intval($data['code']) && $opt['space'] == $data['space']);
        });
        
        // Add new option
        $rpdClass['option-data'][] = $newOption;
        
        // Re-index array
        $rpdClass['option-data'] = array_values($rpdClass['option-data']);
        
        // Update the class using class-update
        // class-update expects a 'client-classes' list as top-level argument
        $updateResponse = $this->sendKeaCommand("class-update", [
            "client-classes" => [$rpdClass]
        ]);
        
        error_log("DHCPv6OptionsModel: class-update response: " . $updateResponse);
        $updateResult = $this->validateKeaResponse($updateResponse, 'update class');
        error_log("DHCPv6OptionsModel: class-update validated successfully");
        
        // 3. Write config to disk to persist changes
        $writeResponse = $this->sendKeaCommand("config-write", (object)[]);
        error_log("DHCPv6OptionsModel: config-write response: " . $writeResponse);
        $this->validateKeaResponse($writeResponse, 'write config');
        
        error_log("DHCPv6OptionsModel: Option added to RPD class and config written to disk");
        
        return $updateResult;
        
        } catch (\Exception $e) {
            error_log("DHCPv6OptionsModel: Error in createEditOption: " . $e->getMessage());
            throw $e;
        }
    }


    public function updateOption(array $optionData): array
    {
        // Same as create - it will replace if exists
        return $this->createEditOption($optionData);
    }

    public function deleteOption(array $data): array
    {
        try {
        // 1. Backup current config to database
        $this->backupKeaConfig('dhcp-option-delete');
        
        // 2. Get current RPD class configuration
        $response = $this->sendKeaCommand("class-get", [
            "name" => "RPD"
        ]);
        
        $result = $this->validateKeaResponse($response, 'get class');
        $rpdClass = $result['arguments'] ?? null;
        
        if (!$rpdClass) {
            throw new Exception("RPD client class not found");
        }
        
        // Ensure the name field is set (required for class-update)
        $rpdClass['name'] = 'RPD';
        
        // Remove option with matching code and space
        if (isset($rpdClass['option-data'])) {
            $rpdClass['option-data'] = array_filter($rpdClass['option-data'], function($opt) use ($data) {
                return !($opt['code'] == intval($data['code']) && $opt['space'] == $data['space']);
            });
            
            // Re-index array
            $rpdClass['option-data'] = array_values($rpdClass['option-data']);
        }
        
        // Update the class using class-update
        // class-update expects a 'client-classes' list as top-level argument
        $updateResponse = $this->sendKeaCommand("class-update", [
            "client-classes" => [$rpdClass]
        ]);
        
        $updateResult = $this->validateKeaResponse($updateResponse, 'update class');
        
        // 3. Write config to disk to persist changes
        $writeResponse = $this->sendKeaCommand("config-write", (object)[]);
        $this->validateKeaResponse($writeResponse, 'write config');
        
        error_log("DHCPv6OptionsModel: Option removed from RPD class and config written to disk");
        
        return ['code' => $data['code']];
        
        } catch (\Exception $e) {
            error_log("DHCPv6OptionsModel: Error in deleteOption: " . $e->getMessage());
            throw $e;
        }
    }

    public function getOptions()
    {
        try {
            // Get RPD class configuration
            $response = $this->sendKeaCommand("class-get", [
                "name" => "RPD"
            ]);
            
            $result = $this->validateKeaResponse($response, 'get class');
            
            if (!isset($result['arguments'])) {
                return '[]';
            }
            
            $rpdClass = $result['arguments'] ?? null;
            
            if (!$rpdClass || !isset($rpdClass['option-data'])) {
                return '[]';
            }
            
            // Return options in the expected format
            return json_encode([
                [
                    'result' => 0,
                    'arguments' => [
                        'options' => $rpdClass['option-data']
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            error_log("DHCPv6Options: Failed to get options: " . $e->getMessage());
            return '[]';
        }
    }

    public function addStaticLease($subnetId, $duid, $ipv6, $options = [])
    {
        try {
            $command = [
                'command' => 'reservation-add',
                'service' => ['dhcp6'],
                'arguments' => [
                    'remote' => ['type' => 'mysql'],
                    'reservation' => [
                        'subnet-id' => intval($subnetId),
                        'duid' => $duid,
                        'ip-addresses' => [$ipv6],
                        'option-data' => []
                    ]
                ]
            ];

            // Only add options if they exist
            if (!empty($options)) {
                foreach ($options as $option) {
                    $command['arguments']['reservation']['option-data'][] = [
                        'name' => $option['name'],
                        'code' => intval($option['code']),
                        'space' => 'vendor-4491',
                        'csv-format' => true,
                        'data' => $option['value'],
                        'always-send' => true
                    ];
                }
            }

            // Send command to Kea
            $result = $this->sendKeaCommand($command);

            if (isset($result['result']) && $result['result'] == 0) {
                return ['success' => true];
            } else {
                return [
                    'success' => false,
                    'message' => $result['text'] ?? 'Unknown error occurred'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

}
