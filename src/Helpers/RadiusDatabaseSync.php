<?php

namespace App\Helpers;

use PDO;
use PDOException;

class RadiusDatabaseSync
{
    private $servers = [];
    private $connections = [];

    public function __construct()
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

            $this->connections[$key] = $connection;
            return $connection;
        } catch (PDOException $e) {
            error_log("Failed to connect to RADIUS server {$serverConfig['name']}: " . $e->getMessage());
            throw $e;
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
     */
    private function insertClient($conn, $clientData)
    {
        $query = "INSERT INTO nas 
                  (id, nasname, shortname, type, ports, secret, server, community, description, bvi_interface_id, created_at, updated_at) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                  ON DUPLICATE KEY UPDATE
                  nasname = VALUES(nasname),
                  shortname = VALUES(shortname),
                  type = VALUES(type),
                  ports = VALUES(ports),
                  secret = VALUES(secret),
                  server = VALUES(server),
                  community = VALUES(community),
                  description = VALUES(description),
                  bvi_interface_id = VALUES(bvi_interface_id),
                  updated_at = VALUES(updated_at)";

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
            $clientData['description'] ?? 'Auto-synced from KEA Admin',
            $clientData['bvi_interface_id'] ?? null,
            $clientData['created_at'] ?? date('Y-m-d H:i:s'),
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
