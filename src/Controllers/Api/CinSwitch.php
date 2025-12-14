<?php

namespace App\Controllers\Api;

use PDO;
use App\Models\DHCP;

class CinSwitch
{
    private $db;
    private $dhcpModel;

    public function __construct($db)
    {
        $this->db = $db;
        $this->dhcpModel = new DHCP($db);
    }

    public function getAll() {
        try {
            header('Content-Type: application/json');
            
            $stmt = $this->db->prepare("
                SELECT * 
                FROM cin_switches 
                ORDER BY hostname
            ");
            $stmt->execute();
            $switches = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $switches
            ]);
        } catch (\Exception $e) {
            error_log("Error in CinSwitch::getAll: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Database error: ' . $e->getMessage()
            ]);
        }
    }

    public function getById($id) {
        try {
            header('Content-Type: application/json');
            
            $stmt = $this->db->prepare("
                SELECT s.*, b.interface_number, b.ipv6_address 
                FROM cin_switches s
                LEFT JOIN cin_switch_bvi_interfaces b ON s.id = b.switch_id
                WHERE s.id = ?
            ");
            $stmt->execute([$id]);
            $switch = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($switch) {
                echo json_encode([
                    'success' => true,
                    'data' => $switch
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Switch not found'
                ]);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Database error'
            ]);
        }
    }

    public function checkExists() {
        try {
            header('Content-Type: application/json');
            
            if (!isset($_GET['hostname'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Hostname parameter is required']);
                return;
            }

            $hostname = $_GET['hostname'];
            
            $stmt = $this->db->prepare("SELECT id FROM cin_switches WHERE hostname = ?");
            $stmt->execute([$hostname]);
            $switch = $stmt->fetch();

            echo json_encode([
                'success' => true,
                'exists' => !empty($switch)
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Database error'
            ]);
        }
    }

    public function checkIpv6() {
        try {
            header('Content-Type: application/json');
            
            if (!isset($_GET['ipv6'])) {
                http_response_code(400);
                echo json_encode(['error' => 'IPv6 parameter is required']);
                return;
            }

            $ipv6 = $_GET['ipv6'];
            
            $stmt = $this->db->prepare("
                SELECT id FROM cin_switch_bvi_interfaces 
                WHERE ipv6_address = ?
            ");
            $stmt->execute([$ipv6]);
            $interface = $stmt->fetch();

            echo json_encode([
                'success' => true,
                'exists' => !empty($interface)
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Database error'
            ]);
        }
    }

    public function create() {
        try {
            header('Content-Type: application/json');
            
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['hostname']) || !isset($data['interface_number']) || !isset($data['ipv6_address'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Missing required fields. Hostname, BVI interface, and IPv6 address are mandatory.'
                ]);
                return;
            }

            // Start transaction since we need to insert into two tables
            $this->db->beginTransaction();

            try {
                // First create the switch
                $stmtSwitch = $this->db->prepare("
                    INSERT INTO cin_switches (hostname) 
                    VALUES (?)
                ");

                $stmtSwitch->execute([$data['hostname']]);
                $switchId = $this->db->lastInsertId();

                // Then create the BVI interface
                $stmtBvi = $this->db->prepare("
                    INSERT INTO cin_switch_bvi_interfaces 
                    (switch_id, interface_number, ipv6_address) 
                    VALUES (?, ?, ?)
                ");

                $stmtBvi->execute([
                    $switchId,
                    $data['interface_number'],
                    $data['ipv6_address']
                ]);

                // If we get here, commit the transaction
                $this->db->commit();

                echo json_encode([
                    'success' => true,
                    'message' => 'Switch and BVI interface created successfully',
                    'id' => $switchId
                ]);

            } catch (\Exception $e) {
                // If anything goes wrong, roll back the transaction
                $this->db->rollBack();
                throw $e;
            }

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Database error: ' . $e->getMessage()
            ]);
        }
    }

    public function update($id) {
        try {
            header('Content-Type: application/json');
            
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['hostname'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Hostname is required'
                ]);
                return;
            }

            // Get old hostname before update
            $stmt = $this->db->prepare("SELECT hostname FROM cin_switches WHERE id = ?");
            $stmt->execute([$id]);
            $oldSwitch = $stmt->fetch();
            $oldHostname = $oldSwitch ? $oldSwitch['hostname'] : null;
            $newHostname = $data['hostname'];

            $stmt = $this->db->prepare("
                UPDATE cin_switches 
                SET hostname = ?
                WHERE id = ?
            ");

            $result = $stmt->execute([
                $newHostname,
                $id
            ]);

            if ($result && $oldHostname && $oldHostname !== $newHostname) {
                // Update RADIUS client shortnames for all BVI interfaces of this switch
                require_once BASE_PATH . '/src/Models/RadiusClient.php';
                require_once BASE_PATH . '/src/Helpers/RadiusDatabaseSync.php';
                
                $radiusModel = new \App\Models\RadiusClient($this->db);
                
                error_log("Switch hostname changed from '$oldHostname' to '$newHostname' - updating RADIUS clients");
                
                // Get all BVI interfaces for this switch
                $stmt = $this->db->prepare("
                    SELECT id, interface_number 
                    FROM cin_switch_bvi_interfaces 
                    WHERE switch_id = ?
                ");
                $stmt->execute([$id]);
                $bviInterfaces = $stmt->fetchAll();
                
                error_log("Found " . count($bviInterfaces) . " BVI interfaces for switch ID $id");
                
                foreach ($bviInterfaces as $bvi) {
                    // Find RADIUS client by BVI ID
                    $stmt = $this->db->prepare("SELECT id FROM nas WHERE bvi_interface_id = ?");
                    $stmt->execute([$bvi['id']]);
                    $radiusClient = $stmt->fetch();
                    
                    if ($radiusClient) {
                        // Generate new shortname: <hostname>-bvi<display_number>
                        $displayBvi = $bvi['interface_number'] + 100;
                        $newShortname = strtolower($newHostname) . '-bvi' . $displayBvi;
                        
                        error_log("Updating RADIUS client ID {$radiusClient['id']}: BVI {$bvi['id']} -> shortname: $newShortname");
                        
                        // Update shortname in main database
                        $stmt = $this->db->prepare("UPDATE nas SET shortname = ? WHERE id = ?");
                        $updateResult = $stmt->execute([$newShortname, $radiusClient['id']]);
                        error_log("Database update result: " . ($updateResult ? 'success' : 'failed'));
                        
                        // Sync to RADIUS servers
                        try {
                            $updatedClient = $radiusModel->getClientById($radiusClient['id']);
                            error_log("Retrieved client data: " . json_encode($updatedClient));
                            
                            if ($updatedClient) {
                                $radiusSync = new \App\Helpers\RadiusDatabaseSync($this->db);
                                $syncResult = $radiusSync->syncClientToAllServers($updatedClient, 'UPDATE');
                                error_log("Sync result for client {$radiusClient['id']}: " . json_encode($syncResult));
                            } else {
                                error_log("Failed to retrieve updated client {$radiusClient['id']} from database");
                            }
                        } catch (\Exception $e) {
                            error_log("Error syncing RADIUS client {$radiusClient['id']}: " . $e->getMessage());
                            error_log("Stack trace: " . $e->getTraceAsString());
                        }
                    }
                }
            }

            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Switch updated successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to update switch'
                ]);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Database error: ' . $e->getMessage()
            ]);
        }
    }

    public function delete($id) {
        try {
            header('Content-Type: application/json');

            // Initialize RadiusClient model
            require_once BASE_PATH . '/src/Models/RadiusClient.php';
            $radiusModel = new \App\Models\RadiusClient($this->db);

            // Get all BVI interfaces for this switch
            $stmtGetBvi = $this->db->prepare("
                SELECT id, ipv6_address FROM cin_switch_bvi_interfaces 
                WHERE switch_id = ?
            ");
            $stmtGetBvi->execute([$id]);
            $bviInterfaces = $stmtGetBvi->fetchAll(\PDO::FETCH_ASSOC);

            // Delete DHCP subnets and RADIUS clients for each BVI
            $deletedRadiusClients = 0;
            foreach ($bviInterfaces as $bvi) {
                // Delete DHCP subnet
                try {
                    // Use bvi_interface_id to find the correct DHCP core record
                    $stmt = $this->db->prepare("SELECT kea_subnet_id FROM cin_bvi_dhcp_core WHERE bvi_interface_id = ?");
                    $stmt->execute([$bvi['id']]);
                    $subnet = $stmt->fetch(\PDO::FETCH_ASSOC);
                    
                    if ($subnet && $subnet['kea_subnet_id']) {
                        error_log("Deleting DHCP subnet (Kea ID: {$subnet['kea_subnet_id']}) for BVI {$bvi['id']} via API");
                        // Pass the kea_subnet_id to deleteSubnet
                        $this->dhcpModel->deleteSubnet($subnet['kea_subnet_id']);
                    }
                } catch (\Exception $e) {
                    error_log("Warning: Could not delete DHCP subnet for BVI {$bvi['id']}: " . $e->getMessage());
                }

                // Delete RADIUS client
                try {
                    $radiusClient = $radiusModel->getClientByBviId($bvi['id']);
                    if ($radiusClient) {
                        error_log("Deleting RADIUS client (ID: {$radiusClient['id']}, NAS: {$bvi['ipv6_address']}) for BVI {$bvi['id']}");
                        $radiusModel->deleteClient($radiusClient['id']);
                        $deletedRadiusClients++;
                    }
                } catch (\Exception $e) {
                    error_log("Warning: Could not delete RADIUS client for BVI {$bvi['id']}: " . $e->getMessage());
                }
            }

            $this->db->beginTransaction();

            try {
                // Delete BVI interfaces
                $stmtBvi = $this->db->prepare("
                    DELETE FROM cin_switch_bvi_interfaces 
                    WHERE switch_id = ?
                ");
                $stmtBvi->execute([$id]);

                // Delete the switch
                $stmtSwitch = $this->db->prepare("
                    DELETE FROM cin_switches 
                    WHERE id = ?
                ");
                $stmtSwitch->execute([$id]);

                $this->db->commit();

                $message = 'Switch and ' . count($bviInterfaces) . ' BVI interface(s) deleted successfully';
                if ($deletedRadiusClients > 0) {
                    $message .= ' (including ' . $deletedRadiusClients . ' RADIUS client(s))';
                }

                echo json_encode([
                    'success' => true,
                    'message' => $message
                ]);
            } catch (\Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            error_log("Error in CinSwitch::delete: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Error deleting switch: ' . $e->getMessage()
            ]);
        }
    }
}
