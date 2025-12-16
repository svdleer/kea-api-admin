#!/usr/bin/env php
<?php
/**
 * Sync CCAP Core options from database to Kea
 * This script reads CCAP core addresses from cin_bvi_dhcp_core table
 * and sets them in Kea using the option6-subnet-set command
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database\Database;

$db = Database::getInstance();

// Get all active Kea servers
$stmt = $db->prepare("SELECT id, name, api_url FROM kea_servers WHERE is_active = 1 ORDER BY priority");
$stmt->execute();
$keaServers = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($keaServers)) {
    echo "Error: No active Kea servers configured.\n";
    exit(1);
}

echo "Found " . count($keaServers) . " active Kea server(s)\n\n";

// Get all subnets with CCAP core addresses
$stmt = $db->prepare("
    SELECT kea_subnet_id, ccap_core 
    FROM cin_bvi_dhcp_core 
    WHERE ccap_core IS NOT NULL AND ccap_core != ''
    ORDER BY kea_subnet_id
");
$stmt->execute();
$subnets = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Found " . count($subnets) . " subnet(s) with CCAP core addresses\n\n";

$successCount = 0;
$errorCount = 0;

foreach ($subnets as $subnet) {
    $subnetId = $subnet['kea_subnet_id'];
    $ccapCore = $subnet['ccap_core'];
    
    echo "Setting CCAP core for subnet $subnetId to $ccapCore\n";
    
    // Prepare the Kea API request using subnet6-delta-add
    $data = [
        "command" => "subnet6-delta-add",
        "service" => ["dhcp6"],
        "arguments" => [
            "subnets" => [[
                "id" => (int)$subnetId,
                "option-data" => [[
                    "name" => "ccap-core",
                    "code" => 61,
                    "space" => "vendor-4491",
                    "csv-format" => true,
                    "data" => $ccapCore,
                    "always-send" => true
                ]]
            ]]
        ]
    ];
    
    // Send to all Kea servers
    foreach ($keaServers as $keaServer) {
        echo "  → Sending to {$keaServer['name']} ({$keaServer['api_url']})... ";
        
        $ch = curl_init($keaServer['api_url']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            echo "❌ CURL Error: " . curl_error($ch) . "\n";
            $errorCount++;
            curl_close($ch);
            continue;
        }
        
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if ($httpCode === 200 && isset($result[0]['result']) && $result[0]['result'] === 0) {
            echo "✅ Success\n";
            $successCount++;
        } else {
            $errorMsg = $result[0]['text'] ?? 'Unknown error';
            echo "❌ Failed: $errorMsg\n";
            $errorCount++;
        }
    }
    
    echo "\n";
}

echo "\n=== Summary ===\n";
echo "Total subnets processed: " . count($subnets) . "\n";
echo "Successful updates: $successCount\n";
echo "Failed updates: $errorCount\n";

if ($errorCount > 0) {
    echo "\n⚠️  Some updates failed. Check the errors above.\n";
    echo "If you see 'Thread stack overrun' errors, you need to increase MySQL's thread_stack setting.\n";
    echo "Add this to your MySQL config: thread_stack=256K\n";
    exit(1);
}

echo "\n✅ All CCAP core options synced successfully!\n";
exit(0);
