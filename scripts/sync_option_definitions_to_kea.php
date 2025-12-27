#!/usr/bin/env php
<?php
/**
 * Sync global option definitions to all Kea servers
 * This ensures all servers have the same option definitions like ccap-core
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

// Define the option definitions to sync
$optionDefinitions = [
    [
        "name" => "time-offset",
        "code" => 38,
        "space" => "vendor-4491",
        "type" => "uint32",
        "array" => true
    ],
    [
        "name" => "syslog-servers",
        "code" => 34,
        "space" => "vendor-4491",
        "type" => "ipv6-address",
        "array" => true
    ],
    [
        "name" => "rfc868-servers",
        "code" => 37,
        "space" => "vendor-4491",
        "type" => "ipv6-address",
        "array" => true
    ],
    [
        "name" => "ccap-core",
        "code" => 61,
        "space" => "vendor-4491",
        "type" => "ipv6-address",
        "array" => true
    ]
];

echo "Syncing " . count($optionDefinitions) . " option definition(s)\n\n";

$successCount = 0;
$errorCount = 0;

foreach ($optionDefinitions as $optionDef) {
    echo "Setting option definition: {$optionDef['space']}.{$optionDef['name']} (code {$optionDef['code']})\n";
    
    // Prepare the Kea API request using option-def6-set
    $data = [
        "command" => "option-def6-set",
        "service" => ["dhcp6"],
        "arguments" => [
            "option-defs" => [$optionDef],
            "operation-target" => "all",
            "server-tags" => ["all"]
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
echo "Total option definitions: " . count($optionDefinitions) . "\n";
echo "Total servers: " . count($keaServers) . "\n";
echo "Successful updates: $successCount\n";
echo "Failed updates: $errorCount\n";

if ($errorCount > 0) {
    echo "\n⚠️  Some updates failed. Check the errors above.\n";
    exit(1);
}

echo "\n✅ All option definitions synced successfully!\n";
exit(0);
