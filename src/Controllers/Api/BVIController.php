<?php

namespace App\Controllers\Api;

use App\Models\BVIModel;
use App\Models\DHCP;
use App\Database\Database;

class BVIController
{
    private BVIModel $bviModel;
    private DHCP $dhcpModel;
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->bviModel = new BVIModel($this->db);
        $this->dhcpModel = new DHCP($this->db);
    }

    public function index($switchId)
    {
        try {
            $interfaces = $this->bviModel->getAllBviInterfaces($switchId);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $interfaces
            ]);
        } catch (\Exception $e) {
            error_log("Error in BVIController::index: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Internal server error'
            ]);
        }
    }

    public function show($switchId, $bviId)
    {
        try {
            $interface = $this->bviModel->getBviInterface($switchId, $bviId);
            
            if (!$interface) {
                http_response_code(404);
                echo json_encode(['error' => 'BVI interface not found']);
                return;
            }

            header('Content-Type: application/json');
            echo json_encode($interface);
        } catch (\Exception $e) {
            error_log("Error in BVIController::show: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    public function create($switchId)
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                http_response_code(400);
                echo json_encode(['error' => 'Invalid JSON data']);
                return;
            }

            $result = $this->bviModel->createBviInterface($switchId, $data);
            
            if ($result) {
                http_response_code(201);
                echo json_encode(['success' => true,'message' => 'BVI interface created successfully', 'id' => $result]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Failed to create BVI interface']);
            }
        } catch (\Exception $e) {
            error_log("Error in BVIController::create: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Internal server error']);
        }
    }

    public function update($switchId, $bviId)
    {
        try {
            header('Content-Type: application/json');
            $data = json_decode(file_get_contents('php://input'), true);
            
            error_log("BVIController::update called for switch $switchId, BVI $bviId with data: " . json_encode($data));
            
            if (!$data) {
                echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
                return;
            }
    
            $result = $this->bviModel->updateBviInterface($switchId, $bviId, $data);
            
            if ($result) {
                // If IPv6 address changed, update the relay address in the associated Kea subnet
                if (isset($data['ipv6_address'])) {
                    error_log("IPv6 address provided in update: {$data['ipv6_address']}");
                    try {
                        // Get the associated DHCP subnet
                        $stmt = $this->db->prepare("SELECT kea_subnet_id FROM cin_bvi_dhcp_core WHERE bvi_interface_id = ?");
                        $stmt->execute([$bviId]);
                        $dhcpSubnet = $stmt->fetch(\PDO::FETCH_ASSOC);
                        
                        if ($dhcpSubnet && $dhcpSubnet['kea_subnet_id']) {
                            error_log("Updating relay address for subnet {$dhcpSubnet['kea_subnet_id']} to {$data['ipv6_address']}");
                            
                            // Get full subnet details from Kea
                            $allSubnets = $this->dhcpModel->getAllSubnetsfromKEA();
                            $currentSubnet = null;
                            foreach ($allSubnets as $s) {
                                if ($s['id'] == $dhcpSubnet['kea_subnet_id']) {
                                    $currentSubnet = $s;
                                    break;
                                }
                            }
                            
                            if ($currentSubnet) {
                                // Extract pool info
                                $poolParts = explode('-', $currentSubnet['pools'][0]['pool'] ?? '');
                                $poolStart = trim($poolParts[0] ?? '');
                                $poolEnd = trim($poolParts[1] ?? '');
                                
                                // Get CCAP core from database
                                $ccapCore = null;
                                $stmt = $this->db->prepare("SELECT ccap_core FROM cin_bvi_dhcp_core WHERE kea_subnet_id = ?");
                                $stmt->execute([$dhcpSubnet['kea_subnet_id']]);
                                $ccapRow = $stmt->fetch(\PDO::FETCH_ASSOC);
                                if ($ccapRow) {
                                    $ccapCore = $ccapRow['ccap_core'];
                                }
                                
                                // Update subnet with new relay address
                                $updateData = [
                                    'subnet_id' => $dhcpSubnet['kea_subnet_id'],
                                    'subnet' => $currentSubnet['subnet'],
                                    'pool_start' => $poolStart,
                                    'pool_end' => $poolEnd,
                                    'relay_address' => $data['ipv6_address'],
                                    'ccap_core_address' => $ccapCore,
                                    'valid_lifetime' => $currentSubnet['valid-lifetime'] ?? 7200,
                                    'preferred_lifetime' => $currentSubnet['preferred-lifetime'] ?? 3600,
                                    'renew_timer' => $currentSubnet['renew-timer'] ?? 1000,
                                    'rebind_timer' => $currentSubnet['rebind-timer'] ?? 2000,
                                    'bvi_interface_id' => $bviId
                                ];
                                
                                $this->dhcpModel->updateSubnet($updateData);
                                error_log("Successfully updated relay address in Kea subnet");
                            }
                        }
                    } catch (\Exception $e) {
                        error_log("Warning: Could not update relay address in Kea: " . $e->getMessage());
                        // Don't fail the BVI update if Kea update fails
                    }
                }
                
                echo json_encode(['success' => true, 'message' => 'BVI interface updated successfully']);
            } else {
                echo json_encode(['success' => false, 'error' => 'BVI interface not found']);
            }
        } catch (\Exception $e) {
            error_log("Error in BVIController::update: " . $e->getMessage());
            echo json_encode(['success' => false, 'error' => 'Internal server error']);
        }
    }
    
    

    public function delete($switchId, $bviId)
    {
        try {
            header('Content-Type: application/json');
            
            // Check if there's an associated DHCP subnet and delete it from Kea using the API
            try {
                // Get the Kea subnet ID for this BVI using bvi_interface_id
                $stmt = $this->db->prepare("SELECT kea_subnet_id FROM cin_bvi_dhcp_core WHERE bvi_interface_id = ?");
                $stmt->execute([$bviId]);
                $subnet = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                if ($subnet && $subnet['kea_subnet_id']) {
                    error_log("Found DHCP subnet with Kea ID {$subnet['kea_subnet_id']} for BVI ID: $bviId - deleting via DHCP API");
                    // Pass the kea_subnet_id to deleteSubnet
                    $this->dhcpModel->deleteSubnet($subnet['kea_subnet_id']);
                    error_log("Successfully deleted DHCP subnet via API");
                } else {
                    error_log("No DHCP subnet found for BVI ID: $bviId");
                }
            } catch (\Exception $e) {
                error_log("Warning: Could not delete DHCP subnet via API: " . $e->getMessage());
                // Continue with BVI deletion even if DHCP deletion fails
            }
            
            // Delete the BVI interface (BVIModel should NOT delete from cin_bvi_dhcp_core since we already did it via API)
            $result = $this->bviModel->deleteBviInterface($switchId, $bviId);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'BVI interface and associated DHCP subnet deleted successfully']);
            } else {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'BVI interface not found']);
            }
        } catch (\Exception $e) {
            error_log("Error in BVIController::delete: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
    public function checkBVIExists($switchId) 
    {
        header('Content-Type: application/json');
        
        $interfaceNumber = $_GET['interface_number'] ?? '';
        $excludeId = $_GET['exclude_id'] ?? null;
    
        try {
            $exists = $this->bviModel->bviExists($switchId, $interfaceNumber, $excludeId);
            echo json_encode([
                'exists' => $exists
            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        } catch (\Exception $e) {
            error_log("Error in checkBVIExists: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'error' => htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        }
    }
    
    public function checkIPv6Exists()
    {
        header('Content-Type: application/json');
        
        $ipv6 = $_GET['ipv6'] ?? '';
    
        try {
            $exists = $this->bviModel->ipv6Exists($ipv6);
            echo json_encode([
                'exists' => $exists
            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        } catch (\Exception $e) {
            error_log("Error in checkIPv6Exists: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'error' => htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        }
    }
    

}
