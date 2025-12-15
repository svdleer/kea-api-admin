#!/usr/bin/env php
<?php
/**
 * Import DHCPv6 reservations from kea-dhcp6.conf into Kea reservation database
 * Reads the JSON config file and adds reservations using the reservation-add API
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database\Database;

// Get command line arguments
$configFile = $argv[1] ?? null;

if (!$configFile) {
    echo "Usage: php import_kea_reservations.php <path-to-kea-dhcp6.conf>\n";
    echo "Example: php import_kea_reservations.php /etc/kea/kea-dhcp6.conf\n";
    exit(1);
}

if (!file_exists($configFile)) {
    echo "Error: Config file not found: {$configFile}\n";
    exit(1);
}

echo "Reading Kea config from: {$configFile}\n\n";

// Read and parse the config file
$configContent = file_get_contents($configFile);

// Clean JSON - remove comments and fix common issues
echo "Cleaning JSON syntax...\n";
// Remove C-style comments /* ... */
$configContent = preg_replace('/\/\*.*?\*\//s', '', $configContent);
// Remove # comments
$configContent = preg_replace('/^\s*#.*$/m', '', $configContent);
// Remove // comments
$configContent = preg_replace('/^\s*\/\/.*$/m', '', $configContent);
// Remove inline # comments
$configContent = preg_replace('/#.*$/m', '', $configContent);
// Remove trailing commas before closing brackets
$configContent = preg_replace('/,(\s*[}\]])/', '$1', $configContent);
// Remove any "Lines X-Y omitted" text
$configContent = preg_replace('/.*Lines \d+-\d+ omitted.*\n?/', '', $configContent);

$config = json_decode($configContent, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "Error: Invalid JSON in config file: " . json_last_error_msg() . "\n";
    echo "\nTip: Make sure the file is valid JSON. First 500 chars after cleaning:\n";
    echo substr($configContent, 0, 500) . "\n";
    exit(1);
}

// Get database connection
$db = Database::getInstance();

// Get all active Kea servers
$stmt = $db->prepare("SELECT id, name, api_url FROM kea_servers WHERE is_active = 1 ORDER BY priority");
$stmt->execute();
$keaServers = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($keaServers)) {
    echo "Error: No active Kea servers configured.\n";
    exit(1);
}

echo "Found " . count($keaServers) . " active Kea server(s)\n";

// Extract subnets with reservations
$subnets = $config['Dhcp6']['subnet6'] ?? [];
$totalReservations = 0;
$successCount = 0;
$errorCount = 0;
$skippedCount = 0;

foreach ($subnets as $subnet) {
    $subnetId = $subnet['id'];
    $subnetPrefix = $subnet['subnet'];
    $reservations = $subnet['reservations'] ?? [];
    
    if (empty($reservations)) {
        continue;
    }
    
    echo "\n=== Subnet {$subnetId} ({$subnetPrefix}) ===\n";
    echo "Found " . count($reservations) . " reservation(s)\n";
    
    foreach ($reservations as $reservation) {
        $totalReservations++;
        
        // Extract reservation details
        $duid = null;
        $hwAddress = null;
        
        // Check for DUID
        if (isset($reservation['duid'])) {
            $duid = $reservation['duid'];
        }
        
        // Check for hardware address
        if (isset($reservation['hw-address'])) {
            $hwAddress = $reservation['hw-address'];
        }
        
        // Get IP addresses
        $ipAddresses = $reservation['ip-addresses'] ?? [];
        
        if (empty($ipAddresses)) {
            echo "  ⚠️  Skipping reservation with no IP addresses\n";
            $skippedCount++;
            continue;
        }
        
        foreach ($ipAddresses as $ipAddress) {
            // Prepare reservation data for Kea API
            $reservationData = [
                'subnet-id' => $subnetId,
                'ip-addresses' => [$ipAddress]
            ];
            
            if ($duid) {
                $reservationData['duid'] = $duid;
            }
            
            if ($hwAddress) {
                $reservationData['hw-address'] = $hwAddress;
            }
            
            // Add hostname if present
            if (isset($reservation['hostname'])) {
                $reservationData['hostname'] = $reservation['hostname'];
            }
            
            // Add option-data if present
            if (isset($reservation['option-data']) && !empty($reservation['option-data'])) {
                $reservationData['option-data'] = $reservation['option-data'];
            }
            
            echo "  → Adding reservation: {$ipAddress}";
            if ($duid) echo " (DUID: {$duid})";
            if ($hwAddress) echo " (MAC: {$hwAddress})";
            echo "\n";
            
            // Prepare API request
            $apiData = [
                'command' => 'reservation-add',
                'service' => ['dhcp6'],
                'arguments' => [
                    'reservation' => $reservationData
                ]
            ];
            
            // Send to all Kea servers
            foreach ($keaServers as $keaServer) {
                echo "    → {$keaServer['name']}: ";
                
                $ch = curl_init($keaServer['api_url']);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($apiData));
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
                    echo "✅\n";
                    $successCount++;
                } elseif ($httpCode === 200 && isset($result[0]['result']) && $result[0]['result'] === 1) {
                    $errorMsg = $result[0]['text'] ?? 'Unknown error';
                    // Check if it's a duplicate
                    if (strpos($errorMsg, 'already exists') !== false || strpos($errorMsg, 'duplicate') !== false) {
                        echo "⚠️  Already exists\n";
                        $skippedCount++;
                    } else {
                        echo "❌ {$errorMsg}\n";
                        $errorCount++;
                    }
                } else {
                    $errorMsg = $result[0]['text'] ?? 'Unknown error';
                    echo "❌ {$errorMsg}\n";
                    $errorCount++;
                }
            }
        }
    }
}

echo "\n\n=== Summary ===\n";
echo "Total reservations found: {$totalReservations}\n";
echo "Successfully added: " . ($successCount / count($keaServers)) . "\n";
echo "Already existed: " . ($skippedCount / count($keaServers)) . "\n";
echo "Errors: " . ($errorCount / count($keaServers)) . "\n";

if ($errorCount > 0) {
    echo "\n⚠️  Some reservations failed to import. Check the errors above.\n";
    exit(1);
}

echo "\n✅ Reservation import completed!\n";
exit(0);
