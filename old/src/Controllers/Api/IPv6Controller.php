<?php

namespace App\Controllers\Api;

use App\Models\IPv6Subnet;
use App\Auth\Authentication;

class IPv6Controller {
    private IPv6Subnet $subnetModel;
    private Authentication $auth;

    public function __construct(IPv6Subnet $subnetModel, Authentication $auth) {
        $this->subnetModel = $subnetModel;
        $this->auth = $auth;
    }

    public function create() {
        if (!$this->auth->isLoggedIn() || !$this->auth->isAdmin()) {
            http_response_code(403);
            return ['error' => 'Unauthorized'];
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['prefix']) || !isset($data['bvi_id']) || !isset($data['name'])) {
            http_response_code(400);
            return ['error' => 'Missing required fields'];
        }

        if (!$this->subnetModel->validatePrefix($data['prefix'])) {
            http_response_code(400);
            return ['error' => 'Invalid IPv6 prefix format'];
        }

        $subnetId = $this->subnetModel->createSubnet(
            $data['prefix'],
            $data['bvi_id'],
            $data['name'],
            $data['description'] ?? null
        );

        if ($subnetId) {
            $pool = $this->subnetModel->getPool($data['prefix']);
            return [
                'id' => $subnetId,
                'message' => 'IPv6 subnet created successfully',
                'pool' => $pool
            ];
        }

        http_response_code(500);
        return ['error' => 'Failed to create IPv6 subnet'];
    }

    public function list() {
        if (!$this->auth->isLoggedIn()) {
            http_response_code(403);
            return ['error' => 'Unauthorized'];
        }

        $subnets = $this->subnetModel->getAllSubnets();
        foreach ($subnets as &$subnet) {
            $subnet['pool'] = $this->subnetModel->getPool($subnet['prefix']);
        }

        return ['subnets' => $subnets];
    }

    public function update($subnetId) {
        if (!$this->auth->isLoggedIn() || !$this->auth->isAdmin()) {
            http_response_code(403);
            return ['error' => 'Unauthorized'];
        }

        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data)) {
            http_response_code(400);
            return ['error' => 'No data provided'];
        }

        if (isset($data['prefix']) && !$this->subnetModel->validatePrefix($data['prefix'])) {
            http_response_code(400);
            return ['error' => 'Invalid IPv6 prefix format'];
        }

        if ($this->subnetModel->updateSubnet($subnetId, $data)) {
            $subnet = $this->subnetModel->getSubnetById($subnetId);
            if ($subnet) {
                $subnet['pool'] = $this->subnetModel->getPool($subnet['prefix']);
            }
            return [
                'message' => 'IPv6 subnet updated successfully',
                'subnet' => $subnet
            ];
        }

        http_response_code(500);
        return ['error' => 'Failed to update IPv6 subnet'];
    }

    public function delete($subnetId) {
        if (!$this->auth->isLoggedIn() || !$this->auth->isAdmin()) {
            http_response_code(403);
            return ['error' => 'Unauthorized'];
        }

        if ($this->subnetModel->deleteSubnet($subnetId)) {
            return ['message' => 'IPv6 subnet deleted successfully'];
        }

        http_response_code(500);
        return ['error' => 'Failed to delete IPv6 subnet'];
    }

    public function getByBvi($bviId) {
        if (!$this->auth->isLoggedIn()) {
            http_response_code(403);
            return ['error' => 'Unauthorized'];
        }

        $subnets = $this->subnetModel->getSubnetsByBvi($bviId);
        foreach ($subnets as &$subnet) {
            $subnet['pool'] = $this->subnetModel->getPool($subnet['prefix']);
        }

        return ['subnets' => $subnets];
    }
}