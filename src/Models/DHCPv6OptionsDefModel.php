<?php
namespace App\Models;

use Exception;

class DHCPv6OptionsDefModel extends KeaModel
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

    private function getCurrentConfig(): array
    {
        $response = $this->sendKeaCommand("config-get");
        $result = $this->validateKeaResponse($response, 'get config');
        
        if (!isset($result['arguments']['Dhcp6'])) {
            throw new Exception("Invalid config format - missing Dhcp6 section");
        }
        
        return $result['arguments']['Dhcp6'];
    }

    private function backupKeaConfig(string $operation): void
    {
        try {
            error_log("DHCPv6OptionsDefModel: Creating config backup before operation: {$operation}");
            
            // Get current config
            $response = $this->sendKeaCommand("config-get");
            $result = $this->validateKeaResponse($response, 'get config');
            
            // Get database connection
            $db = \App\Database\Database::getInstance();
            
            // Get the first active server ID
            $stmt = $db->query("SELECT id FROM kea_servers WHERE is_active = 1 LIMIT 1");
            $server = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$server) {
                error_log("DHCPv6OptionsDefModel: No active Kea server found, skipping backup");
                return;
            }
            
            // Store backup
            $stmt = $db->prepare("
                INSERT INTO kea_config_backups (server_id, config_json, created_by, operation)
                VALUES (:server_id, :config_json, :created_by, :operation)
            ");
            
            $stmt->execute([
                ':server_id' => $server['id'],
                ':config_json' => json_encode($result['arguments']),
                ':created_by' => $_SESSION['username'] ?? 'system',
                ':operation' => $operation
            ]);
            
            // Clean up old backups - keep only last 12
            $db->prepare("
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
            ")->execute([':server_id' => $server['id']]);
            
            error_log("DHCPv6OptionsDefModel: Config backup created successfully");
        } catch (\Exception $e) {
            error_log("DHCPv6OptionsDefModel: Failed to create backup: " . $e->getMessage());
            // Don't throw - backup failure shouldn't block the operation
        }
    }

    private function setConfig(array $config): void
    {
        // Remove hash parameter if present as it's not supported in config-set
        unset($config['hash']);
        
        // Wrap config in Dhcp6 key as required by config-set
        $configWrapper = ['Dhcp6' => $config];
        
        $response = $this->sendKeaCommand("config-set", $configWrapper);
        $this->validateKeaResponse($response, 'set config');
        
        // Write config to disk to persist changes across restarts
        // config-write takes an optional filename argument, pass empty object if no filename
        $writeResponse = $this->sendKeaCommand("config-write", (object)[]);
        $this->validateKeaResponse($writeResponse, 'write config');
        
        error_log("DHCPv6OptionsDefModel: Config written to disk successfully");
    }

    public function createEditOptionDef(array $optionData): array
    {
        // Backup config before making changes
        $this->backupKeaConfig('option-def-create');
        
        // Get current config
        $config = $this->getCurrentConfig();
        
        // Initialize option-def array if it doesn't exist
        if (!isset($config['option-def'])) {
            $config['option-def'] = [];
        }
        
        // Build the new option definition
        $newOptionDef = [
            'code' => intval($optionData['code']),
            'name' => $optionData['name'],
            'space' => $optionData['space'],
            'type' => $optionData['type'],
            'array' => isset($optionData['array']) ? (bool)$optionData['array'] : false,
        ];
        
        // Remove existing option with same code and space
        $config['option-def'] = array_filter($config['option-def'], function($opt) use ($optionData) {
            return !($opt['code'] == intval($optionData['code']) && $opt['space'] == $optionData['space']);
        });
        
        // Add the new option definition
        $config['option-def'][] = $newOptionDef;
        
        // Re-index array
        $config['option-def'] = array_values($config['option-def']);
        
        // Set the config
        $this->setConfig($config);
        
        return $optionData;
    }

    public function updateOptionDef(array $optionData): array
    {
        // Same as create - we use config-get/config-set approach
        return $this->createEditOptionDef($optionData);
    }

    public function deleteOptionDef(array $data): array
    {
        // Backup config before making changes
        $this->backupKeaConfig('option-def-delete');
        
        // Get current config
        $config = $this->getCurrentConfig();
        
        if (!isset($config['option-def'])) {
            throw new Exception("No option definitions found in config");
        }
        
        // Remove the option definition
        $config['option-def'] = array_filter($config['option-def'], function($opt) use ($data) {
            return !($opt['code'] == $data['code'] && $opt['space'] == $data['space']);
        });
        
        // Re-index array
        $config['option-def'] = array_values($config['option-def']);
        
        // Set the config
        $this->setConfig($config);
        
        return ['code' => $data['code']];
    }

    public function getOptionsDef()
    {
        try {
            $config = $this->getCurrentConfig();
            
            if (!isset($config['option-def']) || empty($config['option-def'])) {
                return '[]';
            }
            
            // Return the option-def array as JSON
            return json_encode([
                [
                    'result' => 0,
                    'arguments' => [
                        'option-defs' => $config['option-def']
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            error_log("DHCPv6Options: Failed to get option definitions: " . $e->getMessage());
            return '[]';
        }
    }
}
