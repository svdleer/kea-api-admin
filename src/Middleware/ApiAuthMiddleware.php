<?php

namespace App\Middleware;

use App\Models\ApiKey;

class ApiAuthMiddleware
{
    private $apiKeyModel;
    private $requireWrite;

    public function __construct(ApiKey $apiKeyModel, bool $requireWrite = false)
    {
        $this->apiKeyModel = $apiKeyModel;
        $this->requireWrite = $requireWrite;
    }

    public function handle()
    {
        $headers = getallheaders();
        $apiKey = $headers['X-API-Key'] ?? null;

        if (!$apiKey) {
            http_response_code(401);
            echo json_encode(['error' => 'API key is required']);
            exit;
        }

        $keyData = $this->apiKeyModel->validateApiKey($apiKey);

        if (!$keyData) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid API key']);
            exit;
        }

        if ($this->requireWrite && $keyData['read_only']) {
            http_response_code(403);
            echo json_encode(['error' => 'Write access required for this operation']);
            exit;
        }

        $this->apiKeyModel->updateLastUsed($keyData['id']);
        return true;
    }
}