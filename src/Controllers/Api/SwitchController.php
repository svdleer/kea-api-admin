<?php

namespace App\Controllers\Api;

use App\Database\Database;

class SwitchController {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function getAll() {
        try {
            header('Content-Type: application/json');
            
            $stmt = $this->db->prepare("
                SELECT s.*, b.interface_number, b.ipv6_address 
                FROM cin_switches s
                LEFT JOIN cin_switch_bvi_interfaces b ON s.id = b.switch_id
                ORDER BY s.hostname
            ");
            $stmt->execute();
            $switches = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'data' => $switches
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Database error'
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

            $stmt = $this->db->prepare("
                UPDATE cin_switches 
                SET hostname = ?
                WHERE id = ?
            ");

            $result = $stmt->execute([
                $data['hostname'],
                $id
            ]);

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
                'error' => 'Database error'
            ]);
        }
    }

    public function delete($id) {
        try {
            header('Content-Type: application/json');

            $this->db->beginTransaction();

            try {
                // First delete related BVI interfaces
                $stmtBvi = $this->db->prepare("
                    DELETE FROM cin_switch_bvi_interfaces 
                    WHERE switch_id = ?
                ");
                $stmtBvi->execute([$id]);

                // Then delete the switch
                $stmtSwitch = $this->db->prepare("
                    DELETE FROM cin_switches 
                    WHERE id = ?
                ");
                $stmtSwitch->execute([$id]);

                $this->db->commit();

                echo json_encode([
                    'success' => true,
                    'message' => 'Switch and related BVI interfaces deleted successfully'
                ]);
            } catch (\Exception $e) {
                $this->db->rollBack();
                throw $e;
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Database error'
            ]);
        }
    }
}
