<?php

namespace App\Models;

use PDO;
use PDOException;

class RadiusServerConfig
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get all RADIUS server configurations
     */
    public function getAllServers()
    {
        try {
            $query = "SELECT * FROM radius_server_config ORDER BY display_order ASC";
            $stmt = $this->db->prepare($query);
            $stmt->execute();
            
            $servers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Decrypt passwords
            foreach ($servers as &$server) {
                if (!empty($server['password'])) {
                    $server['password'] = $this->decrypt($server['password']);
                }
            }
            
            return $servers;
        } catch (PDOException $e) {
            error_log("Error getting RADIUS servers: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get server by display order
     */
    public function getServerByOrder($order)
    {
        try {
            $query = "SELECT * FROM radius_server_config WHERE display_order = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$order]);
            
            $server = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($server && !empty($server['password'])) {
                $server['password'] = $this->decrypt($server['password']);
            }
            
            return $server;
        } catch (PDOException $e) {
            error_log("Error getting RADIUS server: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update or insert server configuration
     */
    public function saveServer($data, $order)
    {
        try {
            error_log("RadiusServerConfig::saveServer called with order=$order, data=" . json_encode($data));
            
            // Encrypt password only if provided
            $encryptedPassword = !empty($data['password']) ? $this->encrypt($data['password']) : '';
            
            // Check if server exists (use raw query to avoid decryption issues)
            $query = "SELECT id FROM radius_server_config WHERE display_order = ?";
            $stmt = $this->db->prepare($query);
            $stmt->execute([$order]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                error_log("RadiusServerConfig: Updating existing server at order $order");
                
                // If password is empty, don't update it (keep existing)
                if (!empty($data['password'])) {
                    $query = "UPDATE radius_server_config 
                              SET name = ?, enabled = ?, host = ?, port = ?, 
                                  `database` = ?, username = ?, password = ?, charset = ?
                              WHERE display_order = ?";
                    
                    $stmt = $this->db->prepare($query);
                    $result = $stmt->execute([
                        $data['name'],
                        $data['enabled'] ? 1 : 0,
                        $data['host'],
                        $data['port'],
                        $data['database'],
                        $data['username'],
                        $encryptedPassword,
                        $data['charset'] ?? 'utf8mb4',
                        $order
                    ]);
                } else {
                    // Update without changing password
                    $query = "UPDATE radius_server_config 
                              SET name = ?, enabled = ?, host = ?, port = ?, 
                                  `database` = ?, username = ?, charset = ?
                              WHERE display_order = ?";
                    
                    $stmt = $this->db->prepare($query);
                    $result = $stmt->execute([
                        $data['name'],
                        $data['enabled'] ? 1 : 0,
                        $data['host'],
                        $data['port'],
                        $data['database'],
                        $data['username'],
                        $data['charset'] ?? 'utf8mb4',
                        $order
                    ]);
                }
                
                error_log("RadiusServerConfig: Update result = " . ($result ? 'success' : 'failed'));
                return $result;
            } else {
                error_log("RadiusServerConfig: Inserting new server at order $order");
                
                // Insert new
                $query = "INSERT INTO radius_server_config 
                          (name, enabled, host, port, `database`, username, password, charset, display_order) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $this->db->prepare($query);
                $result = $stmt->execute([
                    $data['name'],
                    $data['enabled'] ? 1 : 0,
                    $data['host'],
                    $data['port'],
                    $data['database'],
                    $data['username'],
                    $encryptedPassword,
                    $data['charset'] ?? 'utf8mb4',
                    $order
                ]);
                
                error_log("RadiusServerConfig: Insert result = " . ($result ? 'success' : 'failed'));
                return $result;
            }
        } catch (PDOException $e) {
            error_log("Error saving RADIUS server: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Get servers in format expected by RadiusDatabaseSync
     */
    public function getServersForSync()
    {
        $servers = $this->getAllServers();
        $formatted = [];
        
        foreach ($servers as $server) {
            $formatted[] = [
                'name' => $server['name'],
                'enabled' => (bool)$server['enabled'],
                'host' => $server['host'],
                'port' => (int)$server['port'],
                'database' => $server['database'],
                'username' => $server['username'],
                'password' => $server['password'],
                'charset' => $server['charset']
            ];
        }
        
        return $formatted;
    }

    /**
     * Simple encryption for passwords (using OpenSSL)
     */
    private function encrypt($plaintext)
    {
        if (empty($plaintext)) {
            return '';
        }
        
        $key = $this->getEncryptionKey();
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
        $encrypted = openssl_encrypt($plaintext, 'aes-256-cbc', $key, 0, $iv);
        
        return base64_encode($iv . $encrypted);
    }

    /**
     * Simple decryption for passwords
     */
    private function decrypt($ciphertext)
    {
        if (empty($ciphertext)) {
            return '';
        }
        
        try {
            $key = $this->getEncryptionKey();
            $data = base64_decode($ciphertext);
            $ivLength = openssl_cipher_iv_length('aes-256-cbc');
            $iv = substr($data, 0, $ivLength);
            $encrypted = substr($data, $ivLength);
            
            return openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
        } catch (\Exception $e) {
            error_log("Error decrypting password: " . $e->getMessage());
            return '';
        }
    }

    /**
     * Get encryption key (should be stored in environment variable)
     */
    private function getEncryptionKey()
    {
        // Try to get from environment first
        $key = getenv('RADIUS_ENCRYPTION_KEY');
        
        if (!$key) {
            // Fall back to a default key (not secure for production!)
            // In production, set RADIUS_ENCRYPTION_KEY environment variable
            $key = 'kea-admin-radius-default-key-change-me';
        }
        
        // Ensure key is 32 bytes for AES-256
        return hash('sha256', $key, true);
    }
}
