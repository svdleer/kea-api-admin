<?php

namespace App\Middleware;

class CombinedAuthMiddleware {
    private $auth;
    private $apiKeyModel;
    private $requireWrite;

    public function __construct($auth, $apiKeyModel, $requireWrite = false) {
        $this->auth = $auth;
        $this->apiKeyModel = $apiKeyModel;
        $this->requireWrite = $requireWrite;
    }

    public function handle() {
        // First check web session authentication
        if ($this->auth->isLoggedIn()) {
            return true;
        }

        // If no web session, check for API key
        $headers = getallheaders();
        $apiKey = isset($headers['X-API-Key']) ? $headers['X-API-Key'] : null;

        if (!$apiKey) {
            header('HTTP/1.1 401 Unauthorized');
            echo json_encode(['error' => 'Authentication required']);
            exit;
        }

        // Validate API key
        if (!$this->apiKeyModel->validateKey($apiKey, $this->requireWrite)) {
            header('HTTP/1.1 401 Unauthorized');
            echo json_encode(['error' => 'Invalid API key']);
            exit;
        }

        return true;
    }
}
