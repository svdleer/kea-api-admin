<?php

namespace App\Controllers\Api;

use App\Models\ApiKey;
use App\Auth\Authentication;



class ApiKeyController {
    private $apiKeyModel;
    private $auth;

    public function __construct(ApiKey $apiKeyModel, Authentication $auth) {
        $this->apiKeyModel = $apiKeyModel;
        $this->auth = $auth;
    }

    public function create() {
        try {
            error_log("ApiKeyController: create() called");
            
            if (!$this->auth->isAdmin()) {
                error_log("ApiKeyController: Unauthorized - not admin");
                http_response_code(403);
                echo json_encode(['error' => 'Unauthorized: Admin access required']);
                exit;
            }
    
            $data = json_decode(file_get_contents('php://input'), true);
            error_log("ApiKeyController: Received data: " . json_encode($data));
    
            if (!isset($data['name'])) {
                error_log("ApiKeyController: Missing name field");
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields']);
                exit;
            }
    
            $readOnly = isset($data['read_only']) ? (bool)$data['read_only'] : false;
            error_log("ApiKeyController: Creating API key - name: {$data['name']}, read_only: " . ($readOnly ? 'true' : 'false'));
            
            // Get current user ID from session
            $userId = $_SESSION['user_id'] ?? null;
            error_log("ApiKeyController: User ID from session: " . ($userId ?? 'null'));
            
            $apiKey = $this->apiKeyModel->createApiKey(
                $data['name'],
                $readOnly,
                $userId
            );
    
            error_log("ApiKeyController: API key created: " . json_encode($apiKey));
    
            if ($apiKey) {
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'success',
                    'apiKey' => $apiKey,
                    'message' => 'API key created successfully'
                ]);
                exit;
            }
    
            error_log("ApiKeyController: Failed to create API key - returned false/null");
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create API key']);
            exit;
        } catch (\Exception $e) {
            error_log("ApiKeyController: Exception: " . $e->getMessage());
            error_log("ApiKeyController: Stack trace: " . $e->getTraceAsString());
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
            exit;
        }
    }
    

    public function list() {
        try {
            // Check if user is admin
            if (!$this->auth->isAdmin()) {
                http_response_code(403);
                echo json_encode(['error' => 'Unauthorized: Admin access required']);
                exit;
            }

            $keys = $this->apiKeyModel->getApiKeys();
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'keys' => $keys
            ]);
            exit;
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Server error']);
            exit;
        }
    }

    public function deactivate($id) {
        try {
            // Check if user is admin
            if (!$this->auth->isAdmin()) {
                http_response_code(403);
                echo json_encode(['error' => 'Unauthorized: Admin access required']);
                exit;
            }

            $success = $this->apiKeyModel->deactivateApiKey($id);
            
            if ($success) {
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'success',
                    'message' => 'API key deactivated successfully'
                ]);
                exit;
            }

            http_response_code(500);
            echo json_encode(['error' => 'Failed to deactivate API key']);
            exit;
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Server error']);
            exit;
        }
    }
    public function delete($id) {
        try {
            $success = $this->apiKeyModel->deleteApiKey($id);
            
            if ($success) {
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'success',
                    'message' => 'API key deleted successfully'
                ]);
                exit;
            }
    
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to delete API key'
            ]);
            exit;
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
            exit;
        }
    }
    
}