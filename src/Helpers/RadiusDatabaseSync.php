<?php

namespace App\Helpers;

use PDO;
use PDOException;

class RadiusDatabaseSync
{
    private $servers = [];
    private $connections = [];
    private $db;

    public function __construct($db = null)
    {
        $this->db = $db ?: \App\Database\Database::getInstance();
        $this->loadServersFromDatabase();
    }

    /**
     * Load server configurations from database
     */
    private function loadServersFromDatabase()
    {
        try {
            require_once BASE_PATH . '/src/Models/RadiusServerConfig.php';
            $configModel = new \App\Models\RadiusServerConfig($this->db);
            $this->servers = $configModel->getServersForSync();
        } catch (\Exception $e) {
            error_log("Error loading RADIUS servers from database: " . $e->getMessage());
            // Fall back to config file if database fails
            $this->loadServersFromConfig();
        }
    }

    /**
     * Fall back to loading from config file
     */
    private function loadServersFromConfig()
    {
        $configFile = BASE_PATH . '/config/radius.php';
        if (file_exists($configFile)) {
            $config = require $configFile;
            $this->servers = $config['servers'] ?? [];
        }
    }

    /**
     * Get connection to a RADIUS server database
     */
    private function getConnection($serverConfig)
    {
        $key = $serverConfig['host'] . ':' . $serverConfig['database'];
        
        if (isset($this->connections[$key])) {
            return $this->connections[$key];
        }

        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $serverConfig['host'],
                $serverConfig['port'],
                $serverConfig['database'],
                $serverConfig['charset']
            );

