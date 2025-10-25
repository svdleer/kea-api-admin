<?php

namespace App\Models;

use PDO;

class BVIModel
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function getAllBviInterfaces($switchId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * 
                FROM cin_switch_bvi_interfaces 
                WHERE switch_id = ?
                ORDER BY interface_number
            ");
            $stmt->execute([$switchId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Error getting BVI interfaces: " . $e->getMessage());
            throw $e;
        }
    }

    public function getBviInterface($switchId, $bviId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * 
                FROM cin_switch_bvi_interfaces 
                WHERE switch_id = ? AND id = ?
            ");
            $stmt->execute([$switchId, $bviId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Error getting BVI interface: " . $e->getMessage());
            throw $e;
        }
    }

    public function createBviInterface($switchId, $data)
    {
        try {
            // Auto-calculate next interface number if not provided
            if (!isset($data['interface_number'])) {
                $data['interface_number'] = $this->getNextInterfaceNumber($switchId);
            }
            
            // Validate data
            if ($this->bviExists($switchId, $data['interface_number'])) {
                throw new \Exception("BVI interface already exists for this switch");
            }
            if ($this->ipv6Exists($data['ipv6_address'])) {
                throw new \Exception("IPv6 address already exists");
            }

            $stmt = $this->db->prepare("
                INSERT INTO cin_switch_bvi_interfaces 
                (switch_id, interface_number, ipv6_address) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([
                $switchId,
                $data['interface_number'],
                $data['ipv6_address']
            ]);
            
            return $this->getBviInterface($switchId, $this->db->lastInsertId());
        } catch (\PDOException $e) {
            error_log("Error creating BVI interface: " . $e->getMessage());
            throw $e;
        }
    }

    private function getNextInterfaceNumber($switchId)
    {
        $stmt = $this->db->prepare("
            SELECT MAX(interface_number) as max_number 
            FROM cin_switch_bvi_interfaces 
            WHERE switch_id = ?
        ");
        $stmt->execute([$switchId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If no BVI exists, start at 0 (displays as BVI100)
        // Otherwise increment the max by 1
        return ($result['max_number'] !== null) ? $result['max_number'] + 1 : 0;
    }

    public function updateBviInterface($switchId, $bviId, $data)
    {
        try {
            // Check if the interface exists
            $existing = $this->getBviInterface($switchId, $bviId);
            if (!$existing) {
                throw new \Exception("BVI interface not found");
            }

            // Check for duplicates (excluding current record)
            $stmt = $this->db->prepare("
                SELECT COUNT(*) 
                FROM cin_switch_bvi_interfaces 
                WHERE switch_id = ? 
                AND interface_number = ? 
                AND id != ?
            ");
            $stmt->execute([$switchId, $data['interface_number'], $bviId]);
            if ($stmt->fetchColumn() > 0) {
                throw new \Exception("BVI interface number already exists");
            }

            $stmt = $this->db->prepare("
                SELECT COUNT(*) 
                FROM cin_switch_bvi_interfaces 
                WHERE ipv6_address = ? 
                AND id != ?
            ");
            $stmt->execute([$data['ipv6_address'], $bviId]);
            if ($stmt->fetchColumn() > 0) {
                throw new \Exception("IPv6 address already exists");
            }

            // Update the record
            $stmt = $this->db->prepare("
                UPDATE cin_switch_bvi_interfaces 
                SET interface_number = ?, ipv6_address = ? 
                WHERE id = ? AND switch_id = ?
            ");
            $stmt->execute([
                $data['interface_number'],
                $data['ipv6_address'],
                $bviId,
                $switchId
            ]);
            
            return $this->getBviInterface($switchId, $bviId);
        } catch (\PDOException $e) {
            error_log("Error updating BVI interface: " . $e->getMessage());
            throw $e;
        }
    }

    public function deleteBviInterface($switchId, $bviId)
    {
        try {
            // Note: cin_bvi_dhcp_core deletion is handled by the DHCP API in the controller
            // We only delete the BVI interface record here
            $stmt = $this->db->prepare("
                DELETE FROM cin_switch_bvi_interfaces 
                WHERE id = ? AND switch_id = ?
            ");
            return $stmt->execute([$bviId, $switchId]);
        } catch (\PDOException $e) {
            error_log("Error deleting BVI interface: " . $e->getMessage());
            throw $e;
        }
    }

    public function bviExists($switchId, $interfaceNumber, $excludeId = null)
    {
        try {
            $sql = "SELECT COUNT(*) FROM cin_switch_bvi_interfaces 
                    WHERE switch_id = ? AND interface_number = ?";
            $params = [$switchId, $interfaceNumber];
    
            if ($excludeId !== null) {
                $sql .= " AND id != ?";
                $params[] = $excludeId;
            }
    
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            
            return (bool)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error checking BVI interface: " . $e->getMessage());
            throw $e;
        }
    }
    

    public function ipv6Exists($ipv6Address)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) 
                FROM cin_switch_bvi_interfaces 
                WHERE ipv6_address = ?
            ");
            $stmt->execute([$ipv6Address]);
            return $stmt->fetchColumn() > 0;
        } catch (\PDOException $e) {
            error_log("Error checking IPv6 existence: " . $e->getMessage());
            throw $e;
        }
    }
}
