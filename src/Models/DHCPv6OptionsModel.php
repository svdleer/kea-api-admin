<?php
namespace App\Models;

use Exception;
use PDO;

class DHCPv6OptionsModel extends KeaModel
{
    private const KEA_SUCCESS = 0;
    private const KEA_ERROR = 1;
    private const KEA_UNSUPPORTED = 2;
    private const KEA_EMPTY = 3;
    private const CLASS_NAME = 'RPD'; // The client class name for cable modems


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

    /**
     * Backup current RPD class configuration before making changes
     */
    private function backupCurrentConfig(string $operation): void
    {
        try {
            // Get current RPD class configuration
            $classGetResponse = $this->sendKeaCommand("class-get", [
                "name" => self::CLASS_NAME
            ]);
            
            $classData = json_decode($classGetResponse, true);
            
            if ($classData[0]['result'] === self::KEA_SUCCESS) {
                // Save to database
                $username = $_SESSION['username'] ?? 'system';
                $stmt = $this->db->prepare(
                    "INSERT INTO kea_config_backups (config_json, created_by, operation) 
                     VALUES (?, ?, ?)"
                );
                $stmt->execute([
                    json_encode($classData[0]['arguments']['client-classes'][0]),
                    $username,
                    $operation
                ]);
            }
        } catch (Exception $e) {
            error_log("Failed to backup RPD class config: " . $e->getMessage());
            // Don't fail the operation if backup fails
        }
    }

    /**
     * Save configuration to disk after successful update
     */
    private function saveConfigToDisk(): void
    {
        try {
            $this->sendKeaCommand("config-write", [
                "filename" => "/etc/kea/kea-dhcp6.conf"
            ]);
        } catch (Exception $e) {
            error_log("Failed to write config to disk: " . $e->getMessage());
            // Don't fail the operation if config write fails
        }
    }

    /**
     * Get current RPD class with all options
     */
    private function getRPDClass(): array
    {
        $response = $this->sendKeaCommand("class-get", [
            "name" => self::CLASS_NAME
        ]);
        
        $result = $this->validateKeaResponse($response, 'get RPD class');
        
        if (!isset($result['arguments']['client-classes'][0])) {
            throw new Exception("RPD class not found in Kea configuration");
        }
        
        return $result['arguments']['client-classes'][0];
    }

    /**
     * Create or update an option in the RPD class
     */
    public function createEditOption($data)
    {
        // Backup current config
        $this->backupCurrentConfig('option_update');

        // Get current RPD class
        $rpdClass = $this->getRPDClass();
        
        // Initialize option-data array if it doesn't exist
        if (!isset($rpdClass['option-data'])) {
            $rpdClass['option-data'] = [];
        }
        
        // Find if option already exists
        $optionIndex = null;
        foreach ($rpdClass['option-data'] as $index => $option) {
            if ($option['code'] == $data['code'] && $option['space'] == $data['space']) {
                $optionIndex = $index;
                break;
            }
        }
        
        // Build the new option
        $newOption = [
            "always-send" => true,
            "csv-format" => true,
            "code" => intval($data['code']),
            "space" => $data['space'],
            "data" => $data['data']
        ];
        
        // Update or add option
        if ($optionIndex !== null) {
            $rpdClass['option-data'][$optionIndex] = $newOption;
        } else {
            $rpdClass['option-data'][] = $newOption;
        }
        
        // Update the class using class-update
        $keaRequest = [
            "client-classes" => [$rpdClass]
        ];

        error_log("Sending class-update request: " . json_encode($keaRequest));
        $response = $this->sendKeaCommand("class-update", $keaRequest);
        error_log("Received class-update response: " . json_encode($response));
        
        $result = $this->validateKeaResponse($response, 'update RPD class option');
        
        // Save config to disk
        $this->saveConfigToDisk();
        
        return $result;
    }

    /**
     * Delete an option from the RPD class
     */
    public function deleteOption(array $data): array
    {
        // Backup current config
        $this->backupCurrentConfig('option_delete');

        // Get current RPD class
        $rpdClass = $this->getRPDClass();
        
        // Find and remove the option
        if (!isset($rpdClass['option-data'])) {
            throw new Exception("No options found in RPD class");
        }
        
        $found = false;
        $newOptionData = [];
        foreach ($rpdClass['option-data'] as $option) {
            if ($option['code'] == $data['code'] && $option['space'] == $data['space']) {
                $found = true;
                continue; // Skip this option (delete it)
            }
            $newOptionData[] = $option;
        }
        
        if (!$found) {
            throw new Exception("Option not found in RPD class");
        }
        
        // Update the option-data
        $rpdClass['option-data'] = $newOptionData;
        
        // Update the class using class-update
        $keaRequest = [
            "client-classes" => [$rpdClass]
        ];

        error_log("Sending class-update for delete: " . json_encode($keaRequest));
        $response = $this->sendKeaCommand("class-update", $keaRequest);
        
        $result = $this->validateKeaResponse($response, 'delete RPD class option');
        
        // Save config to disk
        $this->saveConfigToDisk();
        
        return ['code' => $data['code']];
    }

    /**
     * Get all options from the RPD class
     */
    public function getOptions()
    {
        try {
            $rpdClass = $this->getRPDClass();
            
            // Build response in the format expected by the controller
            $response = [
                [
                    'result' => self::KEA_SUCCESS,
                    'arguments' => [
                        'options' => $rpdClass['option-data'] ?? []
                    ]
                ]
            ];
            
            return json_encode($response);
        } catch (Exception $e) {
            // Return empty array if class doesn't exist or has no options
            error_log("Error getting RPD class options: " . $e->getMessage());
            return json_encode([
                [
                    'result' => self::KEA_EMPTY,
                    'arguments' => [
                        'options' => []
                    ]
                ]
            ]);
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
