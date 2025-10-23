<?php 

namespace App\Controllers\Api;

use App\Models\CinSwitch;
use App\Models\DHCP;
use App\Database\Database;
use App\Auth\Authentication;
use App\Controllers\Api\DHCPController;



class CinSwitchController
{
    protected CinSwitch $cinswitchModel;
    protected Authentication $auth;
    private $db;
    private $dhcpModel;
    private $dhcpController;

 

    public function __construct(CinSwitch $cinswitchModel, Authentication $auth)
    {
        $this->db = Database::getInstance();
        $this->cinswitchModel = $cinswitchModel;
        $this->auth = $auth;
        $this->dhcpModel = new DHCP($this->db);
        $this->dhcpController = new DHCPController($this->dhcpModel, $this->auth);  // Pass both dependencies
    }
    

    public function getById($id) {
        try {
            header('Content-Type: application/json');
            
            $switch = $this->cinswitchModel->getById($id);

            if ($switch) {
                echo json_encode([
                    'success' => true,
                    'data' => $switch
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Switch not found'
                ]);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Database error'
            ]);
        }
    }

    public function getAllSwitches()
    {
        try {
            $switches = $this->cinswitchModel->getAllSwitches();
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $switches
            ]);
        } catch (\Exception $e) {
            error_log("Error in CinSwitchController::getAllSwitches(): " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Error fetching switches'
            ]);
        }
    }
    
    public function getNextAvailableBVINumber($switchId)
    {
        try {
            // Validate input
            if (!$switchId) {
                throw new \InvalidArgumentException('Switch ID is required');
            }
    
            $result = $this->BVIModel->getNextAvailableBVINumber($switchId);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => [
                    'next_bvi_number' => $result['next_number']
                ]
            ]);
    
        } catch (\InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        } catch (\RuntimeException $e) {
            error_log("Error in BVIController::getNextAvailableBVINumber(): " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Error fetching next BVI number',
                'default_number' => 'BVI100'
            ]);
        } catch (\Exception $e) {
            error_log("Unexpected error in BVIController::getNextAvailableBVINumber(): " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'An unexpected error occurred',
                'default_number' => 'BVI100'
            ]);
        }
    }
    

    public function getBviCount($switchId)
{
    try {
        $count = $this->cinswitchModel->getBviCount($switchId);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => [
                'switch_id' => $switchId,
                'bvi_count' => $count
            ]
        ]);
    } catch (\Exception $e) {
        error_log("Error in CinSwitchController::getBviCount(): " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Error fetching BVI count'
        ]);
    }
}

public function getBviInterfaces($switchId)
{
    try {
        $interfaces = $this->cinswitchModel->getBviInterfaces($switchId);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => [
                'switch_id' => $switchId,
                'interfaces' => $interfaces
            ]
        ]);
    } catch (\RuntimeException $e) {
        error_log("Error in CinSwitchController::getBviInterfaces(): " . $e->getMessage());
        
        // Handle "not found" case specifically
        if (strpos($e->getMessage(), 'not found') !== false) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
            return;
        }
        
        // Handle other runtime errors
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Error fetching BVI interfaces'
        ]);
    } catch (\Exception $e) {
        error_log("Unexpected error in CinSwitchController::getBviInterfaces(): " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'An unexpected error occurred'
        ]);
    }
}

public function getBviInterface($switchId, $bviId)
{
    try {
        // Validate input parameters
        if (!$switchId || !$bviId) {
            throw new \InvalidArgumentException('Switch ID and BVI ID are required');
        }

        $bviInterface = $this->cinswitchModel->getBviInterface($switchId, $bviId);
        
        if ($bviInterface === null) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'BVI interface not found'
            ]);
            return;
        }

        echo json_encode([
            'success' => true,
            'data' => $bviInterface
        ]);

    } catch (\InvalidArgumentException $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    } catch (\RuntimeException $e) {
        error_log("Error in CinSwitchController::getBviInterface(): " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Error fetching BVI interface'
        ]);
    } catch (\Exception $e) {
        error_log("Unexpected error in CinSwitchController::getBviInterface(): " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'An unexpected error occurred'
        ]);
    }
}


public function checkExists()
{
    try {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        if (!isset($data['hostname'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Missing hostname'
            ]);
            return;
        }

        $exists = $this->cinswitchModel->checkExists($data['hostname']);

        echo json_encode([
            'success' => true,
            'exists' => $exists
        ]);
    } catch (\Exception $e) {
        error_log("Error in CinSwitchController::hostnameExists(): " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Error checking hostname'
        ]);
    }
}


public function checkIpv6()
{
    try {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        if (!isset($data['ipv6'])) {  // Changed back to 'ipv6'
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'IPv6 parameter is required'
            ]);
            return;
        }

        $exists = $this->cinswitchModel->checkIpv6Exists($data['ipv6']);

        echo json_encode([
            'success' => true,
            'exists' => (bool)$exists
        ]);
    } catch (\Exception $e) {
        error_log("Error in CinSwitchController::checkIpv6(): " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Error checking IPv6 address'
        ]);
    }
}

    public function create()
    {
        try {
            header('Content-Type: application/json');
            
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['hostname']) || !isset($data['interface_number']) || !isset($data['ipv6_address'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Missing required fields. Hostname, BVI interface, and IPv6 address are mandatory.'
                ]);
                return;
            }

            $switchId = $this->cinswitchModel->create($data);

            echo json_encode([
                'success' => true,
                'message' => 'Switch and BVI interface created successfully',
                'id' => $switchId
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Database error: ' . $e->getMessage()
            ]);
        }
    }

    public function update($id)
    {
        try {
            header('Content-Type: application/json');
            
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['hostname'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Hostname is required'
                ]);
                return;
            }

            $result = $this->cinswitchModel->update($id, $data['hostname']);

            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Switch updated successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Failed to update switch'
                ]);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Database error'
            ]);
        }
    }


    public function delete($id)
    {
        try {
            header('Content-Type: application/json');

            // Check if Switch has DHCP subnets
            if ($this->dhcpModel->hasDHCPSubnetsForSwitch($id)) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Cannot delete switch: DHCP subnets are still attached to BVI interfaces on this switch'
                ]);
                return;
            }

            error_log("Attempting to delete CIN Switch and related BVI interfaces");

            if ($this->cinswitchModel->delete($id)) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Switch and related BVI interfaces deleted successfully'
                ]);
                return;
            } else {
                throw new \Exception('Failed to delete switch');
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Database error'
            ]);
        }
    }
}