            $connection = new PDO($dsn, $serverConfig['username'], $serverConfig['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);

            // Check if nas table exists, create if not
            $this->ensureTableExists($connection);

            $this->connections[$key] = $connection;
            return $connection;
        } catch (PDOException $e) {
            error_log("Failed to connect to RADIUS server {$serverConfig['name']}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Ensure nas table exists, create if not
     */
    private function ensureTableExists($connection)
    {
        try {
            // Check if table exists
            $stmt = $connection->query("SHOW TABLES LIKE 'nas'");
            $tableExists = $stmt->rowCount() > 0;

            if (!$tableExists) {
                error_log("Table 'nas' not found, creating it...");
                
                // Create the nas table
                $createTableSQL = "
                    CREATE TABLE `nas` (
                        `id` int(10) NOT NULL AUTO_INCREMENT,
                        `nasname` varchar(128) NOT NULL,
                        `shortname` varchar(32) DEFAULT NULL,
                        `type` varchar(30) DEFAULT 'other',
                        `ports` int(5) DEFAULT NULL,
                        `secret` varchar(60) NOT NULL DEFAULT 'secret',
                        `server` varchar(64) DEFAULT NULL,
                        `community` varchar(50) DEFAULT NULL,
                        `description` varchar(200) DEFAULT 'RADIUS Client',
                        `bvi_interface_id` int(11) DEFAULT NULL,
                        `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                        `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (`id`),
                        UNIQUE KEY `nasname` (`nasname`),
                        KEY `bvi_interface_id` (`bvi_interface_id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
                ";
                
                $connection->exec($createTableSQL);
                error_log("Table 'nas' created successfully");
            }
        } catch (PDOException $e) {
            error_log("Error checking/creating nas table: " . $e->getMessage());
            // Don't throw, let it continue - table might exist but we can't detect it
        }
    }

    /**
     * Sync a RADIUS client to all configured servers
     */
    public function syncClientToAllServers($clientData, $operation = 'INSERT')
    {
        $results = [];

        foreach ($this->servers as $server) {
            if (!$server['enabled']) {
                $results[$server['name']] = [
                    'success' => false,
                    'message' => 'Server disabled in configuration',
                    'skipped' => true
                ];
                continue;
            }

            try {
                $conn = $this->getConnection($server);
                
                switch (strtoupper($operation)) {
                    case 'INSERT':
                        $radiusId = $this->insertClient($conn, $clientData, $server['name']);
                        $results[$server['name']] = [
                            'success' => true,
                            'message' => 'Insert successful',
                            'radius_id' => $radiusId
                        ];
                        break;
                    case 'UPDATE':
                        $this->updateClient($conn, $clientData, $server['name']);
                        $results[$server['name']] = [
                            'success' => true,
                            'message' => 'Update successful'
                        ];
                        break;
                    case 'DELETE':
                        $this->deleteClient($conn, $clientData, $server['name']);
                        $results[$server['name']] = [
                            'success' => true,
                            'message' => 'Delete successful'
                        ];
                        break;
                }
            } catch (PDOException $e) {
                error_log("Failed to sync to {$server['name']}: " . $e->getMessage());
                $results[$server['name']] = [
                    'success' => false,
                    'message' => $e->getMessage()
                ];
            }
        }

        // Auto-reload FreeRADIUS on all servers that have auto_reload enabled
        $this->autoReloadFreeRadius();

        return $results;
    }

    /**
     * Insert client into RADIUS database
     * Returns the RADIUS server's auto-increment ID for the inserted client
     */
    private function insertClient($conn, $clientData, $serverName)
    {
        // First check if nasname already exists
        $checkQuery = "SELECT id FROM nas WHERE nasname = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->execute([$clientData['nasname']]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Update existing entry instead and return its ID
            error_log("[$serverName] NAS {$clientData['shortname']} ({$clientData['nasname']}) already exists (ID: {$existing['id']}), updating instead");
            $this->updateClient($conn, $clientData, $serverName);
            return $existing['id'];
        }

        error_log("[$serverName] Inserting new NAS: {$clientData['shortname']} ({$clientData['nasname']})");

        // Insert without specifying ID - let RADIUS server auto-generate it
        $query = "INSERT INTO nas 
                  (nasname, shortname, type, ports, secret, server, community, description) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($query);
        $stmt->execute([
            $clientData['nasname'],
            $clientData['shortname'],
            $clientData['type'] ?? 'other',
            $clientData['ports'] ?? null,
            $clientData['secret'],
            $clientData['server'] ?? null,
            $clientData['community'] ?? null,
            $clientData['description'] ?? 'Auto-synced from KEA Admin'
        ]);
        
        // Return the RADIUS server's auto-generated ID
        $radiusId = $conn->lastInsertId();
        error_log("[$serverName] NAS {$clientData['shortname']} ({$clientData['nasname']}) inserted with RADIUS ID: $radiusId");
        error_log("Inserted client on $serverName, got RADIUS ID: $radiusId");
        return $radiusId;
    }

    /**
     * Update client in RADIUS database using the stored RADIUS server ID
     */
    private function updateClient($conn, $clientData, $serverName)
    {
        // Determine which RADIUS ID column to use based on server name
        $radiusIdField = (stripos($serverName, 'Primary') !== false) ? 'radius_primary_id' : 'radius_secondary_id';
        $radiusId = $clientData[$radiusIdField] ?? null;
        
        error_log("RadiusSync: Updating client on $serverName, nasname={$clientData['nasname']}, shortname={$clientData['shortname']}, stored_radius_id=$radiusId");
        
        if ($radiusId) {
            // Use stored RADIUS ID for precise update
            error_log("[$serverName] Updating NAS by RADIUS ID $radiusId: {$clientData['shortname']} ({$clientData['nasname']})");
            
            $query = "UPDATE nas SET 
                      nasname = ?,
                      shortname = ?,
                      type = ?,
                      ports = ?,
                      secret = ?,
                      server = ?,
                      community = ?,
                      description = ?
                      WHERE id = ?";

            $stmt = $conn->prepare($query);
            $result = $stmt->execute([
                $clientData['nasname'],
                $clientData['shortname'],
                $clientData['type'] ?? 'other',
                $clientData['ports'] ?? null,
                $clientData['secret'],
                $clientData['server'] ?? null,
                $clientData['community'] ?? null,
                $clientData['description'] ?? 'Auto-synced from KEA Admin',
                $radiusId
            ]);
            
            $rowCount = $stmt->rowCount();
            error_log("[$serverName] Update result: $rowCount row(s) affected");
        } else {
            // Fallback: update by nasname (for legacy clients without stored RADIUS IDs)
            error_log("[$serverName] No RADIUS ID stored, updating by nasname: {$clientData['nasname']}");
            error_log("No stored RADIUS ID for $serverName, using nasname fallback");
            $query = "UPDATE nas SET 
                      shortname = ?,
                      type = ?,
                      ports = ?,
                      secret = ?,
                      server = ?,
                      community = ?,
                      description = ?
                      WHERE nasname = ?";

            $stmt = $conn->prepare($query);
            $result = $stmt->execute([
                $clientData['shortname'],
                $clientData['type'] ?? 'other',
                $clientData['ports'] ?? null,
                $clientData['secret'],
                $clientData['server'] ?? null,
                $clientData['community'] ?? null,
                $clientData['description'] ?? 'Auto-synced from KEA Admin',
                $clientData['nasname']
            ]);
            
            $rowCount = $stmt->rowCount();
            error_log("[$serverName] Update by nasname result: $rowCount row(s) affected");
        }
        
        $rowCount = $stmt->rowCount();
        error_log("RadiusSync: Update on $serverName affected $rowCount row(s)");
        
        if ($rowCount === 0) {
            error_log("RadiusSync: WARNING - No rows updated for nasname={$clientData['nasname']}. Client may not exist on this server.");
        }
    }

    /**
     * Delete client from RADIUS database
     */
    private function deleteClient($conn, $clientData, $serverName)
    {
        // Determine which RADIUS ID column to use
        $radiusIdField = (stripos($serverName, 'Primary') !== false) ? 'radius_primary_id' : 'radius_secondary_id';
        $radiusId = $clientData[$radiusIdField] ?? null;
        
        if ($radiusId) {
            error_log("[$serverName] Deleting NAS by RADIUS ID $radiusId: {$clientData['shortname']} ({$clientData['nasname']})");
            $query = "DELETE FROM nas WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$radiusId]);
            $rowCount = $stmt->rowCount();
            error_log("[$serverName] Delete result: $rowCount row(s) deleted");
        } else {
            // Fallback: delete by nasname
            error_log("[$serverName] No RADIUS ID stored, deleting by nasname: {$clientData['nasname']}");
            $query = "DELETE FROM nas WHERE nasname = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$clientData['nasname']]);
            $rowCount = $stmt->rowCount();
            error_log("[$serverName] Delete by nasname result: $rowCount row(s) deleted");
        }
    }

    /**
     * Delete client from RADIUS database by nasname (IP address)
     * Used when cleaning up duplicate entries
     */
    public function deleteClientByNasname($nasname)
    {
        $results = [];

        foreach ($this->servers as $server) {
            if (!$server['enabled']) {
                $results[$server['name']] = [
                    'success' => false,
                    'message' => 'Server disabled in configuration',
                    'skipped' => true
                ];
                continue;
            }

            try {
                $conn = $this->getConnection($server);
                $query = "DELETE FROM nas WHERE nasname = ?";
                $stmt = $conn->prepare($query);
                $stmt->execute([$nasname]);
                
                $results[$server['name']] = [
                    'success' => true,
                    'message' => 'Deleted by nasname',
                    'affected_rows' => $stmt->rowCount()
                ];
            } catch (PDOException $e) {
                error_log("Failed to delete from {$server['name']}: " . $e->getMessage());
                $results[$server['name']] = [
                    'success' => false,
                    'message' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Sync all clients from Kea database to all RADIUS servers
     */
    public function syncAllClients($keaClients)
    {
        $totalSynced = 0;
        $errors = [];

        foreach ($keaClients as $client) {
            $results = $this->syncClientToAllServers($client, 'INSERT');
            
            foreach ($results as $serverName => $result) {
                if ($result['success']) {
                    $totalSynced++;
                } else if (!isset($result['skipped'])) {
                    $errors[] = "$serverName: {$result['message']}";
                }
            }
        }

        return [
            'synced' => $totalSynced,
            'errors' => $errors
        ];
    }

    /**
     * Test connection to all configured RADIUS servers
     */
    public function testAllConnections()
    {
        $results = [];

        foreach ($this->servers as $server) {
            if (!$server['enabled']) {
                $results[$server['name']] = [
                    'status' => 'disabled',
                    'message' => 'Server disabled in configuration',
                    'response_time' => null
                ];
                continue;
            }

            $startTime = microtime(true);
            try {
                $conn = $this->getConnection($server);
                $stmt = $conn->query('SELECT COUNT(*) as count FROM nas');
                $result = $stmt->fetch();
                $responseTime = round((microtime(true) - $startTime) * 1000, 2);

                $results[$server['name']] = [
                    'status' => 'online',
                    'message' => 'Connected successfully',
                    'client_count' => $result['count'],
                    'response_time' => $responseTime
                ];
            } catch (PDOException $e) {
                $results[$server['name']] = [
                    'status' => 'error',
                    'message' => $e->getMessage(),
                    'response_time' => null
                ];
            }
        }

        return $results;
    }

    /**
     * Clean up radpostauth entries for a specific NAS device
     */
    public function cleanupRadPostAuthForNAS($nasname)
    {
        $results = [];
        
        foreach ($this->servers as $server) {
            try {
                $conn = $this->getConnection($server['name']);
                if (!$conn) {
                    $results[$server['name']] = [
                        'success' => false,
                        'message' => 'Could not connect to server'
                    ];
                    continue;
                }

                // Delete radpostauth entries for this NAS
                $stmt = $conn->prepare("DELETE FROM radpostauth WHERE nasipaddress = ?");
                $stmt->execute([$nasname]);
                $deleted = $stmt->rowCount();

                $results[$server['name']] = [
                    'success' => true,
                    'deleted' => $deleted,
                    'message' => "Deleted $deleted radpostauth entries for NAS $nasname"
                ];
                
                error_log("Cleaned up $deleted radpostauth entries for NAS $nasname on {$server['name']}");
            } catch (PDOException $e) {
                $results[$server['name']] = [
                    'success' => false,
                    'message' => $e->getMessage()
                ];
                error_log("Error cleaning radpostauth for NAS $nasname on {$server['name']}: " . $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * Get list of configured servers
     */
    public function getServers()
    {
        return $this->servers;
    }

    /**
     * Reload FreeRADIUS on all servers by sending HUP signal
     * This causes FreeRADIUS to reload its configuration including NAS clients from MySQL
     */
    public function reloadFreeRadius()
    {
        $results = [];
        
        foreach ($this->servers as $server) {
            $serverName = $server['name'];
            
            try {
                // Check if SSH command is configured
                if (empty($server['ssh_host'])) {
                    $results[$serverName] = [
                        'success' => false,
                        'message' => 'No SSH host configured - cannot reload FreeRADIUS'
                    ];
                    error_log("[$serverName] Cannot reload - no SSH host configured");
                    continue;
                }
                
                $sshHost = $server['ssh_host'];
                $sshUser = $server['ssh_user'] ?? 'root';
                $sshPort = $server['ssh_port'] ?? 22;
                
                // Build SSH command to send HUP signal to FreeRADIUS
                $command = sprintf(
                    "ssh -p %d %s@%s 'sudo systemctl reload freeradius || sudo killall -HUP radiusd' 2>&1",
                    $sshPort,
                    escapeshellarg($sshUser),
                    escapeshellarg($sshHost)
                );
                
                error_log("[$serverName] Reloading FreeRADIUS: $command");
                
                exec($command, $output, $returnCode);
                $outputStr = implode("\n", $output);
                
                if ($returnCode === 0) {
                    $results[$serverName] = [
                        'success' => true,
                        'message' => 'FreeRADIUS reloaded successfully',
                        'output' => $outputStr
                    ];
                    error_log("[$serverName] FreeRADIUS reloaded successfully");
                } else {
                    $results[$serverName] = [
                        'success' => false,
                        'message' => "Reload failed with code $returnCode",
                        'output' => $outputStr
                    ];
                    error_log("[$serverName] FreeRADIUS reload failed: $outputStr");
                }
            } catch (\Exception $e) {
                $results[$serverName] = [
                    'success' => false,
                    'message' => $e->getMessage()
                ];
                error_log("[$serverName] Exception during reload: " . $e->getMessage());
            }
        }
        
        return $results;
    }

    /**
     * Auto-reload FreeRADIUS only on servers that have auto_reload enabled
     * Called automatically after NAS client changes
     */
    private function autoReloadFreeRadius()
    {
        foreach ($this->servers as $server) {
            // Check if auto_reload is enabled (default true if not set)
            $autoReload = $server['auto_reload'] ?? true;
            
            if ($autoReload && !empty($server['ssh_host'])) {
                $serverName = $server['name'];
                $sshHost = $server['ssh_host'];
                $sshUser = $server['ssh_user'] ?? 'root';
                $sshPort = $server['ssh_port'] ?? 22;
                
                try {
                    // Build SSH command to send HUP signal to FreeRADIUS
                    $command = sprintf(
                        "ssh -o ConnectTimeout=5 -p %d %s@%s 'sudo systemctl reload freeradius 2>/dev/null || sudo killall -HUP radiusd 2>/dev/null' 2>&1",
                        $sshPort,
                        escapeshellarg($sshUser),
                        escapeshellarg($sshHost)
                    );
                    
                    error_log("[$serverName] Auto-reloading FreeRADIUS after NAS sync");
                    
                    // Execute in background to not block the response
                    exec($command . " > /dev/null 2>&1 &");
                } catch (\Exception $e) {
                    error_log("[$serverName] Auto-reload failed: " . $e->getMessage());
                }
            }
        }
    }
}
