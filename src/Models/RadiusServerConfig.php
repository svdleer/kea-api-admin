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
            // Encrypt password
            $encryptedPassword = !empty($data['password']) ? $this->encrypt($data['password']) : '';
            
            // Check if server exists
            $existing = $this->getServerByOrder($order);
            
            if ($existing) {
                // Update existing
                $query = "UPDATE radius_server_config 
                          SET name = ?, enabled = ?, host = ?, port = ?, 
                              database = ?, username = ?, password = ?, charset = ?
                          WHERE display_order = ?";
                
                $stmt = $this->db->prepare($query);
                return $stmt->execute([
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
                // Insert new
                $query = "INSERT INTO radius_server_config 
                          (name, enabled, host, port, database, username, password, charset, display_order) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                $stmt = $this->db->prepare($query);
                return $stmt->execute([
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
            }
        } catch (PDOException $e) {
            error_log("Error saving RADIUS server: " . $e->getMessage());
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
