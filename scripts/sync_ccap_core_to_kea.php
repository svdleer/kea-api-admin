#!/usr/bin/env php
<?php
/**
 * Sync CCAP Core options from database to Kea
 * 
 * This script reads CCAP core addresses from cin_bvi_dhcp_core table
 * and updates the corresponding subnets in Kea with the option-data
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database\Database;

echo "=== CCAP Core Sync Script ===\n";
echo "Syncing CCAP core options from database to Kea...\n\n";

try {
    $db = Database::getInstance();
    
    // Get all subnets with CCAP core from database
    $stmt = $db->prepare("
        SELECT 
            kea_subnet_id,
            ccap_core,
            ipv6_address,
            switch_id
        FROM cin_bvi_dhcp_core
        WHERE ccap_core IS NOT NULL
    ");
    $stmt->execute();
    $subnets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($subnets) . " subnets with CCAP core addresses\n\n";
    
    // Get Kea servers
    $stmt = $db->prepare("SELECT id, name, api_url FROM kea_servers WHERE is_active = 1 ORDER BY priority");
    $stmt->execute();
    $keaServers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($keaServers)) {
        throw new Exception("No active Kea servers found");
    }
    
    echo "Found " . count($keaServers) . " active Kea server(s)\n\n";
    
    $updated = 0;
    $failed = 0;
    
    foreach ($subnets as $subnet) {
        $subnetId = $subnet['kea_subnet_id'];
        $ccapCore = $subnet['ccap_core'];
        
        echo "Processing subnet ID {$subnetId} (CCAP: {$ccapCore})...\n";
        
        // Get current subnet config from Kea
        $getCommand = [
            'command' => 'subnet6-get',
            'service' => ['dhcp6'],
            'arguments' => [
                'operation-target' => 'all',
                'subnets' => [['id' => (int)$subnetId]]
            ]
        ];
        
        $ch = curl_init($keaServers[0]['api_url']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($getCommand));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        $result = json_decode($response, true);
        
        if (!isset($result[0]['arguments']['subnets'][0])) {
            echo "  ✗ Subnet not found in Kea\n";
            $failed++;
            continue;
        }
        
        $keaSubnet = $result[0]['arguments']['subnets'][0];
        
        // Preserve existing option-data and add/update CCAP core option
        $existingOptions = $keaSubnet['option-data'] ?? [];
        
        // Remove any existing CCAP core option
        $existingOptions = array_filter($existingOptions, function($opt) {
            return !(($opt['name'] ?? '') === 'ccap-core' || ($opt['code'] ?? 0) == 61);
        });
        
        // Add the new CCAP core option
        $existingOptions[] = [
            'name' => 'ccap-core',
            'code' => 61,
            'space' => 'vendor-4491',
            'csv-format' => true,
            'data' => $ccapCore,
            'always-send' => true
        ];
        
        $keaSubnet['option-data'] = array_values($existingOptions);
        
        echo "  Sending option-data: " . json_encode($keaSubnet['option-data']) . "\n";
        
        // First, set the option using subnet6-delta-add
        $optionCommand = [
            'command' => 'subnet6-delta-add',
            'service' => ['dhcp6'],
            'arguments' => [
                'server-tags' => ['all'],
                'subnets' => [[
                    'id' => (int)$subnetId,
                    'option-data' => [[
                        'name' => 'ccap-core',
                        'code' => 61,
                        'space' => 'vendor-4491',
                        'csv-format' => true,
                        'data' => $ccapCore,
                        'always-send' => true
                    ]]
                ]]
            ]
        ];
        
        // Send option to all Kea servers
        $success = true;
        foreach ($keaServers as $server) {
            $ch = curl_init($server['api_url']);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($optionCommand));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                echo "  ✗ Failed to set option on {$server['name']}: " . substr($response, 0, 100) . "\n";
                $success = false;
            }
        }
        
        if ($success) {
            echo "  ✓ Updated successfully\n";
            $updated++;
        } else {
            $failed++;
        }
    }
    
    echo "\n=== Summary ===\n";
    echo "Updated: {$updated}\n";
    echo "Failed: {$failed}\n";
    
    // Force Kea to reload configuration
    if ($updated > 0) {
        echo "\nReloading Kea configuration...\n";
        foreach ($keaServers as $server) {
            $reloadCmd = [
                'command' => 'config-reload',
                'service' => ['dhcp6']
            ];
            
            $ch = curl_init($server['api_url']);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($reloadCmd));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            
            curl_exec($ch);
            curl_close($ch);
            
            echo "  Reloaded {$server['name']}\n";
        }
    }
    
    echo "\nDone!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
