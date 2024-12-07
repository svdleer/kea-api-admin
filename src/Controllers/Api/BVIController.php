<?php

namespace App\Controllers\Api;

use App\Database\Database;
use App\Models\BVIInterface;

class BVIController
{
    private $db;
    private $bviInterface;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->bviInterface = new BVIInterface($this->db);
    }

    public function index($switchId)
    {
        header('Content-Type: application/json');
        try {
            $bviInterfaces = $this->bviInterface->getAllBviInterfaces($switchId);
            echo json_encode(['success' => true, 'data' => $bviInterfaces]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function create($switchId)
    {
        header('Content-Type: application/json');
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['interface_number']) || !isset($data['ipv6_address'])) {
                throw new \Exception('Missing required fields');
            }

            $result = $this->bviInterface->createBviInterface($switchId, $data);
            echo json_encode(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function show($switchId, $bviId)
    {
        header('Content-Type: application/json');
        try {
            $bviInterface = $this->bviInterface->getBviInterface($switchId, $bviId);
            if (!$bviInterface) {
                http_response_code(404);
                echo json_encode(['success' => false, 'message' => 'BVI Interface not found']);
                return;
            }
            echo json_encode(['success' => true, 'data' => $bviInterface]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function update($switchId, $bviId)
    {
        header('Content-Type: application/json');
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['interface_number']) || !isset($data['ipv6_address'])) {
                throw new \Exception('Missing required fields');
            }

            $result = $this->bviInterface->updateBviInterface($switchId, $bviId, $data);
            echo json_encode(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function delete($switchId, $bviId)
    {
        header('Content-Type: application/json');
        try {
            $result = $this->bviInterface->deleteBviInterface($switchId, $bviId);
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function checkBVIExists($switchId) 
    {
        header('Content-Type: application/json');
        
        $interfaceNumber = $_GET['interface_number'] ?? '';
        $excludeId = $_GET['exclude_id'] ?? null;  // Added support for exclude_id
    
        try {
            $exists = $this->bviInterface->bviExists($switchId, $interfaceNumber, $excludeId);
            echo json_encode(['exists' => $exists]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
    
    

    public function checkIPv6Exists()
    {
        header('Content-Type: application/json');
        
        $ipv6 = $_GET['ipv6'] ?? '';

        try {
            $exists = $this->bviInterface->ipv6Exists($ipv6);
            // amazonq-ignore-next-line
            echo json_encode(['exists' => $exists], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        }
    }

    // In your BVIController or similar class
    

}
