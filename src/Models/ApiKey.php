<?php

namespace App\Models;

use PDO;

class ApiKey
{
    private $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function createApiKey(int $userId, string $name, bool $readOnly = false): ?string
    {
        $apiKey = bin2hex(random_bytes(self::KEY_LENGTH));
        $hashedKey = password_hash($apiKey, PASSWORD_DEFAULT);

        $sql = "INSERT INTO api_keys (user_id, name, api_key, read_only, created_at) 
                VALUES (?, ?, ?, ?, NOW())";

        try {
            $stmt = $this->db->prepare($sql);
            $success = $stmt->execute([$userId, $name, $hashedKey, $readOnly ? 1 : 0]);

            return $success ? $apiKey : null;
        } catch (\PDOException $e) {
            return null;
        }
    }

    public function validateApiKey(string $apiKey): ?array
    {
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

    public function deactivateApiKey(int $keyId, int $userId): bool
    {
        $sql = "UPDATE api_keys SET active = 0 WHERE id = ? AND user_id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$keyId, $userId]);
    }

    public function getUserApiKeys(int $userId): array
    {
        $sql = "SELECT id, name, read_only, created_at, last_used, active 
                FROM api_keys WHERE user_id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateLastUsed(int $keyId): void
    {
        $sql = "UPDATE api_keys SET last_used = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$keyId]);
    }
}