#!/usr/bin/env php
<?php
/**
 * Fix RADIUS NAS shortnames to proper format
 * Converts old format like "BVI-2001:b88:8005:f013" 
 * to proper format like "mnd-gt0002-ar155-bvi100"
 */

// Set BASE_PATH if not defined
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

require_once BASE_PATH . '/vendor/autoload.php';

use App\Database\Database;

echo "=== RADIUS Shortname Fixer ===\n\n";

$db = Database::getInstance();

try {
    // Get all NAS entries with BVI interface links
    $stmt = $db->query("
        SELECT 
            n.id as nas_id,
            n.nasname,
            n.shortname as old_shortname,
            n.bvi_interface_id,
            b.interface_number,
            s.hostname
        FROM nas n
        JOIN cin_bvi_dhcp_core b ON n.bvi_interface_id = b.id
        JOIN cin_switches s ON b.switch_id = s.id
        WHERE n.bvi_interface_id IS NOT NULL
        ORDER BY s.hostname, b.interface_number
    ");
    
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($entries)) {
        echo "No NAS entries found with BVI interface links.\n";
        exit(0);
    }
    
    echo "Found " . count($entries) . " NAS entries to check/fix:\n\n";
    
    $fixed = 0;
    $skipped = 0;
    
    foreach ($entries as $entry) {
        $displayBvi = $entry['interface_number'] + 100;
        $correctShortname = strtolower($entry['hostname']) . '-bvi' . $displayBvi;
        
        if ($entry['old_shortname'] === $correctShortname) {
            echo "✓ NAS ID {$entry['nas_id']}: {$entry['old_shortname']} - Already correct\n";
            $skipped++;
            continue;
        }
        
        echo "⚠ NAS ID {$entry['nas_id']}:\n";
        echo "  Old: {$entry['old_shortname']}\n";
        echo "  New: $correctShortname\n";
        
        // Update shortname
        $updateStmt = $db->prepare("UPDATE nas SET shortname = ? WHERE id = ?");
        $updateStmt->execute([$correctShortname, $entry['nas_id']]);
        
        echo "  ✓ Updated!\n\n";
        $fixed++;
    }
    
    echo "\n=== Summary ===\n";
    echo "Fixed: $fixed\n";
    echo "Already correct: $skipped\n";
    echo "Total: " . count($entries) . "\n\n";
    
    if ($fixed > 0) {
        echo "⚠️  Important: Sync these changes to RADIUS servers:\n";
        echo "   Go to Admin Tools > RADIUS Management > Sync All Clients\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
