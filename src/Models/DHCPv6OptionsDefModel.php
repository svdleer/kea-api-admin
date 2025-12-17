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

    private function setConfig(array $config): void
    {
        // Remove hash parameter if present as it's not supported in config-set
        unset($config['hash']);
        
        $response = $this->sendKeaCommand("config-set", $config);
        $this->validateKeaResponse($response, 'set config');
    }

    public function createEditOptionDef(array $optionData): array
    {
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
