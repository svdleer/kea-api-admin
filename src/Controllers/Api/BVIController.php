<?php

namespace App\Controllers\Api;

use App\Models\BVIModel;
use App\Database\Database;

class BVIController
{
    private BVIModel $bviModel;
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->bviModel = new BVIModel($this->db);
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
            
            if (!$data) {
                echo json_encode(['success' => false, 'error' => 'Invalid JSON data']);
                return;
            }
    
            $result = $this->bviModel->updateBviInterface($switchId, $bviId, $data);
            
            if ($result) {
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
            $result = $this->bviModel->deleteBviInterface($switchId, $bviId);
            
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'BVI interface deleted successfully']);
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
