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

    public function createEditOptionDef(array $optionData): array
    {
        // To properly update an option definition, we need to delete it first, then recreate it
        // This is because option-def6-set doesn't reliably update existing definitions
        
        // Try to delete existing definition first (ignore errors if it doesn't exist)
        $deleteArgs = [
            "remote" => ["type" => "mysql"],
            "server-tags" => ["all"],
            'option-defs' => [
                [
                    'code' => intval($optionData['code']),
                    'space' => $optionData['space']
                ]
            ]
        ];
        
        try {
            $this->sendKeaCommand("option-def6-del", $deleteArgs);
        } catch (\Exception $e) {
            // Ignore error - definition might not exist yet
            error_log("Delete option def (expected for create): " . $e->getMessage());
        }
        
        // Now create the new definition
        $createOptionsDefArguments = [
            "remote" => ["type" => "mysql"],
            "server-tags" => ["all"],
            'option-defs' => [
                [
                    'code' => intval($optionData['code']),
                    'name' => $optionData['name'],
                    'space' => $optionData['space'],
                    'type' => $optionData['type'],
                    'array' => isset($optionData['array']) ? (bool)$optionData['array'] : false,
                ]
            ]
        ];

        $response = $this->sendKeaCommand("option-def6-set", $createOptionsDefArguments);
        
        $result = $this->validateKeaResponse($response, 'create option');
        return $optionData;
    }

    public function updateOptionDef(array $optionData): array
    {
        $updateOptionsDefArguments = [
            "remote" => ["type" => "mysql"],
            "server-tags" => ["all"],
            'option-defs' => [
                [
                    'code' => intval($optionData['code']),
                    'name' => $optionData['name'],
                    'space' => $optionData['space'],
                    'type' => $optionData['type'],
                    'array' => isset($optionData['array']) ? (bool)$optionData['array'] : false,
                ]
            ]
        ];

        $response = $this->sendKeaCommand("option-def6-set", $updateOptionsDefArguments);
        
        $result = $this->validateKeaResponse($response, 'update option');
        return $optionData;
    }

    public function deleteOptionDef(array $data): array
    {
        $deleteOptionsDefArguments = [
            "remote" => ["type" => "mysql"],
            "server-tags" => ["all"],
            'option-defs' => [
                [
                    'code' => $data['code'],
                    'space' => $data['space']
                ]
            ]
        ];
        $response = $this->sendKeaCommand("option-def6-del", $deleteOptionsDefArguments);
        
        $result = $this->validateKeaResponse($response, 'delete option');
        return ['code' => $data['code']];  // Return the code from the input data
    }

    

    public function getOptionsDef()
    {
        $getOptionsDefsArguments = [
            "remote" => ["type" => "mysql"],
            "server-tags" => ["all"]
        ];

        $response = $this->sendKeaCommand("option-def6-get-all", $getOptionsDefsArguments);
        
        $result = $this->validateKeaResponse($response, 'get options');
        
        if ($result['result'] === self::KEA_EMPTY) {
            return '[]';  // Return empty JSON array as string
        }
        
        return $response;  // Return original response string since it's already JSON
    }
}
