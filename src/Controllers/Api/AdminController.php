<?php

namespace App\Controllers\Api;

use PDO;

class AdminController
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Export Kea configuration
     * GET /api/admin/export/kea-config
     */
    public function exportKeaConfig()
    {
        // This will be implemented to generate kea-dhcp6.conf
        $this->jsonResponse([
            'success' => false,
            'message' => 'Export functionality coming soon'
        ]);
    }

    /**
     * Import Kea configuration
     * POST /api/admin/import/kea-config
     */
    public function importKeaConfig()
    {
        if (!isset($_FILES['config'])) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'No file uploaded'
            ], 400);
            return;
        }

        $file = $_FILES['config'];
        $tmpPath = $file['tmp_name'];
        
        // Log file details
        error_log("Import file: " . $file['name'] . " (" . $file['size'] . " bytes)");
        error_log("Temp path: " . $tmpPath);

        // Read file content to check if it's valid
        $content = file_get_contents($tmpPath);
        error_log("File content length: " . strlen($content));
        error_log("First 200 chars: " . substr($content, 0, 200));

        // Call the import script
        $scriptPath = BASE_PATH . '/scripts/import_kea_config.php';
        $output = [];
        $returnCode = 0;

        $command = "php $scriptPath " . escapeshellarg($tmpPath) . " 2>&1";
        error_log("Executing: $command");
        
        exec($command, $output, $returnCode);
        
        error_log("Return code: $returnCode");
        error_log("Output lines: " . count($output));

        if ($returnCode === 0) {
            // Parse output for statistics
            $outputText = implode("\n", $output);
            
            // Log full output for debugging
            error_log("=== Full Import Output ===");
            error_log($outputText);
            error_log("=========================");
            
            // Try to extract stats from output
            preg_match('/Subnets:\s*(\d+)\s*imported,\s*(\d+)\s*skipped/', $outputText, $subnetMatches);
            preg_match('/Reservations:\s*(\d+)\s*imported,\s*(\d+)\s*skipped/', $outputText, $resMatches);
            preg_match('/Options:\s*(\d+)\s*imported,\s*(\d+)\s*skipped/', $outputText, $optMatches);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Configuration imported successfully',
                'debug_output' => $outputText, // Add full output for debugging
                'stats' => [
                    'subnets' => [
                        'imported' => isset($subnetMatches[1]) ? (int)$subnetMatches[1] : 0,
                        'skipped' => isset($subnetMatches[2]) ? (int)$subnetMatches[2] : 0
                    ],
                    'reservations' => [
                        'imported' => isset($resMatches[1]) ? (int)$resMatches[1] : 0,
                        'skipped' => isset($resMatches[2]) ? (int)$resMatches[2] : 0
                    ],
                    'options' => [
                        'imported' => isset($optMatches[1]) ? (int)$optMatches[1] : 0,
                        'skipped' => isset($optMatches[2]) ? (int)$optMatches[2] : 0
                    ]
                ],
                'output' => $outputText
            ]);
        } else {
            $outputText = implode("\n", $output);
            error_log("Import failed: " . $outputText);
            
            $this->jsonResponse([
                'success' => false,
                'message' => 'Import failed - see details below',
                'error' => $outputText,
                'details' => [
                    'filename' => $file['name'],
                    'size' => $file['size'],
                    'returnCode' => $returnCode
                ]
            ], 500);
        }
    }

    /**
     * Import Kea reservations from config file
     * POST /api/admin/import/kea-reservations
     */
    public function importKeaReservations()
    {
        if (!isset($_FILES['config'])) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'No file uploaded'
            ], 400);
            return;
        }

        $file = $_FILES['config'];
        $tmpPath = $file['tmp_name'];
        
        error_log("Importing reservations from file: " . $file['name']);

        // Call the import script
        $scriptPath = BASE_PATH . '/scripts/import_kea_reservations.php';
        $output = [];
        $returnCode = 0;

        $command = "php $scriptPath " . escapeshellarg($tmpPath) . " 2>&1";
        error_log("Executing: $command");
        
        exec($command, $output, $returnCode);
        
        $outputText = implode("\n", $output);
        error_log("Return code: $returnCode");
        error_log("Output: " . $outputText);

        if ($returnCode === 0) {
            // Parse stats from output
            preg_match('/Total reservations found:\s*(\d+)/', $outputText, $totalMatch);
            preg_match('/Added \(new\):\s*([\d.]+)/', $outputText, $addedMatch);
            preg_match('/Updated \(existing\):\s*([\d.]+)/', $outputText, $updatedMatch);
            preg_match('/Errors:\s*([\d.]+)/', $outputText, $errorMatch);
            
            $added = isset($addedMatch[1]) ? (int)$addedMatch[1] : 0;
            $updated = isset($updatedMatch[1]) ? (int)$updatedMatch[1] : 0;
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Reservations imported successfully',
                'stats' => [
                    'reservations' => [
                        'imported' => $added,
                        'updated' => $updated,
                        'skipped' => 0
                    ]
                ],
                'details' => $outputText
            ]);
        } else {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to import reservations',
                'error' => $outputText
            ], 500);
        }
    }

    /**
     * Preview Kea configuration import
     * POST /api/admin/import/kea-config/preview
     */
    public function previewKeaConfig()
    {
        if (!isset($_FILES['config'])) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'No file uploaded'
            ], 400);
            return;
        }

        try {
            $file = $_FILES['config'];
            $content = file_get_contents($file['tmp_name']);
            
            // Extract CIN/Router names from comments BEFORE removing comments
            // Pattern: ##### CCAP-NAME - ROUTER-NAME #####
            $cinNameMap = [];
            if (preg_match_all('/^#####\s*(.+?)\s*-\s*(.+?)\s*#####/m', $content, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $ccapName = trim($match[1]);
                    $routerName = trim($match[2]);
                    
                    // Try to find the subnet that follows this comment
                    // Look for the next subnet definition after this comment
                    $commentPos = strpos($content, $match[0]);
                    $nextSubnetPattern = '/"subnet"\s*:\s*"([^"]+)"/';
                    if (preg_match($nextSubnetPattern, substr($content, $commentPos), $subnetMatch)) {
                        $cinNameMap[$subnetMatch[1]] = [
                            'ccap' => $ccapName,
                            'router' => $routerName
                        ];
                    }
                }
            }
            
            error_log("Extracted CIN name map: " . json_encode($cinNameMap, JSON_PRETTY_PRINT));
            
            // Parse JSON (remove comments now)
            $configJson = preg_replace('/\/\*.*?\*\//s', '', $content);
            $configJson = preg_replace('/^\s*#.*$/m', '', $configJson);
            $configJson = preg_replace('/^\s*\/\/.*$/m', '', $configJson);
            $configJson = preg_replace('/#.*$/m', '', $configJson);
            $configJson = preg_replace('/,(\s*[}\]])/', '$1', $configJson);
            $configJson = preg_replace('/.*Lines \d+-\d+ omitted.*\n?/', '', $configJson);
            
            $config = json_decode($configJson, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Invalid JSON: " . json_last_error_msg());
            }
            
            if (!isset($config['Dhcp6']['subnet6'])) {
                throw new \Exception("No subnets found in configuration");
            }
            
            $subnets = [];
            foreach ($config['Dhcp6']['subnet6'] as $subnet) {
                // Extract pool
                $pool = null;
                if (isset($subnet['pools']) && !empty($subnet['pools'])) {
                    $pool = $subnet['pools'][0]['pool'];
                }
                
                // Extract relay
                $relay = null;
                if (isset($subnet['relay']['ip-addresses']) && !empty($subnet['relay']['ip-addresses'])) {
                    $relay = $subnet['relay']['ip-addresses'][0];
                }
                
                // Extract CCAP core
                $ccapCore = null;
                if (isset($subnet['option-data'])) {
                    error_log("Checking option-data for subnet {$subnet['subnet']}: " . json_encode($subnet['option-data']));
                    foreach ($subnet['option-data'] as $option) {
                        if (($option['name'] ?? null) === 'ccap-core' || ($option['code'] ?? null) == 61) {
                            $ccapCore = $option['data'];
                            error_log("Found CCAP core for {$subnet['subnet']}: {$ccapCore}");
                            break;
                        }
                    }
                    if (!$ccapCore) {
                        error_log("No CCAP core found in option-data for {$subnet['subnet']}");
                    }
                } else {
                    error_log("No option-data found for subnet {$subnet['subnet']}");
                }
                
                $subnets[] = [
                    'id' => $subnet['id'],
                    'subnet' => $subnet['subnet'],
                    'pool' => $pool,
                    'relay' => $relay,
                    'ccap_core' => $ccapCore,
                    'valid_lifetime' => $subnet['valid-lifetime'] ?? 7200,
                    'preferred_lifetime' => $subnet['preferred-lifetime'] ?? 3600,
                    'reservations_count' => isset($subnet['reservations']) ? count($subnet['reservations']) : 0,
                    'suggested_cin_name' => $cinNameMap[$subnet['subnet']]['router'] ?? null,
                    'suggested_ccap_name' => $cinNameMap[$subnet['subnet']]['ccap'] ?? null
                ];
            }
            
            // Check which subnets already exist in Kea
            $dhcpModel = new \App\Models\DHCP($this->db);
            $existingSubnets = $dhcpModel->getAllSubnetsfromKEA();
            $existingSubnetIds = array_column($existingSubnets, 'id');
            
            // Mark subnets that already exist
            foreach ($subnets as &$subnet) {
                $subnet['exists'] = in_array($subnet['id'], $existingSubnetIds);
            }
            unset($subnet); // Break reference
            
            // Get all available BVI interfaces for linking
            $stmt = $this->db->query("
                SELECT 
                    b.id,
                    b.interface_number,
                    b.ipv6_address,
                    s.hostname as switch_hostname,
                    s.id as switch_id
                FROM cin_switch_bvi_interfaces b
                JOIN cin_switches s ON b.switch_id = s.id
                ORDER BY s.hostname, b.interface_number
            ");
            $availableBvis = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Format BVI options for dropdown
            $bviOptions = array_map(function($bvi) {
                $displayNumber = $bvi['interface_number'] + 100;
                return [
                    'id' => $bvi['id'],
                    'label' => "{$bvi['switch_hostname']} - BVI{$displayNumber} ({$bvi['ipv6_address']})",
                    'switch_id' => $bvi['switch_id'],
                    'switch_hostname' => $bvi['switch_hostname'],
                    'interface_number' => $bvi['interface_number'],
                    'ipv6_address' => $bvi['ipv6_address']
                ];
            }, $availableBvis);
            
            $this->jsonResponse([
                'success' => true,
                'subnets' => $subnets,
                'total' => count($subnets),
                'available_bvis' => $bviOptions
            ]);
            
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Execute Kea configuration import with user selections
     * POST /api/admin/import/kea-config/execute
     */
    public function executeKeaImport()
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['subnets']) || empty($input['subnets'])) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'No subnets provided'
            ], 400);
            return;
        }
        
        $imported = 0;
        $skipped = 0;
        $errors = 0;
        $details = [];
        
        // Get all active Kea servers from database
        $stmt = $this->db->prepare("SELECT id, name, api_url FROM kea_servers WHERE is_active = 1 ORDER BY priority");
        $stmt->execute();
        $keaServers = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        if (empty($keaServers)) {
            throw new \Exception("No active Kea servers configured. Please configure a Kea server in the database.");
        }
        
        error_log("Found " . count($keaServers) . " active Kea servers");
        
        $cinSwitchModel = new \App\Models\CinSwitch($this->db);
        $dhcpModel = new \App\Models\DHCP($this->db);
        
        foreach ($input['subnets'] as $config) {
            try {
                $subnet = $config['subnet'];
                $action = $config['action'];
                
                // Skip completely if action is skip - don't create in Kea at all
                if ($action === 'skip') {
                    $skipped++;
                    $details[] = "⊘ Skipped {$subnet['subnet']} (already exists)";
                    continue;
                }
                
                // STEP 1: Create subnet in Kea WITHOUT pools first
                $keaSubnet = [
                    "subnet" => $subnet['subnet'],
                    "id" => $subnet['id'],
                    "client-class" => "RPD"
                    // NO POOLS YET - will be added separately
                ];
                
                // Add relay if available
                if ($subnet['relay']) {
                    $keaSubnet['relay'] = ["ip-addresses" => [$subnet['relay']]];
                }
                
                // Add CCAP core option if available
                if ($subnet['ccap_core']) {
                    $keaSubnet['option-data'] = [[
                        "name" => "ccap-core",
                        "code" => 61,
                        "space" => "vendor-4491",
                        "csv-format" => true,
                        "data" => $subnet['ccap_core'],
                        "always-send" => true
                    ]];
                }
                
                // Add lifetimes
                $keaSubnet['valid-lifetime'] = $subnet['valid_lifetime'];
                $keaSubnet['preferred-lifetime'] = $subnet['preferred_lifetime'];
                
                // Log what we're sending to Kea for debugging
                error_log("=== STEP 1: Creating Subnet (without pools) ===");
                error_log("Subnet: " . $subnet['subnet']);
                error_log("Kea Subnet Data: " . json_encode($keaSubnet, JSON_PRETTY_PRINT));
                
                // Send subnet creation to ALL Kea servers
                $data = [
                    "command" => 'subnet6-add',
                    "service" => ['dhcp6'],
                    "arguments" => [
                        "subnet6" => [$keaSubnet]
                    ]
                ];
                
                $keaErrors = [];
                foreach ($keaServers as $keaServer) {
                    error_log("Sending to Kea server: {$keaServer['name']} ({$keaServer['api_url']})");
                    
                    $ch = curl_init($keaServer['api_url']);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curlError = curl_error($ch);
                    curl_close($ch);
                    
                    if ($curlError) {
                        $error = "Failed to connect to {$keaServer['name']}: " . $curlError;
                        error_log($error);
                        $keaErrors[] = $error;
                        continue;
                    }
                    
                    if ($httpCode !== 200) {
                        $error = "{$keaServer['name']} returned HTTP " . $httpCode;
                        error_log($error . ": " . $response);
                        $keaErrors[] = $error;
                        continue;
                    }
                    
                    $keaResponse = json_decode($response, true);
                    
                    error_log("=== {$keaServer['name']} Response ===");
                    error_log("HTTP Code: " . $httpCode);
                    error_log("Response: " . json_encode($keaResponse, JSON_PRETTY_PRINT));
                    
                    if (!$keaResponse) {
                        $error = "{$keaServer['name']} returned invalid JSON";
                        error_log($error . ". Raw response: " . $response);
                        $keaErrors[] = $error;
                        continue;
                    }
                    
                    if (!isset($keaResponse[0]['result']) || $keaResponse[0]['result'] !== 0) {
                        $errorText = isset($keaResponse[0]['text']) ? $keaResponse[0]['text'] : 'Unknown error';
                        $error = "{$keaServer['name']}: " . $errorText;
                        error_log($error);
                        $keaErrors[] = $error;
                    }
                }
                
                // If ANY server failed, throw exception with all errors
                if (!empty($keaErrors)) {
                    throw new \Exception("Kea subnet creation failed on " . count($keaErrors) . " server(s): " . implode("; ", $keaErrors));
                }
                
                // STEP 2: Now add the pool to the subnet (separate API call)
                if (!empty($subnet['pool'])) {
                    error_log("=== STEP 2: Adding Pool to Subnet ===");
                    error_log("Subnet ID: " . $subnet['id']);
                    error_log("Pool: " . $subnet['pool']);
                    
                    // Convert "start-end" to "start - end" (with spaces)
                    $pool = str_replace('-', ' - ', $subnet['pool']);
                    
                    // Build subnet config with ONLY the pool (delta update)
                    // Don't include relay or options - they're already set from step 1
                    $completeSubnet = [
                        "id" => $subnet['id'],
                        "subnet" => $subnet['subnet'],
                        "pools" => [["pool" => $pool]]
                    ];
                    
                    $poolData = [
                        "command" => 'subnet6-delta-add',
                        "service" => ['dhcp6'],
                        "arguments" => [
                            "subnet6" => [$completeSubnet]
                        ]
                    ];
                    
                    error_log("Pool Data: " . json_encode($poolData, JSON_PRETTY_PRINT));
                    
                    // Send pool update to ALL Kea servers
                    $poolErrors = [];
                    foreach ($keaServers as $keaServer) {
                        error_log("Sending pool to Kea server: {$keaServer['name']}");
                        
                        $ch2 = curl_init($keaServer['api_url']);
                        curl_setopt($ch2, CURLOPT_POST, 1);
                        curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($poolData));
                        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                        
                        $poolResponse = curl_exec($ch2);
                        $poolHttpCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
                        $poolCurlError = curl_error($ch2);
                        curl_close($ch2);
                        
                        if ($poolCurlError || $poolHttpCode !== 200) {
                            $poolErrors[] = "{$keaServer['name']}: " . ($poolCurlError ?: "HTTP $poolHttpCode");
                            continue;
                        }
                        
                        $poolKeaResponse = json_decode($poolResponse, true);
                        
                        if (!$poolKeaResponse || !isset($poolKeaResponse[0]['result']) || $poolKeaResponse[0]['result'] !== 0) {
                            $errorText = isset($poolKeaResponse[0]['text']) ? $poolKeaResponse[0]['text'] : 'Unknown error';
                            $poolErrors[] = "{$keaServer['name']}: " . $errorText;
                        }
                    }
                    
                    if (!empty($poolErrors)) {
                        throw new \Exception("Pool creation failed on " . count($poolErrors) . " server(s): " . implode("; ", $poolErrors));
                    }
                    
                    error_log("=== STEP 2 Complete: Pool added on all servers ===");
                }
                
                // Now handle the action-specific logic
                if ($action === 'create') {
                    // CIN switch creation is OPTIONAL during import
                    // Users can link to CIN switches later using the "Create CIN + BVI" button
                    if (!empty($config['cin_name'])) {
                        // CIN name provided - create CIN switch and link it
                        
                        // Check if CIN switch already exists by hostname
                        $checkStmt = $this->db->prepare("SELECT id FROM cin_switches WHERE hostname = ? LIMIT 1");
                        $checkStmt->execute([$config['cin_name']]);
                        $existingSwitch = $checkStmt->fetch(\PDO::FETCH_ASSOC);
                        
                        if ($existingSwitch) {
                            // Switch already exists - check if it has BVI100 (stored as interface_number = 0)
                            $bviCheckStmt = $this->db->prepare("
                                SELECT id FROM cin_switch_bvi_interfaces 
                                WHERE switch_id = ? AND interface_number = 0
                            ");
                            $bviCheckStmt->execute([$existingSwitch['id']]);
                            $existingBVI = $bviCheckStmt->fetch(\PDO::FETCH_ASSOC);

                            
                            if ($existingBVI) {
                                throw new \Exception("CIN switch '{$config['cin_name']}' with BVI100 already exists. Use 'Skip' action to create subnet only, or 'Link to Existing BVI' to link to existing BVI.");
                            }
                            
                            $switchId = $existingSwitch['id'];
                        } else {
                            // Create new CIN switch
                            $switchId = $cinSwitchModel->createSwitch([
                                'hostname' => $config['cin_name']
                            ]);
                        }
                        
                        // Create BVI100 interface (stored as 0, displayed as BVI100)
                        $bviId = $cinSwitchModel->createBviInterface($switchId, [
                            'interface_number' => 0, // Store as 0, display shows BVI + (100 + 0) = BVI100
                            'ipv6_address' => $subnet['relay'] // Use relay address as BVI address
                        ]);
                        
                        // Link subnet to BVI in cin_bvi_dhcp_core table
                        // Parse pool to get start/end addresses
                        $poolStart = null;
                        $poolEnd = null;
                        if ($subnet['pool'] && preg_match('/^(.+?)\s*-\s*(.+?)$/', $subnet['pool'], $matches)) {
                            $poolStart = trim($matches[1]);
                            $poolEnd = trim($matches[2]);
                        }
                        
                        $stmt = $this->db->prepare("
                            INSERT INTO cin_bvi_dhcp_core 
                            (bvi_interface_id, switch_id, kea_subnet_id, interface_number, ipv6_address, start_address, end_address, ccap_core)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $bviId,
                            $switchId,
                            $subnet['id'],
                            0, // Store as 0 for BVI100
                            $subnet['relay'],
                            $poolStart,
                            $poolEnd,
                            $subnet['ccap_core'] ?? null
                        ]);
                        
                        $result['message'] = "✓ Created subnet with CIN switch '{$config['cin_name']}'";
                    } else {
                        // No CIN name provided - subnet created in Kea only
                        // User can link to CIN switch later via "Create CIN + BVI" button
                        $result['message'] = "✓ Created subnet (no CIN link - add later if needed)";
                    }
                    
                } elseif ($action === 'link') {
                    // Link to existing BVI
                    if (empty($config['bvi_id'])) {
                        throw new \Exception("BVI interface ID is required for linking");
                    }
                    
                    // Get BVI details
                    $bviStmt = $this->db->prepare("SELECT * FROM cin_switch_bvi_interfaces WHERE id = ?");
                    $bviStmt->execute([$config['bvi_id']]);
                    $bvi = $bviStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$bvi) {
                        throw new \Exception("BVI interface not found");
                    }
                    
                    // Parse pool
                    $poolStart = null;
                    $poolEnd = null;
                    if ($subnet['pool'] && preg_match('/^(.+?)\s*-\s*(.+?)$/', $subnet['pool'], $matches)) {
                        $poolStart = trim($matches[1]);
                        $poolEnd = trim($matches[2]);
                    }
                    
                    // Link subnet
                    $stmt = $this->db->prepare("
                        INSERT INTO cin_bvi_dhcp_core 
                        (bvi_interface_id, switch_id, kea_subnet_id, interface_number, ipv6_address, start_address, end_address, ccap_core)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $config['bvi_id'], // Add the BVI interface ID
                        $bvi['switch_id'],
                        $subnet['id'],
                        $bvi['interface_number'],
                        $bvi['ipv6_address'],
                        $poolStart,
                        $poolEnd,
                        $subnet['ccap_core']
                    ]);
                    
                    $details[] = "✓ Linked subnet {$subnet['subnet']} to existing BVI{$bvi['interface_number']}";
                } elseif ($action === 'dedicated') {
                    // Create as dedicated subnet - store with name in dedicated_subnets table
                    if (empty($config['dedicated_name'])) {
                        throw new \Exception("Name is required for dedicated subnets");
                    }
                    
                    // Parse pool
                    $poolStart = null;
                    $poolEnd = null;
                    if ($subnet['pool'] && preg_match('/^(.+?)\s*-\s*(.+?)$/', $subnet['pool'], $matches)) {
                        $poolStart = trim($matches[1]);
                        $poolEnd = trim($matches[2]);
                    }
                    
                    // Store in dedicated_subnets table (without description)
                    $stmt = $this->db->prepare("
                        INSERT INTO dedicated_subnets 
                        (name, kea_subnet_id, subnet, pool_start, pool_end, ccap_core)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $config['dedicated_name'],
                        $subnet['id'],
                        $subnet['subnet'],
                        $poolStart,
                        $poolEnd,
                        $subnet['ccap_core'] ?? null
                    ]);
                    
                    $details[] = "✓ Created dedicated subnet '{$config['dedicated_name']}' ({$subnet['subnet']})";
                }
                
                $imported++;
                
            } catch (\Exception $e) {
                $errors++;
                $details[] = "✗ Error with {$subnet['subnet']}: " . $e->getMessage();
                error_log("Import error for subnet {$subnet['subnet']}: " . $e->getMessage());
            }
        }
        
        $this->jsonResponse([
            'success' => true,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'details' => $details
        ]);
    }

    /**
     * Backup Kea database
     * GET /api/admin/backup/kea-database
     */
    public function backupKeaDatabase()
    {
        $filename = 'kea-db-' . date('Y-m-d-His') . '.sql';
        $filepath = BASE_PATH . '/backups/' . $filename;

        // Create backups directory if not exists
        if (!file_exists(BASE_PATH . '/backups')) {
            mkdir(BASE_PATH . '/backups', 0755, true);
        }

        // Get database credentials from env
        $dbHost = getenv('DB_HOST') ?: 'localhost';
        $dbName = getenv('DB_NAME') ?: 'kea_db';
        $dbUser = getenv('DB_USER') ?: 'kea_db_user';
        $dbPass = getenv('DB_PASSWORD') ?: '';

        // Execute mysqldump
        $command = sprintf(
            "mysqldump -h %s -u %s -p%s --skip-ssl %s > %s 2>&1",
            escapeshellarg($dbHost),
            escapeshellarg($dbUser),
            escapeshellarg($dbPass),
            escapeshellarg($dbName),
            escapeshellarg($filepath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode === 0 && file_exists($filepath)) {
            // Clean up old backups (keep last 7)
            $this->cleanupOldBackups('kea-db', 7);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Backup created successfully on server',
                'filename' => $filename,
                'size' => $this->formatFileSize(filesize($filepath)),
                'path' => '/backups/' . $filename
            ]);
        } else {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Backup failed: ' . implode("\n", $output)
            ], 500);
        }
    }

    /**
     * Backup Kea leases
     * GET /api/admin/backup/kea-leases
     */
    public function backupKeaLeases()
    {
        try {
            $filename = 'kea-leases-' . date('Y-m-d-His') . '.json';
            
            // Get first active Kea server from database
            $stmt = $this->db->prepare("SELECT api_url FROM kea_servers WHERE is_active = 1 ORDER BY priority LIMIT 1");
            $stmt->execute();
            $keaServer = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$keaServer) {
                throw new \Exception("No active Kea servers configured");
            }
            
            $keaApiUrl = $keaServer['api_url'];
            
            // Get all subnets first
            $subnetData = [
                'command' => 'subnet6-list',
                'service' => ['dhcp6']
            ];
            
            $ch = curl_init($keaApiUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($subnetData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            $subnetResponse = json_decode($response, true);
            
            if (!$subnetResponse || !isset($subnetResponse[0]['arguments']['subnets'])) {
                throw new \Exception("Failed to get subnets from Kea");
            }
            
            $subnets = $subnetResponse[0]['arguments']['subnets'];
            $allLeases = [];
            
            // Get leases from each subnet using lease6-get-page (runtime leases)
            foreach ($subnets as $subnet) {
                $subnetId = $subnet['id'];
                $from = "start";
                $limit = 1000;
                
                while (true) {
                    $leaseData = [
                        'command' => 'lease6-get-page',
                        'service' => ['dhcp6'],
                        'arguments' => [
                            'from' => $from,
                            'limit' => $limit,
                            'subnet-id' => $subnetId
                        ]
                    ];
                    
                    $ch = curl_init($keaApiUrl);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($leaseData));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    
                    $leaseResponse = curl_exec($ch);
                    curl_close($ch);
                    
                    $leaseResult = json_decode($leaseResponse, true);
                    
                    error_log("Lease query for subnet $subnetId: " . $leaseResponse);
                    
                    if ($leaseResult && isset($leaseResult[0]['result']) && $leaseResult[0]['result'] === 0) {
                        $leases = $leaseResult[0]['arguments']['leases'] ?? [];
                        error_log("Found " . count($leases) . " leases in subnet $subnetId");
                        $allLeases = array_merge($allLeases, $leases);
                        
                        // Check if there's a next page marker
                        if (isset($leaseResult[0]['arguments']['next'])) {
                            $from = $leaseResult[0]['arguments']['next'];
                        } else {
                            // No more pages
                            break;
                        }
                    } else {
                        // No more leases or error
                        $errorMsg = $leaseResult[0]['text'] ?? 'Unknown error';
                        error_log("No leases or error for subnet $subnetId: $errorMsg");
                        break;
                    }
                }
            }
            
            error_log("Total leases found: " . count($allLeases));
            
            // Send file for download
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: public');
            
            echo json_encode($allLeases, JSON_PRETTY_PRINT);
            exit;
            
        } catch (\Exception $e) {
            error_log('Lease backup error: ' . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => 'Backup failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export Kea leases to CSV
     * GET /api/admin/export/kea-leases-csv
     */
    public function exportKeaLeasesCSV()
    {
        try {
            $filename = 'kea-leases-' . date('Y-m-d-His') . '.csv';
            
            // Get first active Kea server from database
            $stmt = $this->db->prepare("SELECT api_url FROM kea_servers WHERE is_active = 1 ORDER BY priority LIMIT 1");
            $stmt->execute();
            $keaServer = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$keaServer) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'No active Kea servers configured'
                ], 500);
                return;
            }
            
            $keaApiUrl = $keaServer['api_url'];
            
            // Get all subnets first
            $subnetData = [
                'command' => 'subnet6-list',
                'service' => ['dhcp6']
            ];
            
            $ch = curl_init($keaApiUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($subnetData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            $subnetResponse = json_decode($response, true);
            
            if (!$subnetResponse || !isset($subnetResponse[0]['arguments']['subnets'])) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Failed to get subnets from Kea'
                ], 500);
                return;
            }
            
            $subnets = $subnetResponse[0]['arguments']['subnets'];
            $allLeases = [];
            
            // Get leases from each subnet using lease6-get-page (via Kea API)
            foreach ($subnets as $subnet) {
                $subnetId = $subnet['id'];
                $from = "start";
                $limit = 1000;
                
                while (true) {
                    $leaseData = [
                        'command' => 'lease6-get-page',
                        'service' => ['dhcp6'],
                        'arguments' => [
                            'from' => $from,
                            'limit' => $limit,
                            'subnet-id' => $subnetId
                        ]
                    ];
                    
                    $ch = curl_init($keaApiUrl);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($leaseData));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    
                    $leaseResponse = curl_exec($ch);
                    curl_close($ch);
                    
                    $leaseResult = json_decode($leaseResponse, true);
                    
                    if ($leaseResult && isset($leaseResult[0]['result']) && $leaseResult[0]['result'] === 0) {
                        $leases = $leaseResult[0]['arguments']['leases'] ?? [];
                        $allLeases = array_merge($allLeases, $leases);
                        
                        // Check if there's a next page marker
                        if (isset($leaseResult[0]['arguments']['next'])) {
                            $from = $leaseResult[0]['arguments']['next'];
                        } else {
                            // No more pages
                            break;
                        }
                    } else {
                        // No more leases or error
                        break;
                    }
                }
            }
            
            // Check if there are any leases
            if (empty($allLeases)) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'No leases found to export'
                ], 404);
                return;
            }
            
            // Send CSV file
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('X-Lease-Count: ' . count($allLeases));

            $output = fopen('php://output', 'w');
            fputcsv($output, ['Address', 'DUID', 'IAID', 'Valid Lifetime', 'Expire', 'Subnet ID', 'Hostname']);

            foreach ($allLeases as $lease) {
                fputcsv($output, [
                    $lease['ip-address'] ?? '',
                    $lease['duid'] ?? '',
                    $lease['iaid'] ?? '',
                    $lease['valid-lft'] ?? '',
                    $lease['cltt'] + $lease['valid-lft'] ?? '', // Calculate expire time
                    $lease['subnet-id'] ?? '',
                    $lease['hostname'] ?? ''
                ]);
            }

            fclose($output);
            exit;
            
        } catch (\Exception $e) {
            error_log('CSV export error: ' . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => 'Export failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export RADIUS clients
     * GET /api/admin/export/radius-clients
     */
    public function exportRadiusClients()
    {
        $filename = 'radius-clients-' . date('Y-m-d-His') . '.conf';
        
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $query = "SELECT nasname, shortname, type, secret, description FROM nas ORDER BY nasname";
        $stmt = $this->db->query($query);

        echo "# FreeRADIUS clients.conf\n";
        echo "# Generated: " . date('Y-m-d H:i:s') . "\n\n";

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "client " . $row['shortname'] . " {\n";
            echo "    ipv6addr = " . $row['nasname'] . "\n";
            echo "    secret = " . $row['secret'] . "\n";
            echo "    shortname = " . $row['shortname'] . "\n";
            echo "    nastype = " . ($row['type'] ?? 'other') . "\n";
            if ($row['description']) {
                echo "    # " . $row['description'] . "\n";
            }
            echo "}\n\n";
        }

        exit;
    }

    /**
     * Backup RADIUS database
     * GET /api/admin/backup/radius-database/{type}
     */
    public function backupRadiusDatabase($type)
    {
        // Get RADIUS server config from database
        require_once BASE_PATH . '/src/Models/RadiusServerConfig.php';
        $configModel = new \App\Models\RadiusServerConfig($this->db);
        
        $serverIndex = ($type === 'primary') ? 0 : 1;
        $server = $configModel->getServerByOrder($serverIndex);

        if (!$server) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'RADIUS server not configured'
            ], 404);
            return;
        }

        $filename = 'radius-' . $type . '-' . date('Y-m-d-His') . '.sql';
        $filepath = BASE_PATH . '/backups/' . $filename;

        if (!file_exists(BASE_PATH . '/backups')) {
            mkdir(BASE_PATH . '/backups', 0755, true);
        }

        $command = sprintf(
            "mysqldump -h %s -P %d -u %s -p%s --skip-ssl %s > %s 2>&1",
            escapeshellarg($server['host']),
            $server['port'],
            escapeshellarg($server['username']),
            escapeshellarg($server['password']),
            escapeshellarg($server['database']),
            escapeshellarg($filepath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode === 0 && file_exists($filepath)) {
            // Send file for download
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($filepath));
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: public');
            
            readfile($filepath);
            
            // Clean up the backup file after download
            unlink($filepath);
            
            exit;
        } else {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Backup failed: ' . implode("\n", $output)
            ], 500);
        }
    }

    /**
     * Full system backup
     * GET /api/admin/backup/full-system
     * Creates a complete backup including Kea database and RADIUS databases
     */
    public function fullSystemBackup()
    {
        $timestamp = date('Y-m-d-His');
        $backupDir = BASE_PATH . '/backups/full-system-' . $timestamp;
        $results = [];
        $errors = [];
        
        // Create backup directory structure
        if (!file_exists($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        // 1. Backup Kea Database
        try {
            $keaFilename = 'kea-db.sql';
            $keaFilepath = $backupDir . '/' . $keaFilename;
            
            $dbHost = getenv('DB_HOST') ?: 'localhost';
            $dbName = getenv('DB_NAME') ?: 'kea_db';
            $dbUser = getenv('DB_USER') ?: 'kea_db_user';
            $dbPass = getenv('DB_PASSWORD') ?: '';

            $command = sprintf(
                "mysqldump -h %s -u %s -p%s --skip-ssl %s > %s 2>&1",
                escapeshellarg($dbHost),
                escapeshellarg($dbUser),
                escapeshellarg($dbPass),
                escapeshellarg($dbName),
                escapeshellarg($keaFilepath)
            );

            exec($command, $output, $returnCode);

            if ($returnCode === 0 && file_exists($keaFilepath)) {
                $results[] = [
                    'component' => 'Kea Database',
                    'status' => 'success',
                    'filename' => $keaFilename,
                    'size' => $this->formatFileSize(filesize($keaFilepath))
                ];
            } else {
                throw new \Exception('Kea backup failed: ' . implode("\n", $output));
            }
        } catch (\Exception $e) {
            $errors[] = 'Kea Database: ' . $e->getMessage();
            $results[] = [
                'component' => 'Kea Database',
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }

        // 2. Backup RADIUS Databases
        require_once BASE_PATH . '/src/Models/RadiusServerConfig.php';
        $configModel = new \App\Models\RadiusServerConfig($this->db);
        
        // Backup Primary RADIUS
        try {
            $primary = $configModel->getServerByOrder(0);
            if ($primary) {
                $radiusFilename = 'radius-primary.sql';
                $radiusFilepath = $backupDir . '/' . $radiusFilename;

                $command = sprintf(
                    "mysqldump -h %s -P %d -u %s -p%s --skip-ssl %s > %s 2>&1",
                    escapeshellarg($primary['host']),
                    $primary['port'],
                    escapeshellarg($primary['username']),
                    escapeshellarg($primary['password']),
                    escapeshellarg($primary['database']),
                    escapeshellarg($radiusFilepath)
                );

                exec($command, $output, $returnCode);

                if ($returnCode === 0 && file_exists($radiusFilepath)) {
                    $results[] = [
                        'component' => 'RADIUS Primary',
                        'status' => 'success',
                        'filename' => $radiusFilename,
                        'size' => $this->formatFileSize(filesize($radiusFilepath))
                    ];
                } else {
                    throw new \Exception('Primary RADIUS backup failed');
                }
            }
        } catch (\Exception $e) {
            $errors[] = 'RADIUS Primary: ' . $e->getMessage();
            $results[] = [
                'component' => 'RADIUS Primary',
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }

        // Backup Secondary RADIUS
        try {
            $secondary = $configModel->getServerByOrder(1);
            if ($secondary) {
                $radiusFilename = 'radius-secondary.sql';
                $radiusFilepath = $backupDir . '/' . $radiusFilename;

                $command = sprintf(
                    "mysqldump -h %s -P %d -u %s -p%s --skip-ssl %s > %s 2>&1",
                    escapeshellarg($secondary['host']),
                    $secondary['port'],
                    escapeshellarg($secondary['username']),
                    escapeshellarg($secondary['password']),
                    escapeshellarg($secondary['database']),
                    escapeshellarg($radiusFilepath)
                );

                exec($command, $output, $returnCode);

                if ($returnCode === 0 && file_exists($radiusFilepath)) {
                    $results[] = [
                        'component' => 'RADIUS Secondary',
                        'status' => 'success',
                        'filename' => $radiusFilename,
                        'size' => $this->formatFileSize(filesize($radiusFilepath))
                    ];
                } else {
                    throw new \Exception('Secondary RADIUS backup failed');
                }
            }
        } catch (\Exception $e) {
            $errors[] = 'RADIUS Secondary: ' . $e->getMessage();
            $results[] = [
                'component' => 'RADIUS Secondary',
                'status' => 'failed',
                'error' => $e->getMessage()
            ];
        }

        // 3. Create manifest file
        $manifest = [
            'backup_date' => date('Y-m-d H:i:s'),
            'timestamp' => $timestamp,
            'components' => $results
        ];
        
        file_put_contents(
            $backupDir . '/manifest.json',
            json_encode($manifest, JSON_PRETTY_PRINT)
        );

        // 4. Create tar.gz archive
        $archiveName = 'full-system-' . $timestamp . '.tar.gz';
        $archivePath = BASE_PATH . '/backups/' . $archiveName;
        
        $tarCommand = sprintf(
            "cd %s && tar -czf %s %s 2>&1",
            escapeshellarg(BASE_PATH . '/backups'),
            escapeshellarg($archiveName),
            escapeshellarg('full-system-' . $timestamp)
        );
        
        exec($tarCommand, $tarOutput, $tarReturn);

        if ($tarReturn === 0 && file_exists($archivePath)) {
            // Remove temporary directory
            $this->removeDirectory($backupDir);
            
            // Clean up old full backups (keep last 5)
            $this->cleanupOldBackups('full-system', 5);
            
            $successCount = count(array_filter($results, function($r) {
                return $r['status'] === 'success';
            }));
            
            $totalCount = count($results);
            
            $this->jsonResponse([
                'success' => empty($errors),
                'message' => empty($errors) 
                    ? 'Full system backup completed successfully' 
                    : 'Backup completed with some errors',
                'filename' => $archiveName,
                'size' => $this->formatFileSize(filesize($archivePath)),
                'path' => '/backups/' . $archiveName,
                'components' => $results,
                'summary' => [
                    'success' => $successCount,
                    'failed' => $totalCount - $successCount,
                    'total' => $totalCount
                ],
                'errors' => $errors
            ]);
        } else {
            // Cleanup on failure
            if (file_exists($backupDir)) {
                $this->removeDirectory($backupDir);
            }
            
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to create archive',
                'components' => $results,
                'errors' => array_merge($errors, ['Archive creation failed'])
            ], 500);
        }
    }

    /**
     * Helper method to recursively remove directory
     */
    private function removeDirectory($dir)
    {
        if (!file_exists($dir)) {
            return true;
        }

        if (!is_dir($dir)) {
            return unlink($dir);
        }

        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }

            if (!$this->removeDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }

        return rmdir($dir);
    }

    /**
     * List backups
     * GET /api/admin/backups/list
     */
    public function listBackups()
    {
        $backupsDir = BASE_PATH . '/backups';
        
        if (!file_exists($backupsDir)) {
            $this->jsonResponse([
                'success' => true,
                'backups' => []
            ]);
            return;
        }

        $files = scandir($backupsDir);
        $backups = [];

        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || $file === '.gitkeep') {
                continue;
            }

            $filepath = $backupsDir . '/' . $file;
            if (is_file($filepath)) {
                $backups[] = [
                    'filename' => $file,
                    'type' => $this->getBackupType($file),
                    'size' => $this->formatFileSize(filesize($filepath)),
                    'date' => date('Y-m-d H:i:s', filemtime($filepath))
                ];
            }
        }

        // Sort by date descending
        usort($backups, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        $this->jsonResponse([
            'success' => true,
            'backups' => $backups
        ]);
    }

    /**
     * Download backup
     * GET /api/admin/backup/download/{filename}
     */
    public function downloadBackup($filename)
    {
        $filepath = BASE_PATH . '/backups/' . basename($filename);

        if (!file_exists($filepath)) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Backup file not found'
            ], 404);
            return;
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }

    /**
     * Delete backup
     * DELETE /api/admin/backup/delete/{filename}
     */
    public function deleteBackup($filename)
    {
        $filepath = BASE_PATH . '/backups/' . basename($filename);

        if (!file_exists($filepath)) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Backup file not found'
            ], 404);
            return;
        }

        if (unlink($filepath)) {
            $this->jsonResponse([
                'success' => true,
                'message' => 'Backup deleted successfully'
            ]);
        } else {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to delete backup'
            ], 500);
        }
    }

    /**
     * Helper: Get backup type from filename
     */
    private function getBackupType($filename)
    {
        if (strpos($filename, 'kea-db') !== false) return 'Kea Database';
        if (strpos($filename, 'kea-leases') !== false) return 'Kea Leases';
        if (strpos($filename, 'radius-primary') !== false) return 'RADIUS Primary';
        if (strpos($filename, 'radius-secondary') !== false) return 'RADIUS Secondary';
        if (strpos($filename, 'full-system') !== false) return 'Full System';
        return 'Unknown';
    }

    /**
     * Helper: Format file size
     */
    private function formatFileSize($bytes)
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }

    /**
     * Helper: Clean up old backups, keeping only the last N backups of a specific type
     */
    private function cleanupOldBackups($prefix, $keepCount = 7)
    {
        $backupsDir = BASE_PATH . '/backups';
        
        if (!file_exists($backupsDir)) {
            return;
        }

        // Get all backup files matching the prefix
        $files = [];
        foreach (scandir($backupsDir) as $file) {
            if ($file === '.' || $file === '..' || $file === '.gitkeep' || $file === '.gitignore') {
                continue;
            }
            
            if (strpos($file, $prefix) === 0) {
                $filepath = $backupsDir . '/' . $file;
                if (is_file($filepath)) {
                    $files[] = [
                        'name' => $file,
                        'path' => $filepath,
                        'time' => filemtime($filepath)
                    ];
                }
            }
        }

        // Sort by modification time (newest first)
        usort($files, function($a, $b) {
            return $b['time'] - $a['time'];
        });

        // Delete files beyond the keep count
        for ($i = $keepCount; $i < count($files); $i++) {
            unlink($files[$i]['path']);
            error_log("Deleted old backup: " . $files[$i]['name']);
        }
    }

    /**
     * Restore Kea database from backup
     * POST /api/admin/restore/kea-database
     */
    public function restoreKeaDatabase()
    {
        if (!isset($_FILES['backup'])) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'No backup file uploaded'
            ], 400);
            return;
        }

        $file = $_FILES['backup'];
        $tmpPath = $file['tmp_name'];

        // Get database credentials
        $dbHost = $_ENV['DB_HOST'] ?? 'localhost';
        $dbName = $_ENV['DB_NAME'] ?? 'kea_db';
        $dbUser = $_ENV['DB_USER'] ?? 'kea_db_user';
        $dbPass = $_ENV['DB_PASSWORD'] ?? '';

        // Execute mysql restore
        $command = sprintf(
            "mysql -h %s -u %s -p'%s' %s < %s 2>&1",
            escapeshellarg($dbHost),
            escapeshellarg($dbUser),
            $dbPass,
            escapeshellarg($dbName),
            escapeshellarg($tmpPath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode === 0) {
            $this->jsonResponse([
                'success' => true,
                'message' => 'Database restored successfully'
            ]);
        } else {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Restore failed: ' . implode("\n", $output)
            ], 500);
        }
    }

    /**
     * Restore from existing backup file on server
     * POST /api/admin/restore/kea-database/{filename}
     */
    public function restoreFromServerBackup($filename)
    {
        $filepath = BASE_PATH . '/backups/' . basename($filename);

        if (!file_exists($filepath)) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Backup file not found'
            ], 404);
            return;
        }

        // Determine database type from filename
        if (strpos($filename, 'kea-db') !== false) {
            $dbHost = $_ENV['DB_HOST'] ?? 'localhost';
            $dbName = $_ENV['DB_NAME'] ?? 'kea_db';
            $dbUser = $_ENV['DB_USER'] ?? 'kea_db_user';
            $dbPass = $_ENV['DB_PASSWORD'] ?? '';
        } elseif (strpos($filename, 'radius') !== false) {
            // Get RADIUS server config
            require_once BASE_PATH . '/src/Models/RadiusServerConfig.php';
            $configModel = new \App\Models\RadiusServerConfig($this->db);
            
            $type = strpos($filename, 'primary') !== false ? 'primary' : 'secondary';
            $serverIndex = ($type === 'primary') ? 0 : 1;
            $server = $configModel->getServerByOrder($serverIndex);

            if (!$server) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'RADIUS server not configured'
                ], 404);
                return;
            }

            $dbHost = $server['host'];
            $dbName = $server['database'];
            $dbUser = $server['username'];
            $dbPass = $server['password'];
        } else {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Unknown backup type'
            ], 400);
            return;
        }

        // Execute mysql restore
        $command = sprintf(
            "mysql -h %s -u %s -p'%s' %s < %s 2>&1",
            escapeshellarg($dbHost),
            escapeshellarg($dbUser),
            $dbPass,
            escapeshellarg($dbName),
            escapeshellarg($filepath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode === 0) {
            $this->jsonResponse([
                'success' => true,
                'message' => 'Database restored successfully from ' . $filename
            ]);
        } else {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Restore failed: ' . implode("\n", $output)
            ], 500);
        }
    }

    /**
     * Save Kea configuration to disk on all servers
     * POST /api/admin/save-config
     */
    public function saveConfig()
    {
        try {
            // Clear and restart output buffering
            while (ob_get_level()) {
                ob_end_clean();
            }
            ob_start();
            
            header('Content-Type: application/json');
            
            $dhcpModel = new \App\Models\DHCP($this->db);
            
            // Call config-write on all servers with proper arguments
            $result = $dhcpModel->sendKeaCommand('config-write', ['filename' => '/etc/kea/kea-dhcp6.conf']);
            
            // Get server count for response
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM kea_servers WHERE is_active = 1");
            $stmt->execute();
            $serverCount = $stmt->fetchColumn();
            
            if (isset($result[0]['result']) && $result[0]['result'] === 0) {
                $response = [
                    'success' => true,
                    'message' => "Configuration saved successfully on all {$serverCount} server(s)",
                    'details' => [
                        "✓ Configuration written to /etc/kea/kea-dhcp6.conf",
                        "✓ Changes will persist across server restarts",
                        "✓ All {$serverCount} configured Kea server(s) updated"
                    ]
                ];
                echo json_encode($response);
                ob_end_flush();
                exit;
            } else {
                $errorText = $result[0]['text'] ?? 'Unknown error';
                throw new \Exception("Config-write command returned error: {$errorText}");
            }
        } catch (\Exception $e) {
            error_log("Error in saveConfig: " . $e->getMessage());
            http_response_code(500);
            $response = [
                'success' => false,
                'message' => 'Failed to save configuration: ' . $e->getMessage()
            ];
            echo json_encode($response);
            ob_end_flush();
            exit;
        }
    }

    /**
     * Clear all CIN data (switches, BVI interfaces, links) and remove from Kea
     * POST /api/admin/clear-cin-data
     */
    public function clearCinData()
    {
        try {
            error_log("=== Clear CIN Data Started ===");
            
            // Get all subnet IDs linked to BVI interfaces before deleting
            $stmt = $this->db->query("
                SELECT DISTINCT kea_subnet_id 
                FROM cin_bvi_dhcp_core 
                WHERE kea_subnet_id IS NOT NULL
            ");
            $keaSubnetIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            
            error_log("Found " . count($keaSubnetIds) . " Kea subnets to delete");
            
            // Count before deleting
            $switchCount = $this->db->query("SELECT COUNT(*) FROM cin_switches")->fetchColumn();
            $bviCount = $this->db->query("SELECT COUNT(*) FROM cin_switch_bvi_interfaces")->fetchColumn();
            $linkCount = $this->db->query("SELECT COUNT(*) FROM cin_bvi_dhcp_core")->fetchColumn();
            
            // Count RADIUS clients
            $radiusCount = 0;
            try {
                $radiusCount = $this->db->query("SELECT COUNT(*) FROM radius_clients")->fetchColumn();
            } catch (\Exception $e) {
                // Table might not exist
            }
            
            // Delete CIN data from database (in correct order - respect foreign keys)
            $this->db->exec("DELETE FROM cin_bvi_dhcp_core");
            $this->db->exec("DELETE FROM cin_switch_bvi_interfaces");
            $this->db->exec("DELETE FROM cin_switches");
            
            // Delete RADIUS clients if table exists
            try {
                $this->db->exec("DELETE FROM radius_clients");
            } catch (\Exception $e) {
                // Table might not exist
            }
            
            error_log("Deleted CIN data from database");
            
            // Get first active Kea server from database
            $stmt = $this->db->prepare("SELECT api_url FROM kea_servers WHERE is_active = 1 ORDER BY priority LIMIT 1");
            $stmt->execute();
            $keaServer = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$keaServer) {
                throw new \Exception("No active Kea servers configured");
            }
            
            $keaApiUrl = $keaServer['api_url'];
            error_log("Using Kea server: $keaApiUrl");
            
            $deletedSubnets = 0;
            $subnetErrors = [];
            
            foreach ($keaSubnetIds as $subnetId) {
                try {
                    error_log("Deleting Kea subnet ID: $subnetId");
                    
                    $data = [
                        'command' => 'subnet6-del',
                        'service' => ['dhcp6'],
                        'arguments' => [
                            'id' => intval($subnetId)
                        ]
                    ];
                    
                    $ch = curl_init($keaApiUrl);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);
                    
                    $keaResponse = json_decode($response, true);
                    
                    if ($keaResponse && isset($keaResponse[0]['result']) && $keaResponse[0]['result'] === 0) {
                        $deletedSubnets++;
                        error_log("✓ Deleted Kea subnet: $subnetId");
                    } else {
                        $errorMsg = $keaResponse[0]['text'] ?? 'Unknown error';
                        $subnetErrors[] = "Subnet $subnetId: $errorMsg";
                        error_log("✗ Failed to delete subnet $subnetId: $errorMsg");
                    }
                    
                } catch (\Exception $e) {
                    $subnetErrors[] = "Subnet $subnetId: " . $e->getMessage();
                    error_log("✗ Exception deleting subnet $subnetId: " . $e->getMessage());
                }
            }
            
            error_log("=== Clear CIN Data Complete ===");
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'CIN data and Kea subnets cleared successfully',
                'switches' => $switchCount,
                'bvi_interfaces' => $bviCount,
                'links' => $linkCount,
                'radius_clients' => $radiusCount,
                'kea_subnets_deleted' => $deletedSubnets,
                'kea_subnets_total' => count($keaSubnetIds),
                'kea_errors' => $subnetErrors
            ]);
        } catch (\Exception $e) {
            error_log("Clear CIN Data Error: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to clear CIN data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import Kea leases from CSV and convert to static reservations
     * POST /api/admin/import-leases
     */
    public function importLeases()
    {
        try {
            error_log("=== Starting lease import ===");
            
            // Check if file was uploaded
            if (!isset($_FILES['leases_file']) || $_FILES['leases_file']['error'] !== UPLOAD_ERR_OK) {
                throw new \Exception('No file uploaded or upload error');
            }

            $file = $_FILES['leases_file']['tmp_name'];
            $fileName = $_FILES['leases_file']['name'];
            
            error_log("Processing file: " . $fileName);

            // Read and parse CSV
            $leases = $this->parseLeasesCSV($file);
            
            error_log("Parsed " . count($leases) . " leases from CSV");
            
            // Get subnet mapping
            $subnetMapping = [];
            $autoMap = isset($_POST['auto_map']) && $_POST['auto_map'] === 'true';
            
            if ($autoMap) {
                // Automatic mapping by matching IP addresses
                error_log("Auto-mapping subnet IDs by IP address matching");
                $subnetMapping = $this->autoMapSubnetIds($leases);
                error_log("Auto-mapped subnets: " . json_encode($subnetMapping));
            } elseif (isset($_POST['subnet_mapping'])) {
                // Manual mapping provided
                $subnetMapping = json_decode($_POST['subnet_mapping'], true) ?: [];
                error_log("Manual subnet mapping: " . json_encode($subnetMapping));
            }
            
            // Apply lease mapping and filter unmapped leases
            $mappedLeases = [];
            $unmappedCount = 0;
            $unmappedSubnets = [];
            
            // Always filter if auto-mapping was attempted
            if ($autoMap || !empty($subnetMapping)) {
                foreach ($leases as $lease) {
                    $ipAddress = $lease['address'];
                    $oldSubnetId = strval($lease['subnet_id']);
                    
                    if (isset($subnetMapping[$ipAddress])) {
                        // Map to new subnet ID based on IP match
                        $newSubnetId = intval($subnetMapping[$ipAddress]);
                        error_log("Applying mapping for lease $ipAddress: {$lease['subnet_id']} → $newSubnetId");
                        $lease['subnet_id'] = $newSubnetId;
                        $mappedLeases[] = $lease;
                    } else {
                        // Skip unmapped leases - they don't belong to any configured subnet
                        $unmappedCount++;
                        $unmappedSubnets[$oldSubnetId] = ($unmappedSubnets[$oldSubnetId] ?? 0) + 1;
                        error_log("SKIPPING unmapped lease $ipAddress (CSV subnet_id: $oldSubnetId - IP doesn't match any Kea subnet)");
                    }
                }
                
                // Log summary of unmapped subnets
                if ($unmappedCount > 0) {
                    error_log("Skipped $unmappedCount unmapped leases (IPs don't match any Kea subnet): " . json_encode($unmappedSubnets));
                }
                
                $leases = $mappedLeases;
            }

            // Import leases
            $result = $this->importLeasesToStatic($leases);
            
            // Add unmapped info to response
            $responseData = [
                'success' => true,
                'message' => 'Leases imported successfully',
                'total' => count($leases) + $unmappedCount,
                'imported' => $result['imported'],
                'skipped' => $result['skipped'] + $unmappedCount,
                'unmapped' => $unmappedCount,
                'errors' => $result['errors'],
                'subnet_mapping' => $subnetMapping
            ];
            
            // Add info about unmapped subnets if any
            if ($unmappedCount > 0) {
                $unmappedInfo = [];
                foreach ($unmappedSubnets as $subnetId => $count) {
                    $unmappedInfo[] = "Subnet $subnetId: $count leases";
                }
                $responseData['unmapped_info'] = $unmappedInfo;
            }
            
            $this->jsonResponse($responseData);

        } catch (\Exception $e) {
            error_log("Import leases error: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to import leases: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import leases from JSON backup file
     * POST /api/admin/import-leases-json
     */
    public function importLeasesJSON()
    {
        try {
            error_log("=== Starting JSON lease import ===");
            
            // Check if file was uploaded
            if (!isset($_FILES['json_file']) || $_FILES['json_file']['error'] !== UPLOAD_ERR_OK) {
                throw new \Exception('No file uploaded or upload error');
            }

            $file = $_FILES['json_file']['tmp_name'];
            $fileName = $_FILES['json_file']['name'];
            
            error_log("Processing JSON file: " . $fileName);

            // Read and parse JSON
            $jsonContent = file_get_contents($file);
            $leasesData = json_decode($jsonContent, true);
            
            if ($leasesData === null) {
                throw new \Exception('Invalid JSON file');
            }
            
            error_log("Parsed " . count($leasesData) . " leases from JSON");
            
            // Convert JSON lease format to import format
            $leases = [];
            foreach ($leasesData as $lease) {
                $leases[] = [
                    'address' => $lease['ip-address'] ?? '',
                    'duid' => $lease['duid'] ?? '',
                    'valid_lifetime' => $lease['valid-lft'] ?? 7200,
                    'expire' => ($lease['cltt'] ?? time()) + ($lease['valid-lft'] ?? 7200),
                    'subnet_id' => $lease['subnet-id'] ?? 0,
                    'pref_lifetime' => $lease['preferred-lft'] ?? 3600,
                    'lease_type' => $lease['type'] ?? 0,
                    'iaid' => $lease['iaid'] ?? 0,
                    'prefix_len' => $lease['prefix-len'] ?? 128,
                    'fqdn_fwd' => $lease['fqdn-fwd'] ?? false,
                    'fqdn_rev' => $lease['fqdn-rev'] ?? false,
                    'hostname' => $lease['hostname'] ?? '',
                    'hwaddr' => $lease['hwaddr'] ?? '',
                    'state' => $lease['state'] ?? 0
                ];
            }

            // Import leases
            $result = $this->importLeasesToStatic($leases);
            
            $responseData = [
                'success' => true,
                'message' => 'Leases imported successfully from JSON',
                'total' => count($leases),
                'imported' => $result['imported'],
                'skipped' => $result['skipped'],
                'errors' => $result['errors']
            ];
            
            $this->jsonResponse($responseData);

        } catch (\Exception $e) {
            error_log("Import JSON leases error: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to import leases from JSON: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Parse Kea CSV lease file
     */
    private function parseLeasesCSV($filePath)
    {
        $leases = [];
        $handle = fopen($filePath, 'r');
        
        if (!$handle) {
            throw new \Exception('Could not open file');
        }

        // Read header
        $header = fgetcsv($handle);
        
        if (!$header || !in_array('address', $header)) {
            fclose($handle);
            throw new \Exception('Invalid CSV format - missing required columns');
        }

        // Map header to indices
        $columns = array_flip($header);
        
        // Read data rows
        while (($row = fgetcsv($handle)) !== false) {
            if (empty($row) || count($row) < count($header)) {
                continue;
            }

            $lease = [
                'address' => $row[$columns['address']] ?? '',
                'duid' => $row[$columns['duid']] ?? '',
                'valid_lifetime' => intval($row[$columns['valid_lifetime']] ?? 0),
                'expire' => intval($row[$columns['expire']] ?? 0),
                'subnet_id' => intval($row[$columns['subnet_id']] ?? 0),
                'pref_lifetime' => intval($row[$columns['pref_lifetime']] ?? 0),
                'hostname' => $row[$columns['hostname']] ?? '',
                'hwaddr' => $row[$columns['hwaddr']] ?? '',
                'state' => intval($row[$columns['state']] ?? 0)
            ];

            // Only include leases with valid lifetime (skip zero lifetime)
            // Note: We import even if expired - Kea will create new leases with current time + valid_lifetime
            if ($lease['valid_lifetime'] > 0) {
                $leases[] = $lease;
            }
        }

        fclose($handle);
        return $leases;
    }

    /**
     * Auto-map subnet IDs by matching IP addresses to Kea subnets
     */
    private function autoMapSubnetIds($leases)
    {
        $mapping = [];
        
        try {
            // Get first active Kea server from database (same as other operations)
            $stmt = $this->db->prepare("SELECT api_url FROM kea_servers WHERE is_active = 1 ORDER BY priority LIMIT 1");
            $stmt->execute();
            $keaServer = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$keaServer) {
                error_log("No active Kea servers configured for auto-mapping");
                return $mapping;
            }
            
            $keaApiUrl = $keaServer['api_url'];
            error_log("Using Kea server: $keaApiUrl");
            
            $data = [
                'command' => 'subnet6-list',
                'service' => ['dhcp6']
            ];
            
            $ch = curl_init($keaApiUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            
            $response = curl_exec($ch);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($curlError) {
                error_log("Curl error getting subnets: $curlError");
                return $mapping;
            }
            
            error_log("Kea subnet6-list response: " . substr($response, 0, 500));
            
            $keaResponse = json_decode($response, true);
            
            if (!$keaResponse || !isset($keaResponse[0]['arguments']['subnets'])) {
                error_log("Failed to get subnets from Kea for auto-mapping. Response: " . json_encode($keaResponse));
                return $mapping;
            }
            
            $keaSubnets = $keaResponse[0]['arguments']['subnets'];
            error_log("Found " . count($keaSubnets) . " Kea subnets for IP matching");
            
            // Log available subnets for debugging
            foreach ($keaSubnets as $ks) {
                error_log("  Available: {$ks['subnet']} (id={$ks['id']})");
            }
            
            // Match EACH lease's IP to the correct Kea subnet
            // The CSV subnet_id is completely ignored - we only match by IP address
            // Map by IP address (key) to Kea subnet_id (value)
            foreach ($leases as $lease) {
                $ipAddress = $lease['address'];
                $csvSubnetId = strval($lease['subnet_id']);
                
                // Find which Kea subnet this IP actually belongs to
                $matched = false;
                foreach ($keaSubnets as $keaSubnet) {
                    if ($this->ipBelongsToSubnet($ipAddress, $keaSubnet['subnet'])) {
                        // Found it! Map this specific IP address → correct Kea subnet_id
                        $mapping[$ipAddress] = $keaSubnet['id'];
                        error_log("✓ Mapped lease $ipAddress (CSV subnet $csvSubnetId) → Kea subnet {$keaSubnet['id']} ({$keaSubnet['subnet']})");
                        $matched = true;
                        break;
                    }
                }
                
                if (!$matched) {
                    error_log("✗ No Kea subnet found for IP $ipAddress (CSV subnet $csvSubnetId)");
                }
            }
            
            error_log("Auto-mapping complete. Total leases mapped: " . count($mapping));
            
        } catch (\Exception $e) {
            error_log("Error in auto-mapping: " . $e->getMessage());
        }
        
        return $mapping;
    }

    /**
     * Check if an IPv6 address belongs to a subnet
     * Simple and clean implementation using PHP's inet functions
     */
    private function ipBelongsToSubnet($ip, $subnet)
    {
        try {
            // Parse subnet (e.g., "2001:db8::/32")
            $parts = explode('/', $subnet);
            if (count($parts) !== 2) {
                error_log("ERROR: Invalid subnet format: $subnet");
                return false;
            }
            
            list($subnetAddr, $prefixLen) = $parts;
            $prefixLen = (int)$prefixLen;
            
            // Validate inputs
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
                error_log("ERROR: Invalid IPv6 address: $ip");
                return false;
            }
            
            if (filter_var($subnetAddr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
                error_log("ERROR: Invalid IPv6 subnet address: $subnetAddr");
                return false;
            }
            
            // Use Symfony's IpUtils if available, otherwise use inet_pton
            if (class_exists('\Symfony\Component\HttpFoundation\IpUtils')) {
                return \Symfony\Component\HttpFoundation\IpUtils::checkIp6($ip, $subnet);
            }
            
            // Fallback: Simple binary comparison
            $ipBin = inet_pton($ip);
            $subnetBin = inet_pton($subnetAddr);
            
            if ($ipBin === false || $subnetBin === false) {
                error_log("ERROR: Failed to convert to binary - IP: $ip, Subnet: $subnetAddr");
                return false;
            }
            
            // Create mask for the prefix length
            $mask = str_repeat('f', $prefixLen >> 2);
            switch ($prefixLen & 3) {
                case 1: $mask .= '8'; break;
                case 2: $mask .= 'c'; break;
                case 3: $mask .= 'e'; break;
            }
            $mask = str_pad($mask, 32, '0');
            $mask = pack('H*', $mask);
            
            // Apply mask and compare - use == not === (binary strings!)
            return ($ipBin & $mask) == ($subnetBin & $mask);
            
        } catch (\Exception $e) {
            error_log("ERROR in ipBelongsToSubnet: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Import leases as active leases using Kea API (lease6-add)
     */
    private function importLeasesToStatic($leases)
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];
        
        // Get first active Kea server from database (same as other operations)
        $stmt = $this->db->prepare("SELECT api_url FROM kea_servers WHERE is_active = 1 ORDER BY priority LIMIT 1");
        $stmt->execute();
        $keaServer = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$keaServer) {
            return [
                'imported' => 0,
                'skipped' => count($leases),
                'errors' => ['No active Kea servers configured']
            ];
        }
        
        $keaApiUrl = $keaServer['api_url'];
        error_log("Importing leases to Kea server: $keaApiUrl");

        foreach ($leases as $lease) {
            try {
                // Validate lease data
                if (empty($lease['address']) || empty($lease['duid']) || empty($lease['subnet_id'])) {
                    $skipped++;
                    $errors[] = "Skipped lease: missing required fields";
                    continue;
                }

                // Try to delete existing lease first (to handle reimports)
                $deleteData = [
                    'command' => 'lease6-del',
                    'service' => ['dhcp6'],
                    'arguments' => [
                        'ip-address' => $lease['address']
                    ]
                ];
                
                $ch = curl_init($keaApiUrl);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($deleteData));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                curl_exec($ch); // Ignore result - it's OK if it doesn't exist
                curl_close($ch);

                // Now add as active lease via Kea API (lease6-add)
                $data = [
                    'command' => 'lease6-add',
                    'service' => ['dhcp6'],
                    'arguments' => [
                        'ip-address' => $lease['address'],
                        'duid' => $lease['duid'],
                        'iaid' => $lease['iaid'] ?? 1,
                        'subnet-id' => $lease['subnet_id'],
                        'valid-lft' => $lease['valid_lifetime']
                    ]
                ];
                
                // Add optional fields
                if (!empty($lease['pref_lifetime'])) {
                    $data['arguments']['preferred-lft'] = $lease['pref_lifetime'];
                }
                if (!empty($lease['hostname'])) {
                    $data['arguments']['hostname'] = $lease['hostname'];
                }
                if (!empty($lease['hwaddr'])) {
                    $data['arguments']['hw-address'] = $lease['hwaddr'];
                }

                error_log("Adding active lease: " . $lease['address'] . " for DUID: " . $lease['duid']);

                $ch = curl_init($keaApiUrl);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $keaResponse = json_decode($response, true);

                if ($keaResponse && isset($keaResponse[0]['result']) && $keaResponse[0]['result'] === 0) {
                    $imported++;
                    error_log("✓ Imported: " . $lease['address']);
                } else {
                    $skipped++;
                    $errorMsg = $keaResponse[0]['text'] ?? 'Unknown error';
                    $errors[] = "Failed to import {$lease['address']}: {$errorMsg}";
                    error_log("✗ Failed: " . $lease['address'] . " - " . $errorMsg);
                }

            } catch (\Exception $e) {
                $skipped++;
                $errors[] = "Error importing {$lease['address']}: " . $e->getMessage();
                error_log("✗ Exception: " . $e->getMessage());
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors
        ];
    }

    /**
     * Check for orphaned RADIUS entries
     * GET /api/admin/radius/check-orphans
     */
    public function checkRadiusOrphans()
    {
        try {
            // Get all valid BVI IPv6 addresses (these should have NAS entries)
            $validBviIds = $this->db->query("SELECT ipv6_address FROM cin_switch_bvi_interfaces")->fetchAll(PDO::FETCH_COLUMN);
            
            $report = [
                'valid_bvi_ips' => $validBviIds,
                'local_orphans' => [],
                'remote_orphans' => []
            ];
            
            // Check local nas table
            $localOrphans = $this->db->query("
                SELECT n.id, n.nasname, n.shortname
                FROM nas n
                LEFT JOIN cin_switch_bvi_interfaces b ON n.nasname = b.ipv6_address
                WHERE b.id IS NULL
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            $report['local_orphans'] = $localOrphans;
            
            // Check remote RADIUS servers
            require_once BASE_PATH . '/src/Models/RadiusServerConfig.php';
            $radiusConfigModel = new \App\Models\RadiusServerConfig($this->db);
            $servers = $radiusConfigModel->getServersForSync();
            
            foreach ($servers as $server) {
                if (!$server['enabled']) {
                    continue;
                }
                
                try {
                    $dsn = sprintf(
                        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                        $server['host'],
                        $server['port'],
                        $server['database'],
                        $server['charset']
                    );
                    
                    $remoteDb = new PDO($dsn, $server['username'], $server['password'], [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]);
                    
                    // Get all NAS entries from remote server
                    $stmt = $remoteDb->query("SELECT id, nasname, shortname FROM nas");
                    $nasEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $serverOrphans = [];
                    foreach ($nasEntries as $entry) {
                        // Check if this NAS IP exists in our BVI table
                        if (!in_array($entry['nasname'], $validBviIds)) {
                            $serverOrphans[] = $entry;
                        }
                    }
                    
                    if (!empty($serverOrphans)) {
                        $report['remote_orphans'][$server['name']] = [
                            'count' => count($serverOrphans),
                            'entries' => $serverOrphans
                        ];
                    }
                    
                } catch (\PDOException $e) {
                    $report['remote_orphans'][$server['name']] = [
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            $totalOrphans = count($localOrphans);
            foreach ($report['remote_orphans'] as $serverData) {
                if (isset($serverData['count'])) {
                    $totalOrphans += $serverData['count'];
                }
            }
            
            $this->jsonResponse([
                'success' => true,
                'total_orphans' => $totalOrphans,
                'report' => $report
            ]);
            
        } catch (\Exception $e) {
            error_log("Error checking RADIUS orphans: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clean orphaned RADIUS entries
     * POST /api/admin/radius/clean-orphans
     */
    public function cleanRadiusOrphans()
    {
        try {
            // Get all valid BVI IPv6 addresses (these should have NAS entries)
            $validBviIps = $this->db->query("SELECT ipv6_address FROM cin_switch_bvi_interfaces")->fetchAll(PDO::FETCH_COLUMN);
            
            $report = [
                'local_deleted' => 0,
                'remote_deleted' => [],
                'errors' => []
            ];
            
            // Clean local nas table - delete NAS entries not matching any BVI IP
            $localOrphans = $this->db->query("
                SELECT n.id, n.nasname, n.shortname
                FROM nas n
                LEFT JOIN cin_switch_bvi_interfaces b ON n.nasname = b.ipv6_address
                WHERE b.id IS NULL
            ")->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($localOrphans as $orphan) {
                $stmt = $this->db->prepare("DELETE FROM nas WHERE id = ?");
                $stmt->execute([$orphan['id']]);
                $report['local_deleted']++;
            }
            
            // Clean remote RADIUS servers
            require_once BASE_PATH . '/src/Models/RadiusServerConfig.php';
            $radiusConfigModel = new \App\Models\RadiusServerConfig($this->db);
            $servers = $radiusConfigModel->getServersForSync();
            
            foreach ($servers as $server) {
                if (!$server['enabled']) {
                    continue;
                }
                
                try {
                    $dsn = sprintf(
                        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                        $server['host'],
                        $server['port'],
                        $server['database'],
                        $server['charset']
                    );
                    
                    $remoteDb = new PDO($dsn, $server['username'], $server['password'], [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ]);
                    
                    // Get all NAS entries from remote server
                    $stmt = $remoteDb->query("SELECT id, nasname, shortname FROM nas");
                    $nasEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $deletedCount = 0;
                    foreach ($nasEntries as $entry) {
                        // Delete if NAS IP doesn't match any valid BVI IP
                        if (!in_array($entry['nasname'], $validBviIps)) {
                            $deleteStmt = $remoteDb->prepare("DELETE FROM nas WHERE id = ?");
                            $deleteStmt->execute([$entry['id']]);
                            $deletedCount++;
                        }
                    }
                    
                    $report['remote_deleted'][$server['name']] = $deletedCount;
                    
                } catch (\PDOException $e) {
                    $report['errors'][] = "{$server['name']}: {$e->getMessage()}";
                }
            }
            
            $totalDeleted = $report['local_deleted'];
            foreach ($report['remote_deleted'] as $count) {
                $totalDeleted += $count;
            }
            
            $this->jsonResponse([
                'success' => true,
                'total_deleted' => $totalDeleted,
                'report' => $report,
                'message' => "Deleted {$totalDeleted} orphaned RADIUS entries"
            ]);
            
        } catch (\Exception $e) {
            error_log("Error cleaning RADIUS orphans: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * View current Kea configuration
     * GET /api/admin/kea-config/view
     */
    public function viewKeaConfig()
    {
        try {
            // Get the first active Kea server from database
            $stmt = $this->db->prepare("SELECT api_url FROM kea_servers WHERE is_active = 1 LIMIT 1");
            $stmt->execute();
            $server = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$server) {
                throw new \Exception('No active Kea server found');
            }
            
            $command = [
                'command' => 'config-get',
                'service' => ['dhcp6']
            ];
            
            $ch = curl_init($server['api_url']);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($command));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $responseJson = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200 || !$responseJson) {
                throw new \Exception('Failed to communicate with Kea API');
            }
            
            $response = json_decode($responseJson, true);
            
            if (!isset($response[0]['result']) || $response[0]['result'] !== 0) {
                throw new \Exception('Failed to retrieve configuration from Kea');
            }
            
            $config = $response[0]['arguments'];
            
            // Calculate statistics
            $stats = [
                'subnets' => count($config['Dhcp6']['subnet6'] ?? []),
                'pools' => 0,
                'options' => count($config['Dhcp6']['option-data'] ?? [])
            ];
            
            // Count pools
            foreach ($config['Dhcp6']['subnet6'] ?? [] as $subnet) {
                $stats['pools'] += count($subnet['pools'] ?? []);
                $stats['pools'] += count($subnet['pd-pools'] ?? []);
            }
            
            $this->jsonResponse([
                'success' => true,
                'config' => $config,
                'stats' => $stats
            ]);
            
        } catch (\Exception $e) {
            error_log("Error viewing Kea config: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download Kea configuration as .conf file
     * GET /api/admin/kea-config/download-conf
     */
    public function downloadKeaConfigConf()
    {
        try {
            // Get first active Kea server from database
            $stmt = $this->db->prepare("SELECT api_url FROM kea_servers WHERE is_active = 1 ORDER BY priority LIMIT 1");
            $stmt->execute();
            $keaServer = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$keaServer) {
                throw new \Exception("No active Kea servers configured");
            }
            
            $keaApiUrl = $keaServer['api_url'];
            error_log("Downloading config from Kea server: $keaApiUrl");
            
            $command = [
                'command' => 'config-get',
                'service' => ['dhcp6']
            ];
            
            $ch = curl_init($keaApiUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($command));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            
            $responseJson = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                throw new \Exception('Failed to communicate with Kea API');
            }
            
            $response = json_decode($responseJson, true);
            
            if (!isset($response[0]['result']) || $response[0]['result'] !== 0) {
                throw new \Exception('Failed to retrieve configuration from Kea');
            }
            
            $config = $response[0]['arguments'];
            
            // Convert to JSON format (Kea uses JSON config format)
            $configJson = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            
            // Set headers for download
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="kea-dhcp6-' . date('Y-m-d') . '.conf"');
            header('Content-Length: ' . strlen($configJson));
            
            echo $configJson;
            exit;
            
        } catch (\Exception $e) {
            error_log("Error downloading Kea config: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * List Kea config backups from database
     * GET /api/admin/kea-config-backups/list
     */
    public function listKeaConfigBackups()
    {
        try {
            $stmt = $this->db->prepare("
                SELECT b.*, s.name as server_name 
                FROM kea_config_backups b
                LEFT JOIN kea_servers s ON b.server_id = s.id
                ORDER BY b.created_at DESC
                LIMIT 20
            ");
            $stmt->execute();
            $backups = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->jsonResponse([
                'success' => true,
                'backups' => $backups
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to load backups: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * View Kea config backup
     * GET /api/admin/kea-config-backups/view/{id}
     */
    public function viewKeaConfigBackup($backupId)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM kea_config_backups WHERE id = ?");
            $stmt->execute([$backupId]);
            $backup = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$backup) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Backup not found'
                ], 404);
                return;
            }

            $this->jsonResponse([
                'success' => true,
                'config' => json_decode($backup['config_json'], true),
                'created_at' => $backup['created_at'],
                'created_by' => $backup['created_by'],
                'operation' => $backup['operation']
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to load backup: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore Kea config from backup
     * POST /api/admin/kea-config-backups/restore/{id}
     */
    public function restoreKeaConfigBackup($backupId)
    {
        try {
            // Get backup from database
            $stmt = $this->db->prepare("SELECT * FROM kea_config_backups WHERE id = ?");
            $stmt->execute([$backupId]);
            $backup = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$backup) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Backup not found'
                ], 404);
                return;
            }

            // Get server info
            $stmt = $this->db->prepare("SELECT api_url FROM kea_servers WHERE id = ?");
            $stmt->execute([$backup['server_id']]);
            $server = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$server) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Server not found'
                ], 404);
                return;
            }

            $config = json_decode($backup['config_json'], true);
            
            // Send config-set command to Kea
            $ch = curl_init($server['api_url']);
            $data = [
                "command" => "config-set",
                "service" => ["dhcp6"],
                "arguments" => $config
            ];
            
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            curl_close($ch);

            if (!$response) {
                throw new \Exception('Failed to connect to Kea server');
            }

            $decoded = json_decode($response, true);
            if (!isset($decoded[0]['result']) || $decoded[0]['result'] !== 0) {
                throw new \Exception($decoded[0]['text'] ?? 'Config-set failed');
            }

            // Write to disk
            $ch = curl_init($server['api_url']);
            $data = [
                "command" => "config-write",
                "service" => ["dhcp6"],
                "arguments" => (object)[]
            ];
            
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            curl_close($ch);

            $this->jsonResponse([
                'success' => true,
                'message' => 'Configuration restored successfully'
            ]);

        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to restore: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete all leases from Kea
     * POST /api/admin/leases/delete-all
     */
    public function deleteAllLeases()
    {
        try {
            // Get first active Kea server from database
            $stmt = $this->db->prepare("SELECT api_url, name FROM kea_servers WHERE is_active = 1 ORDER BY priority LIMIT 1");
            $stmt->execute();
            $keaServer = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$keaServer) {
                throw new \Exception("No active Kea servers configured");
            }
            
            $keaApiUrl = $keaServer['api_url'];
            error_log("Deleting all leases from Kea server: {$keaServer['name']} ($keaApiUrl)");
            
            // First, get all subnets
            $data = [
                'command' => 'subnet6-list',
                'service' => ['dhcp6']
            ];
            
            $ch = curl_init($keaApiUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            
            $response = curl_exec($ch);
            curl_close($ch);
            
            $keaResponse = json_decode($response, true);
            
            if (!$keaResponse || !isset($keaResponse[0]['arguments']['subnets'])) {
                throw new \Exception("Failed to get subnets from Kea");
            }
            
            $subnets = $keaResponse[0]['arguments']['subnets'];
            error_log("Found " . count($subnets) . " subnets");
            
            $totalDeleted = 0;
            $errors = [];
            
            // Wipe leases for each subnet using lease6-wipe
            foreach ($subnets as $subnet) {
                $subnetId = $subnet['id'];
                error_log("Wiping leases for subnet ID: $subnetId ({$subnet['subnet']})");
                
                $wipeData = [
                    'command' => 'lease6-wipe',
                    'service' => ['dhcp6'],
                    'arguments' => [
                        'subnet-id' => $subnetId
                    ]
                ];
                
                $ch = curl_init($keaApiUrl);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($wipeData));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                
                $wipeResponse = curl_exec($ch);
                curl_close($ch);
                
                $wipeResult = json_decode($wipeResponse, true);
                
                if ($wipeResult && isset($wipeResult[0]['result']) && isset($wipeResult[0]['text'])) {
                    $resultCode = $wipeResult[0]['result'];
                    $resultText = $wipeResult[0]['text'];
                    
                    // Extract deleted count from text like "Deleted 5 lease(s) from subnet(s) 1"
                    if (preg_match('/Deleted (\d+)/', $resultText, $matches)) {
                        $count = intval($matches[1]);
                        $totalDeleted += $count;
                        
                        if ($count > 0) {
                            error_log("✓ Deleted $count lease(s) from subnet $subnetId");
                        } else {
                            error_log("ℹ Subnet $subnetId had no leases to delete");
                        }
                        // Don't treat "Deleted 0" as an error even if result code is non-zero
                    } else if ($resultCode !== 0) {
                        // Actual error - couldn't parse "Deleted X" and result is non-zero
                        $errors[] = "Subnet $subnetId: $resultText";
                        error_log("✗ Failed to wipe subnet $subnetId: $resultText");
                    }
                } else {
                    $errors[] = "Subnet $subnetId: Invalid response from Kea";
                    error_log("✗ Invalid response for subnet $subnetId");
                }
            }
            
            $this->jsonResponse([
                'success' => true,
                'message' => "Deleted $totalDeleted lease(s) from " . count($subnets) . " subnet(s)",
                'deleted' => $totalDeleted,
                'subnets_processed' => count($subnets),
                'errors' => $errors
            ]);
            
        } catch (\Exception $e) {
            error_log("Delete all leases error: " . $e->getMessage());
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to delete leases: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper: Send JSON response
     */
    private function jsonResponse($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
