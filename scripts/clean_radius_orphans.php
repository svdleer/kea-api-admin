#!/usr/bin/env php
<?php
/**
 * Clean up orphaned RADIUS entries
 * This script removes nas entries from RADIUS databases that reference deleted BVI interfaces
 */

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/vendor/autoload.php';
require_once BASE_PATH . '/src/Database/Database.php';

use App\Database\Database;

// Get database connection
$db = Database::getInstance();

echo "====================================\n";
echo "RADIUS Orphan Cleanup\n";
echo "====================================\n\n";

// Get all valid BVI interface IDs
$validBviIds = $db->query("SELECT id FROM cin_switch_bvi_interfaces")->fetchAll(PDO::FETCH_COLUMN);
echo "Valid BVI interface IDs: " . implode(', ', $validBviIds) . "\n\n";

// First, clean local nas table
echo "1. Cleaning LOCAL nas table (kea_db)...\n";
echo "-------------------------------------------\n";

$localOrphans = $db->query("
    SELECT n.id, n.nasname, n.bvi_interface_id
    FROM nas n
    LEFT JOIN cin_switch_bvi_interfaces b ON n.bvi_interface_id = b.id
    WHERE n.bvi_interface_id IS NOT NULL 
    AND b.id IS NULL
")->fetchAll(PDO::FETCH_ASSOC);

if (empty($localOrphans)) {
    echo "✅ No orphaned entries to clean\n\n";
} else {
    echo "Found " . count($localOrphans) . " orphaned entries. Deleting...\n";
    foreach ($localOrphans as $orphan) {
        echo "  Deleting ID {$orphan['id']}: {$orphan['nasname']}\n";
        $stmt = $db->prepare("DELETE FROM nas WHERE id = ?");
        $stmt->execute([$orphan['id']]);
    }
    echo "✅ Local cleanup complete\n\n";
}

// Clean remote RADIUS servers
echo "2. Cleaning REMOTE RADIUS servers...\n";
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
        
        // Get orphaned entries
        $stmt = $remoteDb->query("SELECT id, nasname, bvi_interface_id FROM nas WHERE bvi_interface_id IS NOT NULL");
        $nasEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "  Total NAS entries with bvi_interface_id: " . count($nasEntries) . "\n";
        
        if (empty($nasEntries)) {
            echo "  ℹ️  No entries to check\n";
            continue;
        }
        
        // Delete orphans
        $deletedCount = 0;
        foreach ($nasEntries as $entry) {
            if (!in_array($entry['bvi_interface_id'], $validBviIds)) {
                echo "  🗑️  Deleting ID {$entry['id']}: {$entry['nasname']} (bvi_interface_id: {$entry['bvi_interface_id']})\n";
                $deleteStmt = $remoteDb->prepare("DELETE FROM nas WHERE id = ?");
                $deleteStmt->execute([$entry['id']]);
                $deletedCount++;
            }
        }
        
        if ($deletedCount > 0) {
            echo "  ✅ Deleted {$deletedCount} orphaned entries from {$server['name']}\n";
        } else {
            echo "  ✅ No orphaned entries found\n";
        }
        
    } catch (PDOException $e) {
        echo "  ❌ Error: " . $e->getMessage() . "\n";
    }
}

echo "\n====================================\n";
echo "Cleanup Complete\n";
echo "====================================\n";
echo "\nRun check_radius_orphans.php to verify cleanup.\n";
