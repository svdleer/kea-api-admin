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
                        $this->insertClient($conn, $clientData);
                        break;
                    case 'UPDATE':
                        $this->updateClient($conn, $clientData);
                        break;
                    case 'DELETE':
                        $this->deleteClient($conn, $clientData['id']);
                        break;
                }

                $results[$server['name']] = [
                    'success' => true,
                    'message' => ucfirst(strtolower($operation)) . ' successful'
                ];
            } catch (PDOException $e) {
                error_log("Failed to sync to {$server['name']}: " . $e->getMessage());
                $results[$server['name']] = [
                    'success' => false,
                    'message' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Insert client into RADIUS database
     * Uses INSERT...ON DUPLICATE KEY UPDATE to handle duplicates gracefully
     */
    private function insertClient($conn, $clientData)
    {
        // First check if nasname already exists
        $checkQuery = "SELECT id FROM nas WHERE nasname = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->execute([$clientData['nasname']]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Update existing entry instead
            error_log("RADIUS client with nasname {$clientData['nasname']} already exists (ID: {$existing['id']}), updating instead");
            $this->updateClient($conn, array_merge($clientData, ['id' => $existing['id']]));
            return;
        }

        $query = "INSERT INTO nas 
                  (id, nasname, shortname, type, ports, secret, server, community, description) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($query);
        $stmt->execute([
            $clientData['id'],
            $clientData['nasname'],
            $clientData['shortname'],
            $clientData['type'] ?? 'other',
            $clientData['ports'] ?? null,
            $clientData['secret'],
            $clientData['server'] ?? null,
            $clientData['community'] ?? null,
            $clientData['description'] ?? 'Auto-synced from KEA Admin'
            date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Update client in RADIUS database
     */
    private function updateClient($conn, $clientData)
    {
        $query = "UPDATE nas SET 
                  nasname = ?,
                  shortname = ?,
                  type = ?,
                  ports = ?,
                  secret = ?,
                  server = ?,
                  community = ?,
                  description = ?,
                  updated_at = ?
                  WHERE id = ?";

        $stmt = $conn->prepare($query);
        $stmt->execute([
            $clientData['nasname'],
            $clientData['shortname'],
            $clientData['type'] ?? 'other',
            $clientData['ports'] ?? null,
            $clientData['secret'],
            $clientData['server'] ?? null,
            $clientData['community'] ?? null,
            $clientData['description'] ?? 'Auto-synced from KEA Admin',
            date('Y-m-d H:i:s'),
            $clientData['id']
        ]);
    }

    /**
     * Delete client from RADIUS database
     */
    private function deleteClient($conn, $clientId)
    {
        $query = "DELETE FROM nas WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$clientId]);
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
     * Get list of configured servers
     */
    public function getServers()
    {
        return $this->servers;
    }
}
