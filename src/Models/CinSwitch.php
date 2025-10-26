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
            $stmt = $this->db->prepare("
                SELECT * 
                FROM cin_switches 
                WHERE id = ?
            ");
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            error_log("Database error in getSwitchById(): " . $e->getMessage());
            throw new \RuntimeException('Error fetching switch');
        }
    }

    public function createSwitch($data)
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO cin_switches 
                (hostname, created_at) 
                VALUES (?, NOW())
            ");
            $stmt->execute([$data['hostname']]);
            return $this->db->lastInsertId();
        } catch (\PDOException $e) {
            error_log("Database error in createSwitch(): " . $e->getMessage());
            throw new \RuntimeException('Error creating switch');
        }
    }

    public function updateSwitch($id, $data)
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE cin_switches 
                SET hostname = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            return $stmt->execute([$data['hostname'], $id]);
        } catch (\PDOException $e) {
            error_log("Database error in updateSwitch(): " . $e->getMessage());
            throw new \RuntimeException('Error updating switch');
        }
    }

    public function deleteSwitch($id)
    {
        try {
            // Note: BVI and DHCP cleanup is handled by the controller using the DHCP API
            // This method only deletes the switch record itself
            $stmt = $this->db->prepare("
                DELETE FROM cin_switches 
                WHERE id = ?
            ");
            return $stmt->execute([$id]);
        } catch (\PDOException $e) {
            error_log("Database error in deleteSwitch(): " . $e->getMessage());
            throw new \RuntimeException('Error deleting switch: ' . $e->getMessage());
        }
    }

    // BVI Interface Methods
    public function getBviInterfaces($switchId)
    {
        try {
            // First verify the switch exists
            $switchExists = $this->getSwitchById($switchId);
            if (!$switchExists) {
                throw new \RuntimeException("Switch with ID $switchId not found");
            }
    
            // Log the query we're about to execute
            error_log("Fetching BVI interfaces for switch ID: $switchId");
    
            $stmt = $this->db->prepare("
                SELECT 
                    id,
                    switch_id,
                    interface_number,
                    ipv6_address,
                    created_at,
                    updated_at
                FROM cin_switch_bvi_interfaces 
                WHERE switch_id = ? 
                ORDER BY interface_number
            ");
    
            if (!$stmt->execute([$switchId])) {
                $error = $stmt->errorInfo();
                error_log("Database error in getBviInterfaces(): " . print_r($error, true));
                throw new \RuntimeException('Failed to execute BVI interfaces query');
            }
    
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Found " . count($results) . " BVI interfaces for switch ID: $switchId");
            
            return $results;
    
        } catch (\PDOException $e) {
            error_log("PDO error in getBviInterfaces(): " . $e->getMessage());
            throw new \RuntimeException('Database error while fetching BVI interfaces');
        } catch (\Exception $e) {
            error_log("General error in getBviInterfaces(): " . $e->getMessage());
            throw new \RuntimeException('Error fetching BVI interfaces: ' . $e->getMessage());
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
            error_log("Database error in getBviInterface(): " . $e->getMessage());
            throw new \RuntimeException('Error fetching BVI interface');
        }
    }

    public function createBvi($switchId, $data)
    {
        try {
            // Check if IPv6 address is already in use
            if ($this->bviIpv6Exists($data['ipv6_address'])) {
                throw new \RuntimeException('IPv6 address is already in use');
            }

            $stmt = $this->db->prepare("
                INSERT INTO cin_switch_bvi_interfaces 
                (switch_id, interface_number, ipv6_address, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([
                $switchId,
                $data['interface_number'],
                $data['ipv6_address']
            ]);
            return $this->db->lastInsertId();
        } catch (\PDOException $e) {
            error_log("Database error in createBvi(): " . $e->getMessage());
            if ($e->getCode() == 23000) { // MySQL duplicate entry error
                throw new \RuntimeException('IPv6 address is already in use');
            }
            throw new \RuntimeException('Error creating BVI interface');
        }
    }

    public function updateBvi($switchId, $bviId, $data)
    {
        try {
            // Check if IPv6 address is already in use by another interface
            if (isset($data['ipv6_address']) && 
                $this->bviIpv6Exists($data['ipv6_address'], $bviId)) {
                throw new \RuntimeException('IPv6 address is already in use');
            }

            $stmt = $this->db->prepare("
                UPDATE cin_switch_bvi_interfaces 
                SET interface_number = ?,
                    ipv6_address = ?,
                    updated_at = NOW()
                WHERE switch_id = ? AND id = ?
            ");
            return $stmt->execute([
                $data['interface_number'],
                $data['ipv6_address'],
                $switchId,
                $bviId
            ]);
        } catch (\PDOException $e) {
            error_log("Database error in updateBvi(): " . $e->getMessage());
            if ($e->getCode() == 23000) { // MySQL duplicate entry error
                throw new \RuntimeException('IPv6 address is already in use');
            }
            throw new \RuntimeException('Error updating BVI interface');
        }
    }

    public function deleteBvi($switchId, $bviId)
    {
        try {
            $stmt = $this->db->prepare("
                DELETE FROM cin_switch_bvi_interfaces 
                WHERE switch_id = ? AND id = ?
            ");
            return $stmt->execute([$switchId, $bviId]);
        } catch (\PDOException $e) {
            error_log("Database error in deleteBvi(): " . $e->getMessage());
            throw new \RuntimeException('Error deleting BVI interface');
        }
    }

    public function bviIpv6Exists($ipv6Address, $excludeBviId = null)
    {
        try {
            $sql = "
                SELECT COUNT(*) 
                FROM cin_switch_bvi_interfaces 
                WHERE ipv6_address = ?
            ";
            $params = [$ipv6Address];

            if ($excludeBviId !== null) {
                $sql .= " AND id != ?";
                $params[] = $excludeBviId;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchColumn() > 0;
        } catch (\PDOException $e) {
            error_log("Database error in bviIpv6Exists(): " . $e->getMessage());
            throw new \RuntimeException('Error checking IPv6 existence');
        }
    }

    public function getBviCount($switchId)
{
    try {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) 
            FROM cin_switch_bvi_interfaces 
            WHERE switch_id = ?
        ");
        $stmt->execute([$switchId]);
        return (int)$stmt->fetchColumn();
    } catch (\PDOException $e) {
        error_log("Database error in getBviCount(): " . $e->getMessage());
        throw new \RuntimeException('Error counting BVI interfaces');
    }
}

public function getAllSwitches()
{
    try {
        $stmt = $this->db->query("
            SELECT 
                s.*,
                COUNT(bvi.id) as bvi_count
            FROM cin_switches s
            LEFT JOIN cin_switch_bvi_interfaces bvi ON s.id = bvi.switch_id
            GROUP BY s.id
            ORDER BY s.hostname
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (\PDOException $e) {
        error_log("Database error in getAllSwitches(): " . $e->getMessage());
        throw new \RuntimeException('Error fetching switches');
    }
}

public function createBviInterface($switchId, $data)
{
    try {
        $stmt = $this->db->prepare("
            INSERT INTO cin_switch_bvi_interfaces 
            (switch_id, interface_number, ipv6_address) 
            VALUES (?, ?, ?)
        ");
        $result = $stmt->execute([
            $switchId,
            $data['interface_number'],
            $data['ipv6_address']
        ]);

        if ($result) {
            $bviId = $this->db->lastInsertId();
            
            // Auto-create RADIUS client for this BVI interface
            try {
                require_once BASE_PATH . '/src/Models/RadiusClient.php';
                $radiusClient = new \App\Models\RadiusClient($this->db);
                $radiusClient->createFromBvi($bviId, $data['ipv6_address']);
                error_log("RADIUS client auto-created for BVI interface ID: $bviId");
            } catch (\Exception $e) {
                error_log("Failed to auto-create RADIUS client: " . $e->getMessage());
                // Don't fail the BVI creation if RADIUS sync fails
            }
        }

        return $result;
    } catch (\PDOException $e) {
        error_log("Error creating BVI interface: " . $e->getMessage());
        throw $e;
    }
}

public function updateBviInterface($switchId, $bviId, $data)
{
    try {
        $stmt = $this->db->prepare("
            UPDATE cin_switch_bvi_interfaces 
            SET interface_number = ?, 
                ipv6_address = ? 
            WHERE switch_id = ? AND id = ?
        ");
        $result = $stmt->execute([
            $data['interface_number'],
            $data['ipv6_address'],
            $switchId,
            $bviId
        ]);

        if ($result) {
            // Auto-update RADIUS client IP address if it changed
            try {
                require_once BASE_PATH . '/src/Models/RadiusClient.php';
                $radiusClient = new \App\Models\RadiusClient($this->db);
                $radiusClient->updateClientIpByBviId($bviId, $data['ipv6_address']);
                error_log("RADIUS client auto-updated for BVI interface ID: $bviId");
            } catch (\Exception $e) {
                error_log("Failed to auto-update RADIUS client: " . $e->getMessage());
                // Don't fail the BVI update if RADIUS sync fails
            }
        }

        return $result;
    } catch (\PDOException $e) {
        error_log("Error updating BVI interface: " . $e->getMessage());
        throw $e;
    }
}

public function deleteBviInterface($switchId, $bviId)
{
    try {
        $stmt = $this->db->prepare("
            DELETE FROM cin_switch_bvi_interfaces 
            WHERE switch_id = ? AND id = ?
        ");
        return $stmt->execute([$switchId, $bviId]);
    } catch (\PDOException $e) {
        error_log("Error deleting BVI interface: " . $e->getMessage());
        throw $e;
    }
}

public function ipv6AddressExists($ipv6Address, $excludeBviId = null)
{
    try {
        $sql = "SELECT COUNT(*) FROM cin_switch_bvi_interfaces WHERE ipv6_address = ?";
        $params = [$ipv6Address];

        if ($excludeBviId) {
            $sql .= " AND id != ?";
            $params[] = $excludeBviId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() > 0;
    } catch (\PDOException $e) {
        error_log("Error checking IPv6 address: " . $e->getMessage());
        throw $e;
    }
}

public function getNextAvailableBVINumber($switchId) {
    try {
        $query = "SELECT interface_number 
                FROM cin_switch_bvi_interfaces 
                WHERE switch_id = ? 
                ORDER BY interface_number DESC 
                LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([$switchId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result) {
            // Interface numbers are stored as 0, 1, 2, etc.
            // Next one is current + 1
            $nextNumber = intval($result['interface_number']) + 1;
        } else {
            // If no BVI interfaces exist, start with 0 (displays as BVI100)
            $nextNumber = 0;
        }

        return $nextNumber;
    } catch (\PDOException $e) {
        error_log("Error getting next BVI number: " . $e->getMessage());
        return 0; // Default fallback
    }
}


}
