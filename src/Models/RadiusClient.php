<?php

namespace App\Models;

use PDO;
use PDOException;

class RadiusClient
{
    private $db;
    private $radiusSync;

    public function __construct($db)
    {
        $this->db = $db;
        
        // Initialize RADIUS database sync
        require_once BASE_PATH . '/src/Helpers/RadiusDatabaseSync.php';
        $this->radiusSync = new \App\Helpers\RadiusDatabaseSync();
    }

    /**
     * Get database connection
     */
    public function getDatabase()
    {
        return $this->db;
    }

    /**
     * Get global RADIUS secret from configuration
     */
    private function getGlobalSecret()
    {
        $configFile = BASE_PATH . '/config/radius.php';
        if (file_exists($configFile)) {
            $config = require $configFile;
            $globalSecret = $config['global_secret'] ?? '';
            return !empty($globalSecret) ? $globalSecret : null;
        }
        return null;
    }

    /**
     * Get all RADIUS clients
     */
    public function getAllClients()
    {
        try {
            $query = "SELECT n.*, 
                             b.interface_number, 
                             s.hostname as switch_hostname,
                             s.id as switch_id
                      FROM nas n
                      LEFT JOIN cin_switch_bvi_interfaces b ON n.bvi_interface_id = b.id
                      LEFT JOIN cin_switches s ON b.switch_id = s.id
                      ORDER BY n.created_at DESC";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting all RADIUS clients: " . $e->getMessage());
            throw new \Exception("Failed to retrieve RADIUS clients");
        }
    }

    /**
     * Get RADIUS client by ID
     */
    public function getClientById($id)
    {
        try {
            $query = "SELECT n.*, 
                             b.interface_number, 
                             s.hostname as switch_hostname,
                             s.id as switch_id
                      FROM nas n
                      LEFT JOIN cin_switch_bvi_interfaces b ON n.bvi_interface_id = b.id
                      LEFT JOIN cin_switches s ON b.switch_id = s.id
                      WHERE n.id = ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([$id]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting RADIUS client by ID: " . $e->getMessage());
            throw new \Exception("Failed to retrieve RADIUS client");
        }
    }

    /**
     * Get RADIUS client by BVI interface ID
     */
    public function getClientByBviId($bviInterfaceId)
    {
        try {
            $query = "SELECT * FROM nas WHERE bvi_interface_id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$bviInterfaceId]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting RADIUS client by BVI ID: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Create RADIUS client from BVI interface
     */
    public function createFromBvi($bviInterfaceId, $ipv6Address, $secret = null, $shortname = null)
    {
        try {
            // Check if client already exists for this BVI
            $existing = $this->getClientByBviId($bviInterfaceId);
            if ($existing) {
                error_log("RADIUS client already exists for BVI interface ID: $bviInterfaceId");
                return $existing['id'];
            }

            // Use global secret if configured, otherwise use provided or generate new
            if ($secret === null) {
                $globalSecret = $this->getGlobalSecret();
                if ($globalSecret) {
                    $secret = $globalSecret;
                } else {
                    $secret = bin2hex(random_bytes(16));
                }
            }

            // Generate shortname if not provided
            if ($shortname === null) {
                $shortname = 'BVI-' . substr($ipv6Address, 0, 20);
            }

            $query = "INSERT INTO nas 
                      (nasname, shortname, type, secret, description, bvi_interface_id) 
                      VALUES (?, ?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $ipv6Address,
                $shortname,
                'other',
                $secret,
                'Auto-generated from BVI Interface',
                $bviInterfaceId
            ]);

            $clientId = $this->db->lastInsertId();
            
            // Sync to all RADIUS servers and store the returned RADIUS IDs
            try {
                $client = $this->getClientById($clientId);
                if ($client) {
                    $syncResults = $this->radiusSync->syncClientToAllServers($client, 'INSERT');
                    error_log("RADIUS client synced to servers: " . json_encode($syncResults));
                    
                    // Extract and store RADIUS server IDs
                    $radiusPrimaryId = null;
                    $radiusSecondaryId = null;
                    
                    foreach ($syncResults as $serverName => $result) {
                        if ($result['success'] && isset($result['radius_id'])) {
                            if (stripos($serverName, 'Primary') !== false) {
                                $radiusPrimaryId = $result['radius_id'];
                            } else if (stripos($serverName, 'Secondary') !== false) {
                                $radiusSecondaryId = $result['radius_id'];
                            }
                        }
                    }
                    
                    // Update the main DB with the RADIUS server IDs
                    if ($radiusPrimaryId || $radiusSecondaryId) {
                        $updateQuery = "UPDATE nas SET radius_primary_id = ?, radius_secondary_id = ? WHERE id = ?";
                        $updateStmt = $this->db->prepare($updateQuery);
                        $updateStmt->execute([$radiusPrimaryId, $radiusSecondaryId, $clientId]);
                        error_log("Stored RADIUS IDs - Primary: $radiusPrimaryId, Secondary: $radiusSecondaryId");
                    }
                }
            } catch (\Exception $e) {
                error_log("Failed to sync RADIUS client to servers: " . $e->getMessage());
                // Don't fail the main operation if sync fails
            }

            return $clientId;
        } catch (PDOException $e) {
            error_log("Error creating RADIUS client: " . $e->getMessage());
            throw new \Exception("Failed to create RADIUS client: " . $e->getMessage());
        }
    }

    /**
     * Create RADIUS client from array data (e.g., from import)
     */
    public function create($data)
    {
        try {
            // Check if client with this IP already exists
            if (isset($data['ip_address']) && $this->nasnameExists($data['ip_address'])) {
                throw new \Exception("RADIUS client with IP {$data['ip_address']} already exists");
            }

            // Use global secret if configured and no secret provided
            $secret = $data['secret'] ?? null;
            if ($secret === null) {
                $globalSecret = $this->getGlobalSecret();
                $secret = $globalSecret ?: bin2hex(random_bytes(16));
            }

            $query = "INSERT INTO nas 
                      (nasname, shortname, type, secret, description) 
                      VALUES (?, ?, ?, ?, ?)";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute([
                $data['ip_address'] ?? '',
                $data['name'] ?? 'Imported Client',
                $data['type'] ?? 'other',
                $secret,
                $data['description'] ?? 'Imported from clients.conf'
            ]);

            $clientId = $this->db->lastInsertId();
            
            // Sync to all RADIUS servers
            try {
                $client = $this->getClientById($clientId);
                if ($client) {
                    $syncResults = $this->radiusSync->syncClientToAllServers($client, 'INSERT');
                    error_log("RADIUS client synced to servers: " . json_encode($syncResults));
                }
            } catch (\Exception $e) {
                error_log("Failed to sync RADIUS client to servers: " . $e->getMessage());
                // Don't fail the main operation if sync fails
            }

            return $clientId;
        } catch (PDOException $e) {
            error_log("Error creating RADIUS client: " . $e->getMessage());
            throw new \Exception("Failed to create RADIUS client: " . $e->getMessage());
        }
    }

    /**
     * Update RADIUS client
     */
    public function updateClient($id, $data)
    {
        try {
            $allowedFields = ['nasname', 'shortname', 'type', 'ports', 'secret', 'server', 'community', 'description'];
            $updates = [];
            $params = [];

            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $data[$field];
                }
            }

            if (empty($updates)) {
                throw new \Exception("No valid fields to update");
            }

            $params[] = $id;
            $query = "UPDATE nas SET " . implode(', ', $updates) . " WHERE id = ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            $success = $stmt->rowCount() > 0;
            
            // Sync to all RADIUS servers
            if ($success) {
                try {
                    $client = $this->getClientById($id);
                    if ($client) {
                        $syncResults = $this->radiusSync->syncClientToAllServers($client, 'UPDATE');
                        error_log("RADIUS client updated on servers: " . json_encode($syncResults));
                    }
                } catch (\Exception $e) {
                    error_log("Failed to sync RADIUS client update to servers: " . $e->getMessage());
                }
            }

            return $success;
        } catch (PDOException $e) {
            error_log("Error updating RADIUS client: " . $e->getMessage());
            throw new \Exception("Failed to update RADIUS client");
        }
    }

    /**
     * Update RADIUS client IP address when BVI interface IP changes
     */
    public function updateClientIpByBviId($bviInterfaceId, $newIpv6Address)
    {
        try {
            $query = "UPDATE nas SET nasname = ? WHERE bvi_interface_id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$newIpv6Address, $bviInterfaceId]);

            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error updating RADIUS client IP: " . $e->getMessage());
            throw new \Exception("Failed to update RADIUS client IP address");
        }
    }

    /**
     * Delete RADIUS client
     */
    public function deleteClient($id)
    {
        try {
            // Get client data before deletion for sync
            $client = $this->getClientById($id);
            
            $query = "DELETE FROM nas WHERE id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$id]);
            
            $success = $stmt->rowCount() > 0;
            
            // Sync deletion to all RADIUS servers
            if ($success && $client) {
                try {
                    $syncResults = $this->radiusSync->syncClientToAllServers($client, 'DELETE');
                    error_log("RADIUS client deleted from servers: " . json_encode($syncResults));
                } catch (\Exception $e) {
                    error_log("Failed to sync RADIUS client deletion to servers: " . $e->getMessage());
                }
            }

            return $success;
        } catch (PDOException $e) {
            error_log("Error deleting RADIUS client: " . $e->getMessage());
            throw new \Exception("Failed to delete RADIUS client");
        }
    }

    /**
     * Delete RADIUS client by BVI interface ID
     * (This is automatically handled by CASCADE delete, but included for explicit control)
     */
    public function deleteClientByBviId($bviInterfaceId)
    {
        try {
            $query = "DELETE FROM nas WHERE bvi_interface_id = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$bviInterfaceId]);

            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error deleting RADIUS client by BVI ID: " . $e->getMessage());
            throw new \Exception("Failed to delete RADIUS client");
        }
    }

    /**
     * Check if nasname (IP address) exists
     */
    public function nasnameExists($nasname, $excludeId = null)
    {
        try {
            $query = "SELECT COUNT(*) as count FROM nas WHERE nasname = ?";
            $params = [$nasname];

            if ($excludeId !== null) {
                $query .= " AND id != ?";
                $params[] = $excludeId;
            }

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            return $result['count'] > 0;
        } catch (PDOException $e) {
            error_log("Error checking nasname existence: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Sync all BVI interfaces to RADIUS clients
     * Creates RADIUS clients for BVI interfaces that don't have one
     */
    public function syncAllBviInterfaces()
    {
        try {
            $query = "SELECT b.id, b.ipv6_address 
                      FROM cin_switch_bvi_interfaces b
                      LEFT JOIN nas n ON b.id = n.bvi_interface_id
                      WHERE n.id IS NULL";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            $missingBvis = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $synced = 0;
            foreach ($missingBvis as $bvi) {
                try {
                    $this->createFromBvi($bvi['id'], $bvi['ipv6_address']);
                    $synced++;
                } catch (\Exception $e) {
                    error_log("Failed to sync BVI {$bvi['id']}: " . $e->getMessage());
                }
            }

            return $synced;
        } catch (PDOException $e) {
            error_log("Error syncing BVI interfaces: " . $e->getMessage());
            throw new \Exception("Failed to sync BVI interfaces");
        }
    }

    /**
     * Apply global secret to all RADIUS clients
     */
    public function applyGlobalSecretToAll($globalSecret)
    {
        try {
            $query = "UPDATE nas SET secret = ? WHERE bvi_interface_id IS NOT NULL";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$globalSecret]);

            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("Error applying global secret: " . $e->getMessage());
            throw new \Exception("Failed to apply global secret to all clients");
        }
    }

    /**
     * Get current global secret from config
     */
    public function getCurrentGlobalSecret()
    {
        return $this->getGlobalSecret();
    }
    
    /**
     * Test connections to all RADIUS servers
     */
    public function testServerConnections()
    {
        return $this->radiusSync->testAllConnections();
    }
    
    /**
     * Force sync all clients to RADIUS servers
     */
    public function forceSyncToServers()
    {
        try {
            $clients = $this->getAllClients();
            return $this->radiusSync->syncAllClients($clients);
        } catch (\Exception $e) {
            error_log("Failed to force sync clients: " . $e->getMessage());
            throw $e;
        }
    }
}
