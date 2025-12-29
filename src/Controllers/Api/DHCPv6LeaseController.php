<?php

namespace App\Controllers\Api;

use App\Models\DHCPv6LeaseModel;
use Exception;

class DHCPv6LeaseController
{
    private DHCPv6LeaseModel $leaseModel;

    public function __construct()
    {
        try {
            $this->leaseModel = new DHCPv6LeaseModel();
        } catch (\Exception $e) {
            error_log("Failed to initialize DHCPv6LeaseModel: " . $e->getMessage());
            // Don't throw here, handle in getLeases
        }
    }


    public function getLeases(string $subnetId, string $from, string $limit)
    {   
        // Ensure no output before headers
        ob_start();
        
        try {
            // Check if model was initialized
            if (!isset($this->leaseModel)) {
                throw new Exception('DHCPv6 Lease service is not available. Please configure Kea servers in Admin → Kea Servers.');
            }
            
            // Log incoming parameters
            error_log("getLeases called with subnetId: $subnetId, from: $from, limit: $limit");

            // Validate and cast parameters
            if (!is_numeric($limit) || (int)$limit <= 0) {
                throw new Exception('Limit must be a positive integer');
            }
            if (!is_numeric($subnetId) || (int)$subnetId <= 0) {
                throw new Exception('Subnet ID must be a positive integer');
            }
            
            $limit = (int)$limit;
            $subnetId = (int)$subnetId;

            // Call Kea API directly - no database queries
            error_log("Calling leaseModel->getLeasesBySubnet with subnetId: $subnetId");
            
            $result = $this->leaseModel->getLeasesBySubnet($from, $limit, $subnetId);
            
            // Log the result
            error_log("Database query result:");
            error_log("Result type: " . gettype($result));
            error_log("Result content: " . json_encode($result));

            // Clear any accidental output
            ob_clean();
            
            header('Content-Type: application/json');
            
            $response = [
                'success' => true,
                'data' => $result,
                'debug' => [
                    'params' => [
                        'subnetId' => $subnetId,
                        'from' => $from,
                        'limit' => $limit
                    ],
                    'resultCount' => is_array($result) ? count($result) : 0
                ]
            ];

            error_log("Sending response: " . json_encode($response));
            echo json_encode($response);
            ob_end_flush();

        } catch (Exception $e) {
            error_log("==================== LEASE ERROR ====================");
            error_log("Error in getLeases: " . $e->getMessage());
            error_log("Error file: " . $e->getFile() . " line " . $e->getLine());
            error_log("Error trace: " . $e->getTraceAsString());
            error_log("====================================================");

            // Clear any accidental output
            ob_clean();
            
            header('Content-Type: application/json');
            http_response_code(400);
            
            $errorResponse = [
                'success' => false,
                'message' => $e->getMessage(),
                'error_details' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => explode("\n", $e->getTraceAsString())
                ],
                'debug' => [
                    'params' => [
                        'subnetId' => $subnetId,
                        'from' => $from,
                        'limit' => $limit
                    ]
                ]
            ];

            error_log("Sending error response: " . json_encode($errorResponse));
            echo json_encode($errorResponse);
            ob_end_flush();
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

    public function addStaticLease()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            

            if (!isset($data['hwAddress']) || !isset($data['ipAddress']) || !isset($data['subnetId'])) {
                $result = [
                    'result' => 1,
                    'message' => 'Missing required fields: hwAddress, ipAddress, and subnetId are required'
                ];
                header('Content-Type: application/json');
                echo json_encode($result);
                return;
            }

            // Options are optional now
            $options = isset($data['options']) ? $data['options'] : [];

            error_log("About to call addStaticLease with parameters:");
            error_log("IP Address: " . $data['ipAddress']);
            error_log("MAC Address: " . $data['hwAddress']);
            error_log("Subnet ID: " . $data['subnetId']);
            
            $keaResponse = $this->leaseModel->addStaticLease(
                $data['ipAddress'],
                $data['hwAddress'], 
                $data['subnetId'], 
                $options
            );
            
            header('Content-Type: application/json');
            echo json_encode($keaResponse);
            return;
    
        } catch (Exception $e) {
            error_log("Error in addStaticLease: " . $e->getMessage());
            $result = [
                'result' => 1,
                'message' => 'Error adding static lease: ' . $e->getMessage()
            ];
            header('Content-Type: application/json');
            echo json_encode($result);
            return;
        }
    }
    
    public function getStaticLeases(string $subnetId)
    {   
        try {
            // Log incoming parameter
    
            // Validate subnet ID
            if (!is_numeric($subnetId)) {
                throw new Exception('Invalid subnet ID format');
            }
    
            // Get static leases from model
            $keaResponse = $this->leaseModel->getStaticLeases($subnetId);
            
            header('Content-Type: application/json');
            echo json_encode($keaResponse);
            
        } catch (Exception $e) {
            error_log("Error in getStaticLeases: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            header('Content-Type: application/json');
            http_response_code(400);
            
            $errorResponse = [
                'success' => false,
                'message' => $e->getMessage()
            ];
            
            echo json_encode($errorResponse);
        }
    }
    
    public function updateReservation()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['hwAddress']) || !isset($data['ipAddress']) || !isset($data['subnetId'])) {
                $result = [
                    'result' => 1,
                    'message' => 'Missing required fields: hwAddress, ipAddress, and subnetId are required'
                ];
                header('Content-Type: application/json');
                echo json_encode($result);
                return;
            }

            $options = isset($data['options']) ? $data['options'] : [];
            $hostname = isset($data['hostname']) ? $data['hostname'] : null;

            error_log("About to update reservation with parameters:");
            error_log("IP Address: " . $data['ipAddress']);
            error_log("MAC Address: " . $data['hwAddress']);
            error_log("Subnet ID: " . $data['subnetId']);
            error_log("Hostname: " . ($hostname ?? 'NONE'));
            
            $keaResponse = $this->leaseModel->updateReservation(
                $data['ipAddress'],
                $data['hwAddress'], 
                $data['subnetId'], 
                $options,
                $hostname
            );
            
            header('Content-Type: application/json');
            echo json_encode($keaResponse);
            return;
    
        } catch (Exception $e) {
            error_log("Error in updateReservation: " . $e->getMessage());
            $result = [
                'result' => 1,
                'message' => 'Error updating reservation: ' . $e->getMessage()
            ];
            header('Content-Type: application/json');
            echo json_encode($result);
            return;
        }
    }
    
    public function deleteReservation()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            $ipAddress = $data['ip-address'] ?? null;
            $subnetId = $data['subnet-id'] ?? null;
            
            if (!$ipAddress || !$subnetId) {
                throw new Exception('Missing required parameters: ip-address and subnet-id');
            }
            
            error_log("Deleting reservation for IP: " . $ipAddress . " in subnet: " . $subnetId);
            
            // Delete the reservation
            $result = $this->leaseModel->deleteReservation($ipAddress, $subnetId);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Reservation deleted successfully',
                'result' => $result
            ]);
            
        } catch (Exception $e) {
            error_log("Error in deleteReservation: " . $e->getMessage());
            
            header('Content-Type: application/json');
            http_response_code(400);
            
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    
    
}
