<?php

namespace App\Controllers\Api;

use App\Models\RadiusClient;
use App\Auth\Authentication;

class RadiusController
{
    private $radiusModel;
    private $auth;

    public function __construct(RadiusClient $radiusModel, Authentication $auth)
    {
        $this->radiusModel = $radiusModel;
        $this->auth = $auth;
    }

    /**
     * Get all RADIUS clients
     * GET /api/radius/clients
     */
    public function getAllClients()
    {
        try {
            $clients = $this->radiusModel->getAllClients();
            
            $this->jsonResponse([
                'success' => true,
                'clients' => $clients
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get RADIUS client by ID
     * GET /api/radius/clients/{id}
     */
    public function getClientById($id)
    {
        try {
            $client = $this->radiusModel->getClientById($id);
            
            if (!$client) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'RADIUS client not found'
                ], 404);
                return;
            }

            $this->jsonResponse([
                'success' => true,
                'client' => $client
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create RADIUS client from BVI interface
     * POST /api/radius/clients
     */
    public function createClient()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['bvi_interface_id']) || !isset($data['ipv6_address'])) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Missing required fields: bvi_interface_id, ipv6_address'
                ], 400);
                return;
            }

            $clientId = $this->radiusModel->createFromBvi(
                $data['bvi_interface_id'],
                $data['ipv6_address'],
                $data['secret'] ?? null,
                $data['shortname'] ?? null
            );

            $client = $this->radiusModel->getClientById($clientId);

