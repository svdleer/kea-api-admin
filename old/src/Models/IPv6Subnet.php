<?php

namespace App\Models;

use PDO;

class IPv6Subnet {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function createSubnet(string $prefix, int $bviId, string $name, ?string $description = null): ?int {
        $stmt = $this->db->prepare('INSERT INTO ipv6_subnets (prefix, bvi_id, name, description) VALUES (?, ?, ?, ?)');
        if ($stmt->execute([$prefix, $bviId, $name, $description])) {
            return (int) $this->db->lastInsertId();
        }
        return null;
    }

    public function getSubnetById(int $id): ?array {
        $stmt = $this->db->prepare('
            SELECT s.*, sw.name as bvi_name, sw.ipv6_address as bvi_address 
            FROM ipv6_subnets s 
            JOIN switches sw ON s.bvi_id = sw.id 
            WHERE s.id = ?
        ');
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function updateSubnet(int $id, array $data): bool {
        $allowedFields = ['prefix', 'bvi_id', 'name', 'description', 'active'];
        $updates = [];
        $values = [];

        foreach ($data as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $updates[] = "$field = ?";
                $values[] = $value;
            }
        }

        if (empty($updates)) {
            return false;
        }

        $values[] = $id;
        $sql = 'UPDATE ipv6_subnets SET ' . implode(', ', $updates) . ' WHERE id = ?';
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    public function getAllSubnets(): array {
        $stmt = $this->db->prepare('
            SELECT s.*, sw.name as bvi_name, sw.ipv6_address as bvi_address 
            FROM ipv6_subnets s 
            JOIN switches sw ON s.bvi_id = sw.id 
            WHERE s.active = TRUE
        ');
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteSubnet(int $id): bool {
        $stmt = $this->db->prepare('DELETE FROM ipv6_subnets WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function getSubnetsByBvi(int $bviId): array {
        $stmt = $this->db->prepare('
            SELECT * FROM ipv6_subnets 
            WHERE bvi_id = ? AND active = TRUE
        ');
        $stmt->execute([$bviId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function calculatePool(string $prefix): array {
        // Remove any whitespace and convert to lowercase
        $prefix = strtolower(trim($prefix));
        
        // Split the prefix into network and length parts
        list($network, $length) = explode('/', $prefix);
        
        // Expand the abbreviated IPv6 address
        $full = $this->expandIPv6($network);
        
        // Calculate the start and end of the pool (::2 through ::fffe)
        $base = substr($full, 0, strrpos($full, ':') + 1);
        return [
            'start' => $base . '2',
            'end' => $base . 'fffe'
        ];
    }

    private function expandIPv6(string $ip): string {
        // If the IP contains ::, expand it
        if (strpos($ip, '::') !== false) {
            $part = explode('::', $ip);
            $left = explode(':', $part[0]);
            $right = isset($part[1]) ? explode(':', $part[1]) : [];
            $missing = 8 - count($left) - count($right);
            $expanded = array_merge(
                $left,
                array_fill(0, $missing, '0000'),
                $right
            );
        } else {
            $expanded = explode(':', $ip);
        }

        // Pad each part with zeros
        $expanded = array_map(function($part) {
            return str_pad($part, 4, '0', STR_PAD_LEFT);
        }, $expanded);

        return implode(':', $expanded);
    }

    public function validatePrefix(string $prefix): bool {
        // Remove any whitespace
        $prefix = trim($prefix);
        
        // Check basic format (should be something like 2001:db8::/64)
        if (!preg_match('/^[0-9a-fA-F:]+\/\d{1,3}$/', $prefix)) {
            return false;
        }
        
        // Split into address and prefix length
        list($address, $length) = explode('/', $prefix);
        
        // Validate prefix length
        if ($length < 1 || $length > 128) {
            return false;
        }
        
        // Try to expand the address
        try {
            $expanded = $this->expandIPv6($address);
            return filter_var($expanded, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getPool(string $prefix): ?array {
        if (!$this->validatePrefix($prefix)) {
            return null;
        }
        return $this->calculatePool($prefix);
    }
}