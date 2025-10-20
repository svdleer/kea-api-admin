<?php

namespace App\Models;

use PDO;


class ApiKey {
    private $db;
    const KEY_LENGTH = 32;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function createApiKey(string $name, bool $readOnly = false): ?string {
        $apiKey = bin2hex(random_bytes(self::KEY_LENGTH));
        $hashedKey = password_hash($apiKey, PASSWORD_DEFAULT);

        $sql = "INSERT INTO api_keys (name, api_key, read_only, created_at) 
                VALUES (?, ?, ?, NOW())";

        try {
            $stmt = $this->db->prepare($sql);
            $success = $stmt->execute([$name, $hashedKey, $readOnly ? 1 : 0]);

            return $success ? $apiKey : null;
        } catch (\PDOException $e) {
            return null;
        }
    }

    public function getApiKeys(): array {
        $sql = "SELECT id, name, read_only, created_at, last_used, active 
                FROM api_keys 
                ORDER BY created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deactivateApiKey(int $keyId): bool {
        $sql = "UPDATE api_keys SET active = 0 WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$keyId]);
    }

    public function deleteApiKey(int $id): bool {
        $sql = "DELETE FROM api_keys WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$id]);
    }
    


    public function validateApiKey(string $apiKey): ?array {
        $sql = "SELECT * FROM api_keys WHERE active = 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        while ($key = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if (password_verify($apiKey, $key['api_key'])) {
                return $key;
            }
        }

        return null;
    }

    public function updateLastUsed(int $keyId): void {
        $sql = "UPDATE api_keys SET last_used = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$keyId]);
    }


}
