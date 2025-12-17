<?php

namespace App\Controllers\Api;

use App\Models\DHCPv6OptionsDefModel;
use App\Auth\Authentication;
use Exception;

class DHCPv6OptionsDefController extends KeaController
{
    protected DHCPv6OptionsDefModel $optionsDefModel;
    protected Authentication $auth;

    public function __construct(DHCPv6OptionsDefModel $optionsDefModel, Authentication $auth)
    {
        $this->optionsDefModel = $optionsDefModel;
        $this->auth = $auth;
    }

    public function list()
    {
        try {
            $result = $this->optionsDefModel->getOptionsDef();
            
            header('Content-Type: application/json');
            
            if (empty($result)) {
                echo json_encode([]);
                return;
            }
            
            echo $result;
            
        } catch (Exception $e) {
            error_log("DHCPv6Options error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Create a new DHCPv6 option
     */
    public function create()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['code']) || !isset($data['name']) || !isset($data['type'])) {
                throw new Exception('Missing required fields');
            }

            // Create the option definition
            $result = $this->optionsDefModel->createEditOptionDef($data);
            
            // Also create an empty option so it exists in config
            $optionsModel = new \App\Models\DHCPv6OptionsModel();
            $optionData = [
                'code' => $data['code'],
                'space' => $data['space'] ?? 'dhcp6',
                'data' => '',
                'always_send' => false
            ];
            
            try {
                $optionsModel->createEditOption($optionData);
            } catch (Exception $optionError) {
                // Log but don't fail if option creation fails
                error_log("Failed to create empty option for def: " . $optionError->getMessage());
            }
            
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
    
            $result = $this->optionsDefModel->createEditOptionDef($data);
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
            error_log("Delete request - code parameter: " . var_export($code, true));
            error_log("Delete request - body data: " . json_encode($data));
            
            if (empty($code) || $code === '0') {
                throw new Exception('Option code is required and cannot be empty');
            }
    
            if (!isset($data['space']) || empty($data['space'])) {
                throw new Exception('Option space is required and cannot be empty');
            }
    
            // Create the array structure that the model expects
            $deleteData = [
                'code' => (int)$code,
                'space' => $data['space']
            ];
    
            error_log("Calling deleteOptionDef with: " . json_encode($deleteData));
            $result = $this->optionsDefModel->deleteOptionDef($deleteData);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
        } catch (Exception $e) {
            error_log("Delete error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }
    
    
    
    
           
          
}
