<?php

namespace App\Controllers\Api;

use App\Models\DHCP;
use App\Auth\Authentication;
use App\Database\Database;



class DHCPController
{
    protected DHCP $subnetModel;
    protected Authentication $auth;

    public function __construct(DHCP $subnetModel, Authentication $auth)
    {
        $this->subnetModel = $subnetModel;
        $this->auth = $auth;
    }


    private function validateSubnetData($data)
    {
        $errors = [];
        error_log("DHCPController: Validating subnet data: " . json_encode($data));
        // Convert JavaScript regex to PHP format
        $ipv6Regex = '/^(?:(?:[0-9a-fA-F]{1,4}:){7}[0-9a-fA-F]{1,4}|(?:[0-9a-fA-F]{1,4}:){1,7}:|(?:[0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|(?:[0-9a-fA-F]{1,4}:){1,5}(?::[0-9a-fA-F]{1,4}){1,2}|(?:[0-9a-fA-F]{1,4}:){1,4}(?::[0-9a-fA-F]{1,4}){1,3}|(?:[0-9a-fA-F]{1,4}:){1,3}(?::[0-9a-fA-F]{1,4}){1,4}|(?:[0-9a-fA-F]{1,4}:){1,2}(?::[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:(?:(?::[0-9a-fA-F]{1,4}){1,6})|:(?:(?::[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(?::[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(?:ffff(?::0{1,4}){0,1}:){0,1}(?:(?:25[0-5]|(?:2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(?:25[0-5]|(?:2[0-4]|1{0,1}[0-9]){0,1}[0-9])|(?:[0-9a-fA-F]{1,4}:){1,4}:(?:(?:25[0-5]|(?:2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(?:25[0-5]|(?:2[0-4]|1{0,1}[0-9]){0,1}[0-9]))$/';
    
        // Check if this is a dedicated subnet
        $isDedicated = isset($data['dedicated']) && $data['dedicated'] === true;
        
        // Check if required fields exist
        $requiredFields = [
            'subnet',
            'pool_start',
            'pool_end',
            'relay_address'
        ];
        
        // BVI interface ID is only required for non-dedicated subnets
        if (!$isDedicated) {
            $requiredFields[] = 'bvi_interface_id';
            $requiredFields[] = 'ccap_core_address'; // CCAP core required for BVI subnets
        } else {
            // Name is required for dedicated subnets
            $requiredFields[] = 'name';
            // CCAP core is optional for dedicated subnets
        }

        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || rtrim($data[$field]) === '')  {
                $errors[] = "Field '$field' is required";
            }
        }
    
        // Validate BVI interface ID is numeric (only for non-dedicated)
        if (!$isDedicated && isset($data['bvi_interface_id']) && !is_numeric($data['bvi_interface_id'])) {
            $errors[] = "BVI interface ID must be numeric";
        }
    
        // Validate IPv6 subnet format (must include prefix length)
        if (isset($data['subnet'])) {
            if (!preg_match('/^([0-9a-fA-F:]+)\/(\d+)$/', $data['subnet'], $matches)) {
                $errors[] = "Invalid IPv6 subnet format. Must include prefix length (e.g., 2001:db8::/64)";
            } else {
                $prefix = $matches[2];
                if ($prefix < 0 || $prefix > 128) {
                    $errors[] = "Invalid IPv6 prefix length. Must be between 0 and 128";
                }
                // Validate the IPv6 part of the subnet
                $ipv6Part = $matches[1];
                if (!preg_match($ipv6Regex, $ipv6Part)) {
                    $errors[] = "Invalid IPv6 address format in subnet";
                }
            }
        }
    
        // Validate IPv6 addresses using the regex
        $ipv6Fields = ['pool_start', 'pool_end', 'relay_address'];
        foreach ($ipv6Fields as $field) {
            if (isset($data[$field]) && !empty($data[$field])) {
                if (!preg_match($ipv6Regex, $data[$field])) {
                    $errors[] = "Invalid IPv6 address format for $field";
                }
            }
        }
        
        // Validate ccap_core_address separately (supports comma-separated values)
        if (isset($data['ccap_core_address']) && !empty($data['ccap_core_address'])) {
            $addresses = array_map('trim', explode(',', $data['ccap_core_address']));
            foreach ($addresses as $address) {
                if (!preg_match($ipv6Regex, $address)) {
                    $errors[] = "Invalid IPv6 address format in ccap_core_address: $address";
                }
            }
        }
    
        // If there are any validation errors, return them
        if (!empty($errors)) {
            return ['success' => false, 'details' => $errors];
        }
    
        return true;
    }
    
    public function getAllSubnets()
    {
        error_log("DHCPController: ====== Starting getAllSubnets ======");
        try {
            $subnets = $this->subnetModel->getEnrichedSubnets();
            error_log("DHCPController: Received " . count($subnets) . " subnets from getEnrichedSubnets");
            error_log("DHCPController: Subnet data: " . json_encode($subnets));
            
            // Set proper JSON header
            header('Content-Type: application/json');
            
            // Return the array directly as the frontend expects
            echo json_encode($subnets, JSON_PRETTY_PRINT);
    
        } catch (\Exception $e) {
            error_log("DHCPController: ====== ERROR in getAllSubnets ======");
            error_log("DHCPController: Exception message: " . $e->getMessage());
            error_log("DHCPController: Stack trace: " . $e->getTraceAsString());
            
            // Set error status code
            http_response_code(500);
            
            // Return error response in JSON format
            header('Content-Type: application/json');
            echo json_encode([
                'error' => $e->getMessage()
            ]);
        }
        error_log("DHCPController: ====== Completed getAllSubnets ======");
    }
    

    


    public function create()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            // Validate required fields
            $validation = $this->validateSubnetData($data);
            if ($validation !== true) {
                http_response_code(400);
                echo json_encode([
                    'success' => false, 
                    'error' => 'Validation failed',
                    'details' => $validation
                ]);
                return;
            }

            $result = $this->subnetModel->createSubnet($data);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Subnet created successfully',
                'id' => $result
            ]);

        } catch (\Exception $e) {
            error_log("Error in DHCPController::create: " . $e->getMessage());
            
            // Check if it's a vendor options missing error
            if (strpos($e->getMessage(), 'VENDOR_OPTIONS_MISSING') === 0) {
                http_response_code(400);
                $message = str_replace('VENDOR_OPTIONS_MISSING: ', '', $e->getMessage());
                echo json_encode(['success' => false, 'error' => $message]);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
        }
    }


    public function getById($id)
    {
        try {
            $subnet = $this->subnetModel->getSubnetById($id);

            if (!$subnet) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Subnet not found']);
                return;
            }

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'data' => $subnet]);
        } catch (\Exception $e) {
            error_log("Error in DHCPController::getById: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Internal server error']);
        }
    }

    
    public function update()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            // Validate required fields
            $validation = $this->validateSubnetData($data);
            if ($validation !== true) {
                http_response_code(400);
                echo json_encode([
                    'success' => false, 
                    'error' => 'Validation failed',
                    'details' => $validation
                ]);
                return;
            }

            $result = $this->subnetModel->updateSubnet($data);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Subnet created successfully',
                'id' => $result
            ]);

        } catch (\Exception $e) {
            error_log("Error in DHCPController::create: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Internal server error']);
        }
    }

    public function delete($id)
    {
        try {
            error_log("DHCPController: Attempting to delete subnet with ID: $id");
            
            $result = $this->subnetModel->deleteSubnet($id);
            
            error_log("DHCPController: Delete result: " . ($result ? 'success' : 'failed'));
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Subnet deleted successfully'
            ]);
            
        } catch (\Exception $e) {
            error_log("Error in DHCPController::delete: " . $e->getMessage());
            error_log("Error trace: " . $e->getTraceAsString());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false, 
                'error' => $e->getMessage(),
                'message' => 'Failed to delete subnet: ' . $e->getMessage()
            ]);
        }
    }

    public function deleteOrphaned($keaSubnetId)
    {
        try {
            error_log("DHCPController: Attempting to delete orphaned subnet with Kea ID: $keaSubnetId");
            
            // Use a custom method in the DHCP model to delete the orphaned subnet
            $result = $this->subnetModel->deleteOrphanedSubnetFromKea($keaSubnetId);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Orphaned subnet deleted successfully from Kea'
            ]);
            
        } catch (\Exception $e) {
            error_log("Error in DHCPController::deleteOrphaned: " . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function linkOrphanedSubnet()
    {
        try {
            // Get JSON input
            $rawData = file_get_contents("php://input");
            $data = json_decode($rawData, true);

            if (!isset($data['kea_subnet_id']) || !isset($data['bvi_interface_id'])) {
                throw new \Exception('Missing required fields: kea_subnet_id and bvi_interface_id');
            }

            $keaSubnetId = $data['kea_subnet_id'];
            $bviInterfaceId = $data['bvi_interface_id'];

            error_log("DHCPController: Linking orphaned subnet (Kea ID: $keaSubnetId) to BVI interface ID: $bviInterfaceId");

            // Get database connection
            $db = Database::getInstance();

            // Get BVI interface details
            $stmt = $db->prepare("
                SELECT b.*, s.name as switch_name
                FROM cin_switch_bvi_interfaces b
                JOIN cin_switches s ON b.switch_id = s.id
                WHERE b.id = ?
            ");
            $stmt->execute([$bviInterfaceId]);
            $bvi = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$bvi) {
                throw new \Exception('BVI interface not found');
            }

            // Get subnet details from Kea
            $subnets = $this->subnetModel->getAllSubnetsfromKEA();
            $subnet = null;
            foreach ($subnets as $s) {
                if ($s['id'] == $keaSubnetId) {
                    $subnet = $s;
                    break;
                }
            }

            if (!$subnet) {
                throw new \Exception('Subnet not found in Kea');
            }

            // Extract pool information
            $poolStart = null;
            $poolEnd = null;
            if (!empty($subnet['pools'])) {
                $firstPool = $subnet['pools'][0]['pool'];
                // Parse "start - end" format
                if (preg_match('/^(.+?)\s*-\s*(.+?)$/', $firstPool, $matches)) {
                    $poolStart = trim($matches[1]);
                    $poolEnd = trim($matches[2]);
                }
            }

            // Get CCAP core from options
            $ccapCore = null;
            if (!empty($subnet['option-data'])) {
                foreach ($subnet['option-data'] as $option) {
                    if (($option['name'] ?? '') === 'ccap-core') {
                        $ccapCore = $option['data'];
                        break;
                    }
                }
            }

            // Insert/update the link in cin_bvi_dhcp_core table
            $sql = "REPLACE INTO cin_bvi_dhcp_core (
                        id,
                        switch_id,
                        kea_subnet_id,
                        interface_number,
                        ipv6_address,
                        start_address,
                        end_address,
                        ccap_core
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                $bvi['id'],
                $bvi['switch_id'],
                $keaSubnetId,
                $bvi['interface_number'],
                $bvi['ipv6_address'],
                $poolStart,
                $poolEnd,
                $ccapCore
            ]);

            error_log("DHCPController: Successfully linked subnet to BVI interface");

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Subnet linked to BVI interface successfully'
            ]);

        } catch (\Exception $e) {
            error_log("Error in DHCPController::linkOrphanedSubnet: " . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function createCINAndLink()
    {
        try {
            // Get JSON input
            $rawData = file_get_contents("php://input");
            $data = json_decode($rawData, true);

            if (!isset($data['kea_subnet_id']) || !isset($data['cin_name']) || !isset($data['bvi_ipv6'])) {
                throw new \Exception('Missing required fields: kea_subnet_id, cin_name, and bvi_ipv6');
            }

            $keaSubnetId = $data['kea_subnet_id'];
            $cinName = $data['cin_name'];
            $bviIpv6 = $data['bvi_ipv6'];

            error_log("DHCPController: Creating CIN '$cinName' with BVI100 and linking to subnet ID: $keaSubnetId");

            // Get database connection
            $db = Database::getInstance();
            $cinSwitchModel = new \App\Models\CinSwitch($db);

            // Get subnet details from Kea (read-only, via API)
            $subnets = $this->subnetModel->getAllSubnetsfromKEA();
            $subnet = null;
            foreach ($subnets as $s) {
                if ($s['id'] == $keaSubnetId) {
                    $subnet = $s;
                    break;
                }
            }

            if (!$subnet) {
                throw new \Exception('Subnet not found in Kea');
            }

            // Log the full subnet data to see what Kea returned
            error_log("DHCPController: Full subnet data from Kea: " . json_encode($subnet, JSON_PRETTY_PRINT));

            // Extract pool information from subnet data (already retrieved from Kea API)
            $poolStart = null;
            $poolEnd = null;
            if (!empty($subnet['pools'])) {
                $firstPool = $subnet['pools'][0]['pool'];
                error_log("DHCPController: Raw pool data: " . $firstPool);
                // Parse "start - end" format
                if (preg_match('/^(.+?)\s*-\s*(.+?)$/', $firstPool, $matches)) {
                    $poolStart = trim($matches[1]);
                    $poolEnd = trim($matches[2]);
                    error_log("DHCPController: Parsed pool - start: $poolStart, end: $poolEnd");
                } else {
                    error_log("DHCPController: Failed to parse pool format: " . $firstPool);
                }
            } else {
                error_log("DHCPController: No pools found in subnet data");
            }
            
            // Validate pool addresses are not null
            if (!$poolStart || !$poolEnd) {
                throw new \Exception("Failed to extract pool addresses from subnet. Start: " . var_export($poolStart, true) . ", End: " . var_export($poolEnd, true));
            }

            // Get CCAP core from options (already retrieved from Kea API)
            $ccapCore = null;
            if (!empty($subnet['option-data'])) {
                foreach ($subnet['option-data'] as $option) {
                    if (($option['name'] ?? '') === 'ccap-core') {
                        $ccapCore = $option['data'];
                        break;
                    }
                }
            }

            // Check if CIN switch with this name already exists (exclude id=0 as it's invalid)
            $existingSwitchStmt = $db->prepare("SELECT id FROM cin_switches WHERE hostname = ? AND id > 0 ORDER BY id DESC LIMIT 1");
            $existingSwitchStmt->execute([$cinName]);
            $existingSwitch = $existingSwitchStmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($existingSwitch) {
                // Switch already exists, use its ID
                $switchId = $existingSwitch['id'];
                error_log("DHCPController: Using existing CIN switch '$cinName' with ID: $switchId");
            } else {
                // Create new CIN switch in OUR database (not Kea)
                try {
                    $switchId = $cinSwitchModel->createSwitch([
                        'hostname' => $cinName
                    ]);
                    error_log("DHCPController: Created new CIN switch '$cinName' with ID: $switchId");
                } catch (\Exception $e) {
                    error_log("DHCPController: Failed to create CIN switch: " . $e->getMessage());
                    throw new \Exception("Failed to create CIN switch: " . $e->getMessage());
                }
            }
            
            if (!$switchId || $switchId == 0) {
                throw new \Exception("Invalid switch ID returned: " . var_export($switchId, true));
            }

            // Create BVI100 interface in OUR database (not Kea)
            // Store as 0, display adds 100 to show BVI100
            $bviInterfaceId = $cinSwitchModel->createBviInterface($switchId, [
                'interface_number' => 0,
                'ipv6_address' => $bviIpv6
            ]);

            // Link subnet to BVI in OUR database (cin_bvi_dhcp_core table - not Kea)
            // This is OUR mapping table that links Kea subnets to our BVI interfaces
            // Check if this switch+interface already exists
            $checkStmt = $db->prepare("
                SELECT id FROM cin_bvi_dhcp_core 
                WHERE switch_id = ? AND interface_number = ?
            ");
            $checkStmt->execute([$switchId, 0]);
            $existingLink = $checkStmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($existingLink) {
                // Update existing link
                $sql = "UPDATE cin_bvi_dhcp_core 
                        SET kea_subnet_id = ?,
                            ipv6_address = ?,
                            start_address = ?,
                            end_address = ?,
                            ccap_core = ?
                        WHERE id = ?";
                
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $keaSubnetId,
                    $bviIpv6,
                    $poolStart,
                    $poolEnd,
                    $ccapCore,
                    $existingLink['id']
                ]);
            } else {
                // Insert new link (without specifying id, let it auto-increment)
                $sql = "INSERT INTO cin_bvi_dhcp_core (
                            switch_id,
                            kea_subnet_id,
                            interface_number,
                            ipv6_address,
                            start_address,
                            end_address,
                            ccap_core
                        ) VALUES (?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $switchId,
                    $keaSubnetId,
                    0,  // Store as 0, display adds 100 to show BVI100
                    $bviIpv6,
                    $poolStart,
                    $poolEnd,
                    $ccapCore
                ]);
            }

            error_log("DHCPController: Successfully created CIN + BVI100 and linked subnet (no Kea DB writes)");

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'CIN switch with BVI100 created and subnet linked successfully',
                'data' => [
                    'switch_id' => $switchId,
                    'cin_name' => $cinName,
                    'bvi_ipv6' => $bviIpv6
                ]
            ]);

        } catch (\Exception $e) {
            error_log("Error in DHCPController::createCINAndLink: " . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function deleteLease()
    {
        try {
            // Get the raw DELETE data
            $rawData = file_get_contents("php://input");
            $data = json_decode($rawData, true);

            // Extract parameters
            $ipAddress = $data['ip-address'] ?? null;
            $subnetId = $data['subnet-id'] ?? null;

            // Validate parameters
            if (!$ipAddress || !$subnetId) {
                throw new Exception('Subnet ID and IPv6 address are required');
            }

            // Call the model's deleteLease method
            $result = $this->leaseModel->deleteLease($ipAddress);

            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
        } catch (Exception $e) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    

    private function getJsonData(): ?array
    {
        $jsonData = file_get_contents('php://input');
        if (!$jsonData) {
            return null;
        }
        return json_decode($jsonData, true);
    }

    

    public function checkDuplicate()
    {
        try {
            $data = $this->getJsonData();
            if (!$data) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'No data provided'
                ]);
                return;
            }

            $subnet = $data['subnet'] ?? null;
            if (!$subnet) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Subnet is required'
                ]);
                return;
            }

            // Check for duplicate subnet
            $stmt = $this->subnetModel->checkDuplicateSubnet($subnet);
            
            echo json_encode([
                'success' => true,
                'exists' => $stmt->rowCount() > 0
            ]);

        } catch (\Exception $e) {
            error_log("Error in checkDuplicate: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Server error while checking duplicate subnet: ' . $e->getMessage()
            ]);
        }
    }
    
    public function updateDedicatedSubnet()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $_SERVER['REQUEST_URI'];
            preg_match('/\/api\/dhcp\/dedicated-subnets\/(\d+)/', $id, $matches);
            $subnetId = $matches[1] ?? null;
            
            if (!$subnetId) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Invalid subnet ID']);
                return;
            }
            
            if (empty($data['name'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Name is required']);
                return;
            }
            
            $db = \App\Database\Database::getInstance();
            
            // Get current subnet details from Kea (this is the source of truth)
            $allSubnets = $this->subnetModel->getAllSubnetsfromKEA();
            $keaSubnet = null;
            foreach ($allSubnets as $s) {
                if ($s['id'] == $subnetId) {
                    $keaSubnet = $s;
                    break;
                }
            }
            
            if (!$keaSubnet) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Subnet not found in Kea']);
                return;
            }
            
            // Get pool info
            $poolStart = null;
            $poolEnd = null;
            if (isset($keaSubnet['pools'][0]['pool'])) {
                $poolParts = explode('-', $keaSubnet['pools'][0]['pool']);
                $poolStart = trim($poolParts[0] ?? '');
                $poolEnd = trim($poolParts[1] ?? '');
            }
            
            // Get relay
            $relay = $keaSubnet['relay']['ip-addresses'][0] ?? '::1';
            
            // Check if record exists in database
            $stmt = $db->prepare("SELECT id FROM dedicated_subnets WHERE kea_subnet_id = ?");
            $stmt->execute([$subnetId]);
            $exists = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($exists) {
                // Update existing record
                $stmt = $db->prepare("UPDATE dedicated_subnets SET name = ?, ccap_core = ?, subnet = ?, pool_start = ?, pool_end = ? WHERE kea_subnet_id = ?");
                $stmt->execute([
                    $data['name'], 
                    $data['ccap_core_address'] ?? null,
                    $keaSubnet['subnet'],
                    $poolStart,
                    $poolEnd,
                    $subnetId
                ]);
            } else {
                // Insert new record
                $stmt = $db->prepare("INSERT INTO dedicated_subnets (name, kea_subnet_id, subnet, pool_start, pool_end, ccap_core) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $data['name'], 
                    $subnetId,
                    $keaSubnet['subnet'],
                    $poolStart,
                    $poolEnd,
                    $data['ccap_core_address'] ?? null
                ]);
            }
            
            // Build update data for existing updateSubnet method
            $updateData = [
                'subnet_id' => $subnetId,
                'subnet' => $keaSubnet['subnet'],
                'pool_start' => $poolStart,
                'pool_end' => $poolEnd,
                'relay_address' => $relay,
                'ccap_core_address' => $data['ccap_core_address'] ?? null,
                'valid_lifetime' => intval($data['valid_lifetime'] ?? 7200),
                'preferred_lifetime' => intval($data['preferred_lifetime'] ?? 3600),
                'renew_timer' => intval($data['renew_timer'] ?? 1000),
                'rebind_timer' => intval($data['rebind_timer'] ?? 2000),
                'bvi_interface_id' => 0 // Dummy value for dedicated subnets
            ];
            
            // Use the existing updateSubnet method
            $this->subnetModel->updateSubnet($updateData);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => 'Subnet updated successfully'
            ]);
            
        } catch (\Exception $e) {
            error_log("Error in updateDedicatedSubnet: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }}