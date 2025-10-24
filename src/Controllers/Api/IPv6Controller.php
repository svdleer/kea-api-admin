<?php

namespace App\Controllers\Api;

use App\Models\DHCP;
use App\Auth\Authentication;

class IPv6Controller {
    private DHCP $dhcpModel;
    private Authentication $auth;

    public function __construct(DHCP $dhcpModel, Authentication $auth) {
        $this->dhcpModel = $dhcpModel;
        $this->auth = $auth;
    }

    public function create() {
        if (!$this->auth->isLoggedIn() || !$this->auth->isAdmin()) {
            http_response_code(403);
            return ['error' => 'Unauthorized'];
        }

        $data = json_decode(file_get_contents('php://input'), true);

        // Validate required fields (old format: name, prefix, bvi_id)
        if (!isset($data['prefix']) || !isset($data['bvi_id'])) {
            http_response_code(400);
            return ['error' => 'Missing required fields: prefix and bvi_id are required'];
        }

        try {
            // Transform old format to new format for Kea API
            // Calculate pool from prefix (e.g., 2001:db8::/64 -> 2001:db8::1000 to 2001:db8::ffff)
            $prefix = $data['prefix'];
            list($network, $length) = explode('/', $prefix);
            
            // Simple pool calculation - use ::1000 to ::ffff as pool range
            $baseNetwork = substr($network, 0, strrpos($network, ':') + 1);
            
            $keaData = [
                'subnet' => $prefix,
                'pool_start' => $baseNetwork . '1000',
                'pool_end' => $baseNetwork . 'ffff',
                'relay_address' => 'fe80::1', // default relay
                'ccap_core_address' => '',
                'switch_id' => null, // Will be populated from BVI
                'bvi_interface' => $data['bvi_id'],
                'ipv6_address' => null
            ];
            
            $result = $this->dhcpModel->createSubnet($keaData);
            
            if ($result) {
                return [
                    'message' => 'IPv6 subnet created successfully via Kea API',
                    'subnet_id' => $result
                ];
            }
        } catch (\Exception $e) {
            error_log("IPv6Controller create error: " . $e->getMessage());
            http_response_code(500);
            return ['error' => 'Failed to create IPv6 subnet: ' . $e->getMessage()];
        }

        http_response_code(500);
        return ['error' => 'Failed to create IPv6 subnet'];
    }

    public function list() {
        if (!$this->auth->isLoggedIn()) {
            http_response_code(403);
            return ['error' => 'Unauthorized'];
        }

        try {
            // Get subnets directly from Kea API
            $keaSubnets = $this->dhcpModel->getAllSubnetsfromKEA();
            
            error_log("IPv6Controller: Got " . count($keaSubnets) . " subnets from Kea");
            error_log("IPv6Controller: Kea subnets: " . json_encode($keaSubnets));
            
            // Format for display - show raw Kea data even without BVI enrichment
            $subnets = [];
            foreach ($keaSubnets as $subnet) {
                $subnets[] = [
                    'id' => $subnet['id'],
                    'subnet' => $subnet['subnet'],
                    'pool' => null,
                    'bvi_interface' => null,
                    'ipv6_address' => null,
                    'ccap_core' => 'N/A'
                ];
            }
            
            error_log("IPv6Controller: Returning " . count($subnets) . " formatted subnets");
            
            return ['subnets' => $subnets];
        } catch (\Exception $e) {
            error_log("IPv6Controller list error: " . $e->getMessage());
            error_log("IPv6Controller list trace: " . $e->getTraceAsString());
            http_response_code(500);
            return ['error' => 'Failed to retrieve subnets: ' . $e->getMessage()];
        }
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

        try {
            $data['id'] = $subnetId;
            $result = $this->dhcpModel->updateSubnet($data);
            
            if ($result) {
                return [
                    'message' => 'IPv6 subnet updated successfully via Kea API',
                    'subnet' => $result
                ];
            }
        } catch (\Exception $e) {
            http_response_code(500);
            return ['error' => 'Failed to update IPv6 subnet: ' . $e->getMessage()];
        }

        http_response_code(500);
        return ['error' => 'Failed to update IPv6 subnet'];
    }

    public function delete($subnetId) {
        if (!$this->auth->isLoggedIn() || !$this->auth->isAdmin()) {
            http_response_code(403);
            return ['error' => 'Unauthorized'];
        }

        try {
            $result = $this->dhcpModel->deleteSubnet($subnetId);
            
            if ($result) {
                return ['message' => 'IPv6 subnet deleted successfully from Kea'];
            }
        } catch (\Exception $e) {
            http_response_code(500);
            return ['error' => 'Failed to delete IPv6 subnet: ' . $e->getMessage()];
        }

        http_response_code(500);
        return ['error' => 'Failed to delete IPv6 subnet'];
    }

    public function getByBvi($bviId) {
        if (!$this->auth->isLoggedIn()) {
            http_response_code(403);
            return ['error' => 'Unauthorized'];
        }

        try {
            // Get all subnets from Kea and filter by BVI
            $allSubnets = $this->dhcpModel->getAllSubnetsfromKEA();
            $subnets = array_filter($allSubnets, function($subnet) use ($bviId) {
                return isset($subnet['bvi_id']) && $subnet['bvi_id'] == $bviId;
            });

            return ['subnets' => array_values($subnets)];
        } catch (\Exception $e) {
            http_response_code(500);
            return ['error' => 'Failed to retrieve subnets: ' . $e->getMessage()];
        }
    }

    public function getById($subnetId) {
        if (!$this->auth->isLoggedIn()) {
            http_response_code(403);
            return ['error' => 'Unauthorized'];
        }

        try {
            $subnet = $this->dhcpModel->getSubnetById($subnetId);
            
            if ($subnet) {
                return $subnet;
            }
            
            http_response_code(404);
            return ['error' => 'Subnet not found'];
        } catch (\Exception $e) {
            http_response_code(500);
            return ['error' => 'Failed to retrieve subnet: ' . $e->getMessage()];
        }
    }
}