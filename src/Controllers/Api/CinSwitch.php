<?php

namespace App\Models;

use PDO;

class CinSwitch
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function getSwitchById($id)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM cin_switches WHERE id = ?");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Error getting switch by ID: " . $e->getMessage());
            throw $e;
        }
    }

    public function getBviInterfaces($switchId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM cin_switch_bvi_interfaces 
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

    public function updateSwitch($id, $data)
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE cin_switches 
                SET hostname = ? 
                WHERE id = ?
            ");
            return $stmt->execute([$data['hostname'], $id]);
        } catch (\PDOException $e) {
            error_log("Error updating switch: " . $e->getMessage());
            throw $e;
        }
    }

    public function hostnameExists($hostname, $excludeId = null)
    {
        try {
            $sql = "SELECT COUNT(*) FROM cin_switches WHERE hostname = ?";
            $params = [$hostname];

            if ($excludeId) {
                $sql .= " AND id != ?";
                $params[] = $excludeId;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchColumn() > 0;
        } catch (\PDOException $e) {
            error_log("Error checking hostname: " . $e->getMessage());
            throw $e;
        }
    }

    public function getBviInterface($switchId, $bviId)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT id, interface_number, ipv6_address 
                FROM cin_switch_bvi_interfaces 
                WHERE switch_id = ? AND id = ?
            ");
            $stmt->execute([$switchId, $bviId]);
            return $stmt->fetch(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Error getting BVI interface: " . $e->getMessage());
            throw $e;
        }
    }

    public function interfaceNumberExists($switchId, $interfaceNumber)
    {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) 
                FROM cin_switch_bvi_interfaces 
                WHERE switch_id = ? AND interface_number = ?
            ");
            $stmt->execute([$switchId, $interfaceNumber]);
            return $stmt->fetchColumn() > 0;
        } catch (\PDOException $e) {
            error_log("Error checking interface number: " . $e->getMessage());
            throw $e;
        }
    }

    
}
