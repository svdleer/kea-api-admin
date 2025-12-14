<?php

namespace App\Controllers;

use App\Database\Database;
use PDO;
use Exception;

class KeaServerController
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Display Kea servers management page
     */
    public function index()
    {
        $title = 'Kea DHCP Servers';
        $currentPage = 'kea-servers';
        
        require_once BASE_PATH . '/views/admin/kea-servers.php';
    }

    /**
     * Get all Kea servers (API endpoint)
     */
    public function getServers()
    {
        try {
            $stmt = $this->db->query(
                "SELECT * FROM kea_servers ORDER BY priority ASC, name ASC"
            );
            $servers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'servers' => $servers
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Failed to fetch servers: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Create a new Kea server
     */
    public function create()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['name']) || empty($data['api_url'])) {
                throw new Exception('Name and API URL are required');
            }

            $stmt = $this->db->prepare(
                "INSERT INTO kea_servers (name, description, api_url, username, password, is_active, priority) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );

            $stmt->execute([
                $data['name'],
                $data['description'] ?? null,
                $data['api_url'],
                $data['username'] ?? null,
                $data['password'] ?? null,
                isset($data['is_active']) ? (bool)$data['is_active'] : true,
                $data['priority'] ?? 99
            ]);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Kea server created successfully',
                'id' => $this->db->lastInsertId()
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Failed to create server: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Update an existing Kea server
     */
    public function update($id)
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data['name']) || empty($data['api_url'])) {
                throw new Exception('Name and API URL are required');
            }

            $stmt = $this->db->prepare(
                "UPDATE kea_servers 
                 SET name = ?, description = ?, api_url = ?, username = ?, password = ?, is_active = ?, priority = ?
                 WHERE id = ?"
            );

            $stmt->execute([
                $data['name'],
                $data['description'] ?? null,
                $data['api_url'],
                $data['username'] ?? null,
                $data['password'] ?? null,
                isset($data['is_active']) ? (bool)$data['is_active'] : true,
                $data['priority'] ?? 99,
                $id
            ]);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Kea server updated successfully'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update server: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Delete a Kea server
     */
    public function delete($id)
    {
        try {
            $stmt = $this->db->prepare("DELETE FROM kea_servers WHERE id = ?");
            $stmt->execute([$id]);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Kea server deleted successfully'
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Failed to delete server: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Test connection to a Kea server
     */
    public function testConnection($id)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM kea_servers WHERE id = ?");
            $stmt->execute([$id]);
            $server = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$server) {
                throw new Exception('Server not found');
            }

            // Try to connect to Kea API
            $ch = curl_init($server['api_url'] . '/');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            
            if (!empty($server['username']) && !empty($server['password'])) {
                curl_setopt($ch, CURLOPT_USERPWD, $server['username'] . ':' . $server['password']);
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            $success = ($httpCode >= 200 && $httpCode < 300) || $httpCode === 401;

            header('Content-Type: application/json');
            echo json_encode([
                'success' => $success,
                'message' => $success ? 'Connection successful' : 'Connection failed: ' . ($error ?: 'HTTP ' . $httpCode),
                'http_code' => $httpCode
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'message' => 'Test failed: ' . $e->getMessage()
            ]);
        }
    }
}