            $this->jsonResponse([
                'success' => true,
                'message' => 'RADIUS client created successfully',
                'client' => $client
            ], 201);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update RADIUS client
     * PUT /api/radius/clients/{id}
     */
    public function updateClient($id)
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (empty($data)) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'No data provided'
                ], 400);
                return;
            }

            $updated = $this->radiusModel->updateClient($id, $data);

            if (!$updated) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'RADIUS client not found or no changes made'
                ], 404);
                return;
            }

            $client = $this->radiusModel->getClientById($id);

            $this->jsonResponse([
                'success' => true,
                'message' => 'RADIUS client updated successfully',
                'client' => $client
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete RADIUS client
     * DELETE /api/radius/clients/{id}
     */
    public function deleteClient($id)
    {
        try {
            $deleted = $this->radiusModel->deleteClient($id);

            if (!$deleted) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'RADIUS client not found'
                ], 404);
                return;
            }

            $this->jsonResponse([
                'success' => true,
                'message' => 'RADIUS client deleted successfully'
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync all BVI interfaces to RADIUS clients
     * POST /api/radius/sync
     */
    public function syncBviInterfaces()
    {
        try {
            $synced = $this->radiusModel->syncAllBviInterfaces();

            $this->jsonResponse([
                'success' => true,
                'message' => "$synced BVI interface(s) synced to RADIUS clients",
                'synced_count' => $synced
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get global secret configuration
     * GET /api/radius/global-secret
     */
    public function getGlobalSecret()
    {
        try {
            $globalSecret = $this->radiusModel->getCurrentGlobalSecret();
            
            $this->jsonResponse([
                'success' => true,
                'has_global_secret' => !empty($globalSecret),
                'secret' => $globalSecret ?: null
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update global secret and apply to all clients
     * PUT /api/radius/global-secret
     */
    public function updateGlobalSecret()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);

            if (!isset($data['secret']) || empty($data['secret'])) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Secret is required'
                ], 400);
                return;
            }

            $secret = $data['secret'];
            $applyToAll = $data['apply_to_all'] ?? true;

            // Store global secret in database (config file is read-only in container)
            $db = $this->radiusModel->getDatabase();
            $stmt = $db->prepare("
                INSERT INTO app_config (config_key, config_value) 
                VALUES ('radius_global_secret', ?) 
                ON DUPLICATE KEY UPDATE config_value = ?
            ");
            $stmt->execute([$secret, $secret]);

            $updatedCount = 0;
            if ($applyToAll) {
                $updatedCount = $this->radiusModel->applyGlobalSecretToAll($secret);
            }

            $this->jsonResponse([
                'success' => true,
                'message' => $applyToAll 
                    ? "Global secret updated and applied to $updatedCount client(s)"
                    : "Global secret updated (not applied to existing clients)",
                'updated_count' => $updatedCount
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get RADIUS servers status
     * GET /api/radius/servers/status
     */
    public function getServersStatus()
    {
        try {
            $status = $this->radiusModel->testServerConnections();
            
            $this->jsonResponse([
                'success' => true,
                'servers' => $status
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Force sync all clients to RADIUS servers
     * POST /api/radius/servers/sync
     */
    public function forceSyncServers()
    {
        try {
            $result = $this->radiusModel->forceSyncToServers();
            
            $this->jsonResponse([
                'success' => true,
                'message' => "Synced {$result['synced']} client(s) to RADIUS servers",
                'synced' => $result['synced'],
                'errors' => $result['errors']
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get RADIUS servers configuration
     * GET /api/radius/servers/config
     */
    public function getServersConfig()
    {
        try {
            require_once BASE_PATH . '/src/Models/RadiusServerConfig.php';
            $configModel = new \App\Models\RadiusServerConfig($this->radiusModel->getDatabase());
            $servers = $configModel->getAllServers();
            
            $this->jsonResponse([
                'success' => true,
                'servers' => $servers
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Update RADIUS server configuration
     * PUT /api/radius/servers/config
     */
    public function updateServerConfig()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            error_log("RadiusController::updateServerConfig received data: " . json_encode($data));
            
            if (!isset($data['index']) || !isset($data['server'])) {
                error_log("RadiusController: Missing index or server data");
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Missing index or server data'
                ], 400);
                return;
            }
            
            require_once BASE_PATH . '/src/Models/RadiusServerConfig.php';
            $configModel = new \App\Models\RadiusServerConfig($this->radiusModel->getDatabase());
            
            $success = $configModel->saveServer($data['server'], $data['index']);
            
            if ($success) {
                error_log("RadiusController: Server configuration saved successfully");
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Server configuration saved successfully'
                ]);
            } else {
                error_log("RadiusController: Failed to save server configuration");
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Failed to save server configuration'
                ], 500);
            }
        } catch (\Exception $e) {
            error_log("RadiusController: Exception - " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Test RADIUS server connection
     * POST /api/radius/servers/test
     */
    public function testServerConnection()
    {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['server'])) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => 'Missing server configuration'
                ], 400);
                return;
            }
            
            $server = $data['server'];
            
            $startTime = microtime(true);
            try {
                $dsn = sprintf(
                    'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                    $server['host'],
                    $server['port'],
                    $server['database']
                );
                
                $conn = new \PDO($dsn, $server['username'], $server['password'], [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                ]);
                
                // Check if nas table exists
                $stmt = $conn->query("SHOW TABLES LIKE 'nas'");
                $tableExists = $stmt->rowCount() > 0;
                
                if (!$tableExists) {
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
                    
                    $conn->exec($createTableSQL);
                    $tableCreated = true;
                } else {
                    $tableCreated = false;
                }
                
                $stmt = $conn->query('SELECT COUNT(*) as count FROM nas');
                $result = $stmt->fetch();
                $responseTime = round((microtime(true) - $startTime) * 1000, 2);
                
                $message = 'Connection successful';
                if ($tableCreated) {
                    $message .= ' (nas table created automatically)';
                }
                
                $this->jsonResponse([
                    'success' => true,
                    'message' => $message,
                    'client_count' => $result['count'],
                    'response_time' => $responseTime,
                    'table_created' => $tableCreated
                ]);
            } catch (\PDOException $e) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper function to send JSON response
     */
    private function jsonResponse($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
