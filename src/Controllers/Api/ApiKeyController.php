<?php

namespace App\Controllers\Api;

use App\Models\ApiKey;
use App\Auth\Authentication;

class ApiKeyController
{
    private $apiKeyModel;
    private $auth;

    public function __construct(ApiKey $apiKeyModel, Authentication $auth)
    {
        $this->apiKeyModel = $apiKeyModel;
        $this->auth = $auth;
    }

    public function create()
    {
        if (!$this->auth->isLoggedIn() || !$this->auth->isAdmin()) {
            return json_encode(['error' => 'Unauthorized']);
        }

        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['name']) || empty($data['name'])) {
            return json_encode(['error' => 'Name is required']);
        }

        $readOnly = isset($data['read_only']) ? (bool)$data['read_only'] : false;
        $userId = $this->auth->getCurrentUserId();
        
        $apiKey = $this->apiKeyModel->createApiKey($userId, $data['name'], $readOnly);
        
        if ($apiKey) {
            return json_encode(['success' => true, 'api_key' => $apiKey]);
        }
        
        return json_encode(['error' => 'Failed to create API key']);
    }

    public function list()
    {
        if (!$this->auth->isLoggedIn()) {
            return json_encode(['error' => 'Unauthorized']);
        }

        $userId = $this->auth->getCurrentUserId();
        $keys = $this->apiKeyModel->getUserApiKeys($userId);
        
        return json_encode(['success' => true, 'keys' => $keys]);
    }

    public function deactivate($keyId)
    {
        if (!$this->auth->isLoggedIn()) {
            return json_encode(['error' => 'Unauthorized']);
        }

        $userId = $this->auth->getCurrentUserId();
        
        if ($this->apiKeyModel->deactivateApiKey($keyId, $userId)) {
            return json_encode(['success' => true]);
        }
        
        return json_encode(['error' => 'Failed to deactivate API key']);
    }
}