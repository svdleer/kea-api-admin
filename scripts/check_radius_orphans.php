#!/usr/bin/env php
<?php
/**
 * Check for orphaned RADIUS entries
 * This script checks both local and remote RADIUS databases for orphaned nas entries
 */

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/vendor/autoload.php';
require_once BASE_PATH . '/src/Database/Database.php';

use App\Database\Database;

// Get database connection
$db = Database::getInstance();

echo "====================================\n";
echo "RADIUS Orphan Check Report\n";
echo "====================================\n\n";

// Check local nas table
echo "1. Checking LOCAL nas table (kea_db)...\n";
echo "-------------------------------------------\n";

$localOrphans = $db->query("
    SELECT n.id, n.nasname, n.bvi_interface_id, n.shortname
    FROM nas n
    LEFT JOIN cin_switch_bvi_interfaces b ON n.bvi_interface_id = b.id
    WHERE n.bvi_interface_id IS NOT NULL 
    AND b.id IS NULL
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($localOrphans)) {
    echo "✅ No orphaned entries in local nas table\n\n";
} else {
    echo "❌ Found " . count($localOrphans) . " orphaned entries:\n";
    foreach ($localOrphans as $orphan) {
        echo "  - ID: {$orphan['id']}, nasname: {$orphan['nasname']}, ";
        echo "bvi_interface_id: {$orphan['bvi_interface_id']}\n";
    }
    echo "\n";
}

// Get all valid BVI interface IDs
$validBviIds = $db->query("SELECT id FROM cin_switch_bvi_interfaces")->fetchAll(PDO::FETCH_COLUMN);
echo "Valid BVI interface IDs: " . implode(', ', $validBviIds) . "\n\n";

// Check remote RADIUS servers
echo "2. Checking REMOTE RADIUS servers...\n";
echo "-------------------------------------------\n";

require_once BASE_PATH . '/src/Models/RadiusServerConfig.php';
$radiusConfigModel = new \App\Models\RadiusServerConfig($db);
$servers = $radiusConfigModel->getServersForSync();

foreach ($servers as $server) {
    echo "\nServer: {$server['name']}\n";
    
    if (!$server['enabled']) {
        echo "  ⏸️  Skipped (disabled)\n";
        continue;
    }
    
    try {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $server['host'],
            $server['port'],
            $server['database'],
            $server['charset']
        );
        
        $remoteDb = new PDO($dsn, $server['username'], $server['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        
        // Get all nas entries
        $stmt = $remoteDb->query("SELECT id, nasname, shortname, bvi_interface_id FROM nas");
        $nasEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "  Total NAS entries: " . count($nasEntries) . "\n";
        
        if (empty($nasEntries)) {
            echo "  ℹ️  No entries found\n";
            continue;
        }
        
        // Check for orphans
        $orphanCount = 0;
        foreach ($nasEntries as $entry) {
            if ($entry['bvi_interface_id'] === null) {
                echo "  ⚠️  ID {$entry['id']}: {$entry['nasname']} - No bvi_interface_id (legacy entry)\n";
                continue;
            }
            
            if (!in_array($entry['bvi_interface_id'], $validBviIds)) {
                echo "  ❌ ID {$entry['id']}: {$entry['nasname']} - ";
                echo "bvi_interface_id {$entry['bvi_interface_id']} doesn't exist!\n";
                $orphanCount++;
            } else {
                echo "  ✅ ID {$entry['id']}: {$entry['nasname']} - ";
                echo "Linked to BVI interface {$entry['bvi_interface_id']}\n";
            }
        }
        
        if ($orphanCount > 0) {
            echo "  \n  ❌ Found {$orphanCount} orphaned entries in {$server['name']}\n";
        }
        
    } catch (PDOException $e) {
        echo "  ❌ Error connecting: " . $e->getMessage() . "\n";
    }
}

echo "\n====================================\n";
echo "Report Complete\n";
echo "====================================\n";
