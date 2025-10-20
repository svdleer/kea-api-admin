<?php

namespace App\Controllers\Api;

use App\Network\NetworkManager;
use App\Database\Database;

class NetworkController
{
    private $networkManager;

    public function __construct()
    {
        $db = Database::getInstance();
        $this->networkManager = new NetworkManager($db);
    }

    /**
     * Add a new subnet
     */
    public function createSubnet()
    {
        try {
            $this->validateJsonRequest();
            $data = $this->getJsonData();

            // Validate required fields
            if (!isset($data['network']) || !isset($data['mask'])) {
                throw new \InvalidArgumentException('Network and mask are required');
            }

            // Validate IPv6 network format
            if (!filter_var($data['network'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                throw new \InvalidArgumentException('Invalid IPv6 network address format');
            }

            // Validate IPv6 prefix length (mask)
            if (!is_numeric($data['mask']) || $data['mask'] < 0 || $data['mask'] > 128) {
                throw new \InvalidArgumentException('Invalid IPv6 prefix length (must be between 0 and 128)');
            }

            $result = $this->networkManager->createSubnet($data);

            $this->sendJsonResponse([
                'success' => true,
                'message' => 'Subnet created successfully',
                'data' => ['id' => $result]
            ]);

        } catch (\Exception $e) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Update an existing subnet
     */
    public function updateSubnet($subnetId)
    {
        try {
            $this->validateJsonRequest();
            $data = $this->getJsonData();

            // Validate IPv6 network format if provided
            if (isset($data['network']) && !filter_var($data['network'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                throw new \InvalidArgumentException('Invalid IPv6 network address format');
            }

            // Validate IPv6 prefix length if provided
            if (isset($data['mask']) && (!is_numeric($data['mask']) || $data['mask'] < 0 || $data['mask'] > 128)) {
                throw new \InvalidArgumentException('Invalid IPv6 prefix length (must be between 0 and 128)');
            }

            $result = $this->networkManager->updateSubnet($subnetId, $data);

            $this->sendJsonResponse([
                'success' => true,
                'message' => 'Subnet updated successfully'
            ]);

        } catch (\Exception $e) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Delete a subnet
     */
    public function deleteSubnet($subnetId)
    {
        try {
            $result = $this->networkManager->deleteSubnet($subnetId);

            $this->sendJsonResponse([
                'success' => true,
                'message' => 'Subnet deleted successfully'
            ]);

        } catch (\Exception $e) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Add a new prefix
     */
    public function createPrefix($subnetId)
    {
        try {
            $this->validateJsonRequest();
            $data = $this->getJsonData();

            // Validate required fields
            if (!isset($data['prefix']) || !isset($data['mask'])) {
                throw new \InvalidArgumentException('Prefix and mask are required');
            }

            // Validate IPv6 prefix format
            if (!filter_var($data['prefix'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                throw new \InvalidArgumentException('Invalid IPv6 prefix address format');
            }

            // Validate IPv6 prefix length
            if (!is_numeric($data['mask']) || $data['mask'] < 0 || $data['mask'] > 128) {
                throw new \InvalidArgumentException('Invalid IPv6 prefix length (must be between 0 and 128)');
            }

            $result = $this->networkManager->createPrefix($subnetId, $data);

            $this->sendJsonResponse([
                'success' => true,
                'message' => 'Prefix created successfully',
                'data' => ['id' => $result]
            ]);

        } catch (\Exception $e) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Update an existing prefix
     */
    public function updatePrefix($subnetId, $prefixId)
    {
        try {
            $this->validateJsonRequest();
            $data = $this->getJsonData();

            // Validate IPv6 prefix format if provided
            if (isset($data['prefix']) && !filter_var($data['prefix'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                throw new \InvalidArgumentException('Invalid IPv6 prefix address format');
            }

            // Validate IPv6 prefix length if provided
            if (isset($data['mask']) && (!is_numeric($data['mask']) || $data['mask'] < 0 || $data['mask'] > 128)) {
                throw new \InvalidArgumentException('Invalid IPv6 prefix length (must be between 0 and 128)');
            }

            $result = $this->networkManager->updatePrefix($subnetId, $prefixId, $data);

            $this->sendJsonResponse([
                'success' => true,
                'message' => 'Prefix updated successfully'
            ]);

        } catch (\Exception $e) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Delete a prefix
     */
    public function deletePrefix($subnetId, $prefixId)
    {
        try {
            $result = $this->networkManager->deletePrefix($subnetId, $prefixId);

            $this->sendJsonResponse([
                'success' => true,
                'message' => 'Prefix deleted successfully'
            ]);

        } catch (\Exception $e) {
            $this->sendJsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Validate JSON API request
     */
    private function validateJsonRequest()
    {
        if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
            throw new \Exception('Content-Type must be application/json');
        }

        if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
            throw new \Exception('Invalid request type');
        }
    }

    /**
     * Get JSON request data
     */
    private function getJsonData()
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON data');
        }

        return $data;
    }

    /**
     * Send JSON response
     */
    private function sendJsonResponse($data, $statusCode = 200)
    {
        header('Content-Type: application/json');
        http_response_code($statusCode);
        echo json_encode($data);
        return;
    }
}
