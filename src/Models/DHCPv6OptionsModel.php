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

    public function createEditOption($data)
    {
        $keaRequest = [
            "remote" => ["type" => "mysql"],
            "server-tags" => ["all"],
            "options" => [
                    [
                        "always-send" => true,
                        "csv-format" => true,
                        "code" => intval($data['code']),
                        "space" => $data['space'],
                        "data" => $data['data']
                    ]
            ],
        
        ];


        error_log("Sending Kea request: " . json_encode($keaRequest));
        $response = $this->sendKeaCommand("remote-option6-global-set", $keaRequest);
        error_log("Received Kea response: " . json_encode($response));
        
        $result = $this->validateKeaResponse($response, 'create option');
        return $result;
    }



    public function updateOption(array $optionData): array
    {
        $updateOptionsArguments = [
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

        $response = $this->sendKeaCommand("remote-option6-global-set", $updateOptionsArguments);
        
        $result = $this->validateKeaResponse($response, 'update option');
        return $optionData;
    }

    public function deleteOption(array $data): array
    {
        $deleteOptionsArguments = [
            "remote" => ["type" => "mysql"],
            "server-tags" => ["all"],
            'options' => [
                [
                    'code' => $data['code'],
                    'space' => $data['space']
                ]
            ]
        ];
        $response = $this->sendKeaCommand("remote-option6-global-del", $deleteOptionsArguments);
        
        $result = $this->validateKeaResponse($response, 'delete option');
        return ['code' => $data['code']];  // Return the code from the input data
    }

    

    public function getOptions()
    {
        $getOptionsArguments = [
            "remote" => ["type" => "mysql"],
            "server-tags" => ["all"]
        ];

        $response = $this->sendKeaCommand("remote-option6-global-get-all", $getOptionsArguments);
        
        $result = $this->validateKeaResponse($response, 'get options');
        
        if ($result['result'] === self::KEA_EMPTY) {
            return '[]';  // Return empty JSON array as string
        }
        
        return $response;  // Return original response string since it's already JSON
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
