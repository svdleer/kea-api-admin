<?php

namespace App\Controllers\Api;

use App\Models\DHCPv6OptionsModel;
use App\Models\DHCPv6OptionsDefModel;
use App\Auth\Authentication;
use Exception;

class DHCPv6OptionsController extends KeaController
{
    protected DHCPv6OptionsModel $optionsModel;
    protected DHCPv6OptionsDefModel $optionsDefModel;
    protected Authentication $auth;

    public function __construct(DHCPv6OptionsModel $optionsModel, DHCPv6OptionsDefModel $optionsDefModel, Authentication $auth)
    {
        $this->optionsModel = $optionsModel;
        $this->auth = $auth;
        $this->optionsDefModel = $optionsDefModel;

    }


    
    public function list()
{
    try {
        $options = $this->optionsModel->getOptions();
        $optionsDef = $this->optionsDefModel->getOptionsDef();
        
        // Decode the JSON strings
        $options = json_decode($options, true);
        $optionsDef = json_decode($optionsDef, true);
        
        error_log("Decoded options: " . json_encode($options));
        error_log("Decoded optionsDef: " . json_encode($optionsDef));
        
        header('Content-Type: application/json');
        
        // Extract options from KEA response
        $optionsArray = [];
        if (is_array($options) && !empty($options[0]['arguments']['options'])) {
            $optionsArray = $options[0]['arguments']['options'];
        }
        
        // Extract option definitions from KEA response
        $optionsDefArray = [];
        if (is_array($optionsDef) && !empty($optionsDef[0]['arguments']['option-defs'])) {
            $optionsDefArray = $optionsDef[0]['arguments']['option-defs'];
        }
        
        error_log("Processed optionsArray: " . json_encode($optionsArray));
        error_log("Processed optionsDefArray: " . json_encode($optionsDefArray));
        
        // Combine options with their definitions
        $combinedData = [];
        foreach ($optionsDefArray as $def) {
            $option = $this->findOptionByCode($optionsArray, $def['code']);
            $combinedData[] = [
                'definition' => $def,
                'option' => $option
            ];
        }
        
        error_log("Final combinedData: " . json_encode($combinedData));
        
        $response = [
            'success' => true,
            'data' => $combinedData
        ];
        
        error_log("Final response: " . json_encode($response));
        echo json_encode($response);
        
    } catch (Exception $e) {
        error_log("DHCPv6Options error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}


    
    private function findOptionByCode($options, $code) {
        if (empty($options)) return null;
        
        // No need to json_decode here since we already have an array
        foreach ($options as $option) {
            if ($option['code'] == $code) {
                return $option;
            }
        }
        return null;
    }
    
    



    /**
     * Create a new DHCPv6 option
     */
    public function create()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['code']) || !isset($data['space']) || !isset($data['data'])) {
                throw new Exception('Missing required fields (code, space, or data)');
            }

            $result = $this->optionsModel->createEditOption($data);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }




    
     
    
    /**
     * Update an existing DHCPv6 option
     */
    public function update(string $code)
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$code) {
                throw new Exception('Option code is required');
            }
    
            $result = $this->optionsModel->createEditOption($data);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    public function delete(string $code)
    {   
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            error_log("Delete request data: " . json_encode($data));
            
            if (!$code) {
                throw new Exception('Option code is required');
            }
    
            if (!isset($data['space'])) {
                throw new Exception('Option space is required');
            }
    
            // Create the array structure that the model expects
            $deleteData = [
                'code' => (int)$code,
                'space' => $data['space']
            ];
    
            $result = $this->optionsModel->deleteOption($deleteData);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    
    
    
           
          
}
