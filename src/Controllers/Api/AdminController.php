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
            
            // Parse JSON (remove comments first)
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
                    foreach ($subnet['option-data'] as $option) {
                        if (($option['name'] ?? null) === 'ccap-core' || ($option['code'] ?? null) == 61) {
                            $ccapCore = $option['data'];
                            break;
                        }
                    }
                }
                
                $subnets[] = [
                    'id' => $subnet['id'],
                    'subnet' => $subnet['subnet'],
                    'pool' => $pool,
                    'relay' => $relay,
                    'ccap_core' => $ccapCore,
                    'valid_lifetime' => $subnet['valid-lifetime'] ?? 7200,
                    'preferred_lifetime' => $subnet['preferred-lifetime'] ?? 3600,
                    'reservations_count' => isset($subnet['reservations']) ? count($subnet['reservations']) : 0
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
            
            $this->jsonResponse([
                'success' => true,
                'subnets' => $subnets,
                'total' => count($subnets)
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
        
        $keaApiUrl = $_ENV['KEA_API_ENDPOINT'];
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
                
                // Create subnet in Kea first
                $keaSubnet = [
                    "subnet" => $subnet['subnet'],
                    "id" => $subnet['id'],
                    "shared-network-name" => null,
                    "pools" => []
                ];
                
                // Add pool if available
                if ($subnet['pool']) {
                    $keaSubnet['pools'][] = ["pool" => $subnet['pool']];
                }
                
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
                
                // Send to Kea
                $data = [
                    "command" => 'remote-subnet6-set',
                    "service" => ['dhcp6'],
                    "arguments" => [
                        "remote" => ["type" => "mysql"],
                        "server-tags" => ["all"],
                        "subnets" => [$keaSubnet]
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
                
                if (!$keaResponse || !isset($keaResponse[0]['result']) || $keaResponse[0]['result'] !== 0) {
                    throw new \Exception("Kea API error: " . json_encode($keaResponse));
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
                            // Switch already exists - check if it has BVI100
                            $bviCheckStmt = $this->db->prepare("
                                SELECT id FROM cin_switch_bvi_interfaces 
                                WHERE switch_id = ? AND interface_number = 100
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
                        
                        // Create BVI100 interface
                        $cinSwitchModel->createBviInterface($switchId, [
                            'interface_number' => 100, // Always BVI100
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
                            (switch_id, kea_subnet_id, interface_number, ipv6_address, start_address, end_address, ccap_core)
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $switchId,
                            $subnet['id'],
                            100,
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
                        (switch_id, kea_subnet_id, interface_number, ipv6_address, start_address, end_address, ccap_core)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $bvi['switch_id'],
                        $subnet['id'],
                        $bvi['interface_number'],
                        $bvi['ipv6_address'],
                        $poolStart,
                        $poolEnd,
                        $subnet['ccap_core']
                    ]);
                    
                    $details[] = "✓ Linked subnet {$subnet['subnet']} to existing BVI{$bvi['interface_number']}";
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
        $dbHost = $_ENV['DB_HOST'] ?? 'localhost';
        $dbName = $_ENV['DB_NAME'] ?? 'kea_db';
        $dbUser = $_ENV['DB_USER'] ?? 'kea_db_user';
        $dbPass = $_ENV['DB_PASSWORD'] ?? '';

        // Execute mysqldump
        $command = sprintf(
            "mysqldump -h %s -u %s -p'%s' %s > %s 2>&1",
            escapeshellarg($dbHost),
            escapeshellarg($dbUser),
            $dbPass,
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
        $filename = 'kea-leases-' . date('Y-m-d-His') . '.sql';
        $filepath = BASE_PATH . '/backups/' . $filename;

        if (!file_exists(BASE_PATH . '/backups')) {
            mkdir(BASE_PATH . '/backups', 0755, true);
        }

        // Backup only lease-related tables
        $dbHost = $_ENV['DB_HOST'] ?? 'localhost';
        $dbName = $_ENV['DB_NAME'] ?? 'kea_db';
        $dbUser = $_ENV['DB_USER'] ?? 'kea_db_user';
        $dbPass = $_ENV['DB_PASSWORD'] ?? '';

        $command = sprintf(
            "mysqldump -h %s -u %s -p'%s' %s lease6 hosts > %s 2>&1",
            escapeshellarg($dbHost),
            escapeshellarg($dbUser),
            $dbPass,
            escapeshellarg($dbName),
            escapeshellarg($filepath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode === 0 && file_exists($filepath)) {
            // Clean up old backups (keep last 7)
            $this->cleanupOldBackups('kea-leases', 7);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Leases backup created successfully on server',
                'filename' => $filename,
                'size' => $this->formatFileSize(filesize($filepath)),
                'path' => '/backups/' . $filename
            ]);
        } else {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Backup failed'
            ], 500);
        }
    }

    /**
     * Export Kea leases to CSV
     * GET /api/admin/export/kea-leases-csv
     */
    public function exportKeaLeasesCSV()
    {
        $filename = 'kea-leases-' . date('Y-m-d-His') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Address', 'DUID', 'Valid Lifetime', 'Expire', 'Subnet ID', 'Hostname']);

        $query = "SELECT 
            INET6_NTOA(address) as address,
            HEX(duid) as duid,
            valid_lifetime,
            expire,
            subnet_id,
            hostname
            FROM lease6
            ORDER BY expire DESC";

        $stmt = $this->db->query($query);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
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

        $query = "SELECT nasname, shortname, type, secret, description FROM radius_clients ORDER BY nasname";
        $stmt = $this->db->query($query);

        echo "# FreeRADIUS clients.conf\n";
        echo "# Generated: " . date('Y-m-d H:i:s') . "\n\n";

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "client " . $row['shortname'] . " {\n";
            echo "    ipv6addr = " . $row['nasname'] . "\n";
            echo "    secret = " . $row['secret'] . "\n";
            echo "    shortname = " . $row['shortname'] . "\n";
            echo "    nastype = " . $row['type'] . "\n";
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
            "mysqldump -h %s -P %d -u %s -p'%s' %s > %s 2>&1",
            escapeshellarg($server['host']),
            $server['port'],
            escapeshellarg($server['username']),
            $server['password'],
            escapeshellarg($server['database']),
            escapeshellarg($filepath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode === 0 && file_exists($filepath)) {
            // Clean up old backups (keep last 7 per type)
            $this->cleanupOldBackups('radius-' . $type, 7);
            
            $this->jsonResponse([
                'success' => true,
                'message' => ucfirst($type) . ' RADIUS backup created successfully on server',
                'filename' => $filename,
                'size' => $this->formatFileSize(filesize($filepath)),
                'path' => '/backups/' . $filename
            ]);
        } else {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Backup failed'
            ], 500);
        }
    }

    /**
     * Full system backup
     * GET /api/admin/backup/full-system
     */
    public function fullSystemBackup()
    {
        $this->jsonResponse([
            'success' => false,
            'message' => 'Full system backup coming soon'
        ]);
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
     * Clear all CIN data (switches, BVI interfaces, links)
     * POST /api/admin/clear-cin-data
     */
    public function clearCinData()
    {
        try {
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
            
            // Delete in correct order (respect foreign keys)
            $this->db->exec("DELETE FROM cin_bvi_dhcp_core");
            $this->db->exec("DELETE FROM cin_switch_bvi_interfaces");
            $this->db->exec("DELETE FROM cin_switches");
            
            // Delete RADIUS clients if table exists
            try {
                $this->db->exec("DELETE FROM radius_clients");
            } catch (\Exception $e) {
                // Table might not exist
            }
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'CIN data cleared successfully',
                'switches' => $switchCount,
                'bvi_interfaces' => $bviCount,
                'links' => $linkCount,
                'radius_clients' => $radiusCount
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to clear CIN data: ' . $e->getMessage()
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
