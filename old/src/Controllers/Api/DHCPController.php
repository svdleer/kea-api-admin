<?php

namespace App\Controllers\Api;

use App\Models\DHCP;
use App\Auth\Authentication;
use App\Database\Database;



class DHCPController
{
    protected DHCP $subnetModel;
    protected Authentication $auth;

    public function __construct(DHCP $subnetModel, Authentication $auth)
    {
        $this->subnetModel = $subnetModel;
        $this->auth = $auth;
    }


    private function validateSubnetData($data)
    {
        $errors = [];
        error_log("DHCPController: Validating subnet data: " . json_encode($data));
        // Convert JavaScript regex to PHP format
        $ipv6Regex = '/^(?:(?:[0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}|(?:[0-9a-fA-F]{1,4}:){1,7}:|(?:[0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|(?:[0-9a-fA-F]{1,4}:){1,5}(?::[0-9a-fA-F]{1,4}){1,2}|(?:[0-9a-fA-F]{1,4}:){1,4}(?::[0-9a-fA-F]{1,4}){1,3}|(?:[0-9a-fA-F]{1,4}:){1,3}(?::[0-9a-fA-F]{1,4}){1,4}|(?:[0-9a-fA-F]{1,4}:){1,2}(?::[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:(?:(?::[0-9a-fA-F]{1,4}){1,6})|:(?:(?::[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(?::[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(?:ffff(?::0{1,4}){0,1}:){0,1}(?:(?:25[0-5]|(?:2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(?:25[0-5]|(?:2[0-4]|1{0,1}[0-9]){0,1}[0-9])|(?:[0-9a-fA-F]{1,4}:){1,4}:(?:(?:25[0-5]|(?:2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(?:25[0-5]|(?:2[0-4]|1{0,1}[0-9]){0,1}[0-9]))$/';
    
        // Check if required fields exist
        $requiredFields = [
            'bvi_interface_id',
            'subnet',
            'pool_start',
            'pool_end',
            'ccap_core_address',
            'relay_address'
        ];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || rtrim($data[$field]) === '')  {
                $errors[] = "Field '$field' is required";
            }
        }
    
        // Validate BVI interface ID is numeric
        if (isset($data['bvi_interface_id']) && !is_numeric($data['bvi_interface_id'])) {
            $errors[] = "BVI interface ID must be numeric";
        }
    
        // Validate IPv6 subnet format (must include prefix length)
        if (isset($data['subnet'])) {
            if (!preg_match('/^([0-9a-fA-F:]+)\/(\d+)$/', $data['subnet'], $matches)) {
                $errors[] = "Invalid IPv6 subnet format. Must include prefix length (e.g., 2001:db8::/64)";
            } else {
                $prefix = $matches[2];
                if ($prefix < 0 || $prefix > 128) {
                    $errors[] = "Invalid IPv6 prefix length. Must be between 0 and 128";
                }
                // Validate the IPv6 part of the subnet
                $ipv6Part = $matches[1];
                if (!preg_match($ipv6Regex, $ipv6Part)) {
                    $errors[] = "Invalid IPv6 address format in subnet";
                }
            }
        }
    
        // Validate IPv6 addresses using the regex
        $ipv6Fields = ['pool_start', 'pool_end', 'ccap_core_address', 'relay_address'];
        foreach ($ipv6Fields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                if (!preg_match($ipv6Regex, $data[$field])) {
                    $errors[] = "Invalid IPv6 address format for $field";
                }
            }
        }
    
        // If there are any validation errors, return them
        if (!empty($errors)) {
            return ['success' => false, 'details' => $errors];
        }
    
        return true;
    }
    
    public function getAllSubnets()
    {
        error_log("DHCPController: ====== Starting getAllSubnets ======");
        try {
            $subnets = $this->subnetModel->getEnrichedSubnets();
            error_log("DHCPController: Received response from getEnrichedSubnets: " . json_encode($subnets));
            
            // Set proper JSON header
            header('Content-Type: application/json');
            
            // Since the frontend expects a direct array of subnets, we'll return it directly
            echo json_encode($subnets, JSON_PRETTY_PRINT);
    
        } catch (\Exception $e) {
            error_log("DHCPController: ====== ERROR in getAllSubnets ======");
            error_log("DHCPController: Exception message: " . $e->getMessage());
            
            // Set error status code
            http_response_code(500);
            
            // Return error response in JSON format
            header('Content-Type: application/json');
            echo json_encode([
                'error' => $e->getMessage()
            ]);
        }
        error_log("DHCPController: ====== Completed getAllSubnets ======");
    }
    

    


    public function create()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            error_log("Received data: " . print_r($data, true));  // Debug log

            // Validate required fields
            $validation = $this->validateSubnetData($data);
            if ($validation !== true) {
                http_response_code(400);
                echo json_encode([
                    'success' => false, 
                    'error' => 'Validation failed',
                    'details' => $validation
                ]);
                return;
            }

            $result = $this->subnetModel->createSubnet($data);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Subnet created successfully',
                'id' => $result
            ]);

        } catch (\Exception $e) {
            error_log("Error in DHCPController::create: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Internal server error']);
        }
    }


    public function getById($id)
    {
        try {
            $subnet = $this->subnetModel->getSubnetById($id);

            if (!$subnet) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Subnet not found']);
                return;
            }

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'data' => $subnet]);
        } catch (\Exception $e) {
            error_log("Error in DHCPController::getById: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Internal server error']);
        }
    }

    
    public function update()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            error_log("Received data: " . print_r($data, true));  // Debug log

            // Validate required fields
            $validation = $this->validateSubnetData($data);
            if ($validation !== true) {
                http_response_code(400);
                echo json_encode([
                    'success' => false, 
                    'error' => 'Validation failed',
                    'details' => $validation
                ]);
                return;
            }

            $result = $this->subnetModel->updateSubnet($data);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Subnet created successfully',
                'id' => $result
            ]);

        } catch (\Exception $e) {
            error_log("Error in DHCPController::create: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Internal server error']);
        }
    }


    public function deleteLease()
    {
        try {
            // Get the raw DELETE data
            $rawData = file_get_contents("php://input");
            $data = json_decode($rawData, true);

            // Extract parameters
            $ipAddress = $data['ip-address'] ?? null;
            $subnetId = $data['subnet-id'] ?? null;

            // Validate parameters
            if (!$ipAddress || !$subnetId) {
                throw new Exception('Subnet ID and IPv6 address are required');
            }

            // Call the model's deleteLease method
            $result = $this->leaseModel->deleteLease($ipAddress);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
        } catch (Exception $e) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    

    private function getJsonData(): ?array
    {
        $jsonData = file_get_contents('php://input');
        if (!$jsonData) {
            return null;
        }
        return json_decode($jsonData, true);
    }

    

    public function checkDuplicate()
    {
        try {
            $data = $this->getJsonData();
            if (!$data) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'No data provided'
                ]);
                return;
            }

            $subnet = $data['subnet'] ?? null;
            if (!$subnet) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Subnet is required'
                ]);
                return;
            }

            // Check for duplicate subnet
            $stmt = $this->subnetModel->checkDuplicateSubnet($subnet);
            
            echo json_encode([
                'success' => true,
                'exists' => $stmt->rowCount() > 0
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Server error while checking duplicate subnet'
            ]);
        }
    }
}


