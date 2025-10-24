<?php
/**
 * Fix existing DHCP subnet to point to correct BVI interface
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database\Database;

try {
    $db = Database::getInstance();
    
    // Get all subnets from cin_bvi_dhcp_core
    $stmt = $db->query("SELECT * FROM cin_bvi_dhcp_core");
    $dhcpSubnets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($dhcpSubnets) . " DHCP subnet(s) in cin_bvi_dhcp_core:\n";
    foreach ($dhcpSubnets as $subnet) {
        echo "  ID: {$subnet['id']}, Switch: {$subnet['switch_id']}, Interface: {$subnet['interface_number']}, IPv6: {$subnet['ipv6_address']}, Kea Subnet ID: {$subnet['kea_subnet_id']}\n";
    }
    
    echo "\n";
    
    // Get all BVI interfaces
    $stmt = $db->query("SELECT * FROM cin_switch_bvi_interfaces");
    $bviInterfaces = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($bviInterfaces) . " BVI interface(s):\n";
    foreach ($bviInterfaces as $bvi) {
        echo "  ID: {$bvi['id']}, Switch: {$bvi['switch_id']}, Interface: {$bvi['interface_number']}, IPv6: {$bvi['ipv6_address']}\n";
    }
    
    echo "\n";
    
    // Try to match and fix
    foreach ($dhcpSubnets as $subnet) {
        foreach ($bviInterfaces as $bvi) {
            // Match by switch_id, interface_number, and ipv6_address
            if ($subnet['switch_id'] == $bvi['switch_id'] && 
                $subnet['interface_number'] == $bvi['interface_number'] &&
                $subnet['ipv6_address'] == $bvi['ipv6_address']) {
                
                if ($subnet['id'] != $bvi['id']) {
                    echo "Found mismatch! DHCP subnet ID {$subnet['id']} should be {$bvi['id']}\n";
                    echo "Fixing... ";
                    
                    // Update the cin_bvi_dhcp_core table
                    $updateStmt = $db->prepare("UPDATE cin_bvi_dhcp_core SET id = ? WHERE kea_subnet_id = ?");
                    $updateStmt->execute([$bvi['id'], $subnet['kea_subnet_id']]);
                    
                    echo "DONE!\n";
                } else {
                    echo "DHCP subnet ID {$subnet['id']} is already correct.\n";
                }
            }
        }
    }
    
    echo "\nAll done!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
