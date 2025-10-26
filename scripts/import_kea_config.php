#!/usr/bin/env php
<?php
/**
 * KEA DHCPv6 Configuration Import Script
 * 
 * This script imports DHCPv6 configuration from kea-dhcp6.conf into the database:
 * - Subnets with pools and options
 * - Host reservations (static leases)
 * - Option definitions
 * - Global options
 * 
 * Usage:
 *   php scripts/import_kea_config.php [config-file]
 * 
 * Example:
 *   php scripts/import_kea_config.php /etc/kea/kea-dhcp6.conf
 */

// Set up paths
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/vendor/autoload.php';

use App\Database\Database;

// Colors for output
class Colors {
    const RESET = "\033[0m";
    const RED = "\033[31m";
    const GREEN = "\033[32m";
    const YELLOW = "\033[33m";
    const BLUE = "\033[34m";
    const CYAN = "\033[36m";
    const BOLD = "\033[1m";
}

class KeaConfigImporter {
    private $db;
    private $apiBaseUrl;
    private $apiKey;
    private $stats = [
        'subnets' => ['imported' => 0, 'skipped' => 0, 'errors' => 0],
        'reservations' => ['imported' => 0, 'skipped' => 0, 'errors' => 0],
        'options' => ['imported' => 0, 'skipped' => 0, 'errors' => 0]
    ];

    public function __construct() {
        $this->db = Database::getInstance();
        
        // Get API configuration
        $this->apiBaseUrl = $_ENV['API_BASE_URL'] ?? 'http://localhost';
        
        // Get admin API key from database
        $stmt = $this->db->prepare("SELECT api_key FROM api_keys WHERE is_admin = 1 LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$result) {
            throw new Exception("No admin API key found in database. Please create an admin API key first.");
        }
        
        $this->apiKey = $result['api_key'];
    }
    
    /**
     * Make API request
     */
    private function apiRequest($method, $endpoint, $data = null) {
        $url = rtrim($this->apiBaseUrl, '/') . $endpoint;
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-API-Key: ' . $this->apiKey,
            'Content-Type: application/json'
        ]);
        
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            return json_decode($response, true);
        } else {
            throw new Exception("API request failed: HTTP $httpCode - $response");
        }
    }

    /**
     * Main import function
     */
    public function import($configFile) {
        $this->printHeader();

        if (!file_exists($configFile)) {
            $this->error("Configuration file not found: $configFile");
            return false;
        }

        $this->info("Reading configuration from: $configFile");

        // Read and parse JSON config
        $configJson = file_get_contents($configFile);
        
        // Remove C-style block comments /* ... */
        $configJson = preg_replace('/\/\*.*?\*\//s', '', $configJson);
        
        // Remove line comments (lines starting with # or //)
        $configJson = preg_replace('/^\s*#.*$/m', '', $configJson);
        $configJson = preg_replace('/^\s*\/\/.*$/m', '', $configJson);
        
        // Remove inline # comments
        $configJson = preg_replace('/#.*$/m', '', $configJson);
        
        // Remove trailing commas before closing brackets/braces
        $configJson = preg_replace('/,(\s*[}\]])/', '$1', $configJson);
        
        // Remove lines with "omitted" placeholders
        $configJson = preg_replace('/.*Lines \d+-\d+ omitted.*\n?/', '', $configJson);
        
        $config = json_decode($configJson, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error("Failed to parse configuration file: " . json_last_error_msg());
            $this->warning("Note: Kea config files may contain comments or syntax errors.");
            
            // Show a snippet of the problematic area
            $lines = explode("\n", $configJson);
            $this->warning("\nShowing first 20 non-empty lines after cleanup:");
            $count = 0;
            foreach ($lines as $i => $line) {
                $trimmed = trim($line);
                if (!empty($trimmed)) {
                    $this->info(sprintf("Line %d: %s", $i + 1, substr($line, 0, 100)));
                    $count++;
                    if ($count >= 20) break;
                }
            }
            
            return false;
        }

        if (!isset($config['Dhcp6'])) {
            $this->error("Invalid configuration: 'Dhcp6' section not found");
            return false;
        }

        $dhcp6Config = $config['Dhcp6'];

        // Debug: Show what we found
        $this->info("\nConfiguration structure detected:");
        $this->info("  - Option definitions: " . (isset($dhcp6Config['option-def']) ? count($dhcp6Config['option-def']) : 0));
        $this->info("  - Global options: " . (isset($dhcp6Config['option-data']) ? count($dhcp6Config['option-data']) : 0));
        $this->info("  - Subnets: " . (isset($dhcp6Config['subnet6']) ? count($dhcp6Config['subnet6']) : 0));
        
        // Debug: Show keys in config
        $this->info("\nTop-level Dhcp6 keys found: " . implode(', ', array_keys($dhcp6Config)));

        // For now, only import subnets (options and reservations can be added later)
        $this->info("\n" . Colors::YELLOW . "Note: Currently importing subnets only. Options and reservations will be added in future updates." . Colors::RESET);

        // Import subnets
        if (isset($dhcp6Config['subnet6'])) {
            $this->info("\n" . Colors::CYAN . "Importing Subnets..." . Colors::RESET);
            $this->info("Found " . count($dhcp6Config['subnet6']) . " subnets in configuration");
            foreach ($dhcp6Config['subnet6'] as $subnet) {
                $this->importSubnet($subnet);
            }
        } else {
            $this->warning("\n" . Colors::YELLOW . "No subnets found in configuration" . Colors::RESET);
        }

        // Import host reservations from reservations file if configured
        if (isset($dhcp6Config['hosts-database'])) {
            $this->info("\n" . Colors::CYAN . "Host reservations database configured" . Colors::RESET);
            $this->info("Reservations will be read from Kea's database");
        }

        $this->printSummary();
        return true;
    }

    /**
     * Import option definitions
     */
    private function importOptionDefinitions($optionDefs) {
        foreach ($optionDefs as $optionDef) {
            try {
                $code = $optionDef['code'];
                $name = $optionDef['name'];
                $type = $optionDef['type'];
                $space = $optionDef['space'] ?? 'dhcp6';

                // Check if already exists
                $stmt = $this->db->prepare("SELECT code FROM dhcp6_option_def WHERE code = ? AND space = ?");
                $stmt->execute([$code, $space]);
                
                if ($stmt->fetch()) {
                    $this->stats['options']['skipped']++;
                    $this->warning("  Option definition $code ($name) already exists, skipping");
                    continue;
                }

                // Insert option definition
                $stmt = $this->db->prepare(
                    "INSERT INTO dhcp6_option_def (code, name, type, space, array, record_types, encapsulate) 
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );

                $stmt->execute([
                    $code,
                    $name,
                    $type,
                    $space,
                    $optionDef['array'] ?? false,
                    isset($optionDef['record-types']) ? implode(',', $optionDef['record-types']) : null,
                    $optionDef['encapsulate'] ?? null
                ]);

                $this->stats['options']['imported']++;
                $this->success("  ✓ Imported option definition: $name (code $code)");

            } catch (Exception $e) {
                $this->stats['options']['errors']++;
                $this->error("  ✗ Failed to import option definition: " . $e->getMessage());
            }
        }
    }

    /**
     * Import options (global or per subnet)
     */
    private function importOptions($options, $subnetId = null) {
        foreach ($options as $option) {
            try {
                $code = $option['code'];
                $data = $option['data'];
                $name = $option['name'] ?? "option-$code";

                // Check if already exists
                $query = $subnetId 
                    ? "SELECT code FROM dhcp6_options WHERE code = ? AND subnet_id = ?"
                    : "SELECT code FROM dhcp6_options WHERE code = ? AND subnet_id IS NULL";
                
                $stmt = $this->db->prepare($query);
                $params = $subnetId ? [$code, $subnetId] : [$code];
                $stmt->execute($params);
                
                if ($stmt->fetch()) {
                    $this->stats['options']['skipped']++;
                    continue;
                }

                // Insert option
                $stmt = $this->db->prepare(
                    "INSERT INTO dhcp6_options (code, name, data, space, subnet_id) 
                     VALUES (?, ?, ?, ?, ?)"
                );

                $stmt->execute([
                    $code,
                    $name,
                    $data,
                    $option['space'] ?? 'dhcp6',
                    $subnetId
                ]);

                $this->stats['options']['imported']++;

            } catch (Exception $e) {
                $this->stats['options']['errors']++;
                $this->error("  ✗ Failed to import option: " . $e->getMessage());
            }
        }
    }

    /**
     * Import subnet with pools and reservations
     */
    private function importSubnet($subnet) {
        try {
            $subnetPrefix = $subnet['subnet'];
            $subnetId = $subnet['id'];

            $this->info("\n  Processing subnet: $subnetPrefix (ID: $subnetId)");

            // Check if subnet already exists via API
            try {
                $existing = $this->apiRequest('GET', '/api/dhcp/subnets');
                if ($existing && isset($existing['subnets'])) {
                    foreach ($existing['subnets'] as $existingSubnet) {
                        if ($existingSubnet['subnet'] === $subnetPrefix) {
                            $this->stats['subnets']['skipped']++;
                            $this->warning("    Subnet already exists, skipping");
                            return;
                        }
                    }
                }
            } catch (Exception $e) {
                // Continue if check fails
            }

            // Extract pools
            $pools = [];
            if (isset($subnet['pools'])) {
                foreach ($subnet['pools'] as $pool) {
                    $pools[] = $pool['pool'];
                }
            }

            // Prepare subnet data for API
            $subnetData = [
                'subnet' => $subnetPrefix,
                'pools' => $pools,
                'valid_lifetime' => $subnet['valid-lifetime'] ?? 7200,
                'preferred_lifetime' => $subnet['preferred-lifetime'] ?? 3600,
                'rapid_commit' => $subnet['rapid-commit'] ?? false,
                'description' => 'Imported from Kea config on ' . date('Y-m-d H:i:s')
            ];

            // Create subnet via API
            $result = $this->apiRequest('POST', '/api/dhcp/subnets', $subnetData);

            if ($result && isset($result['success']) && $result['success']) {
                $this->stats['subnets']['imported']++;
                $this->success("    ✓ Imported subnet: $subnetPrefix");
                
                if (!empty($pools)) {
                    $this->info("      Pools: " . implode(', ', $pools));
                }

                // Import reservations if present in subnet
                if (isset($subnet['reservations']) && !empty($subnet['reservations'])) {
                    $this->info("    Found " . count($subnet['reservations']) . " reservations (skipping - not yet implemented via API)");
                    // TODO: Implement reservation import via API
                    // foreach ($subnet['reservations'] as $reservation) {
                    //     $this->importReservation($reservation, $subnetId, $subnetPrefix);
                    // }
                }
            } else {
                throw new Exception("API returned error: " . json_encode($result));
            }

        } catch (Exception $e) {
            $this->stats['subnets']['errors']++;
            $this->error("    ✗ Failed to import subnet: " . $e->getMessage());
        }
    }

    /**
     * Import host reservation
     */
    private function importReservation($reservation, $subnetId, $subnetPrefix) {
        try {
            $duid = $reservation['duid'] ?? $reservation['hw-address'] ?? null;
            $ipAddresses = $reservation['ip-addresses'] ?? [];
            $hostname = $reservation['hostname'] ?? null;

            if (!$duid || empty($ipAddresses)) {
                $this->warning("      Skipping invalid reservation (missing DUID or IP)");
                return;
            }

            foreach ($ipAddresses as $ipAddress) {
                // Check if reservation already exists
                $stmt = $this->db->prepare(
                    "SELECT dhcp6_iaid FROM hosts WHERE dhcp6_subnet_id = ? AND ipv6_address = ?"
                );
                $stmt->execute([$subnetId, inet_pton($ipAddress)]);
                
                if ($stmt->fetch()) {
                    $this->stats['reservations']['skipped']++;
                    continue;
                }

                // Insert reservation (using Kea's hosts table structure)
                $stmt = $this->db->prepare(
                    "INSERT INTO hosts 
                    (dhcp_identifier, dhcp_identifier_type, dhcp6_subnet_id, ipv6_address, hostname) 
                    VALUES (?, 0, ?, ?, ?)"
                );

                $stmt->execute([
                    hex2bin(str_replace(':', '', $duid)),
                    $subnetId,
                    inet_pton($ipAddress),
                    $hostname
                ]);

                $this->stats['reservations']['imported']++;
                $this->success("      ✓ Imported reservation: $ipAddress ($hostname)");
            }

        } catch (Exception $e) {
            $this->stats['reservations']['errors']++;
            $this->error("      ✗ Failed to import reservation: " . $e->getMessage());
        }
    }

    /**
     * Print header
     */
    private function printHeader() {
        echo Colors::BOLD . Colors::CYAN;
        echo "\n";
        echo "╔═══════════════════════════════════════════════════════════╗\n";
        echo "║      KEA DHCPv6 Configuration Import Script               ║\n";
        echo "║      Import subnets, pools, and reservations              ║\n";
        echo "╚═══════════════════════════════════════════════════════════╝\n";
        echo Colors::RESET . "\n";
    }

    /**
     * Print summary
     */
    private function printSummary() {
        echo "\n" . Colors::BOLD . Colors::CYAN;
        echo "╔═══════════════════════════════════════════════════════════╗\n";
        echo "║                    Import Summary                         ║\n";
        echo "╚═══════════════════════════════════════════════════════════╝\n";
        echo Colors::RESET;

        $this->printStatLine("Subnets", $this->stats['subnets']);
        $this->printStatLine("Reservations", $this->stats['reservations']);
        $this->printStatLine("Options", $this->stats['options']);

        $totalImported = $this->stats['subnets']['imported'] + 
                        $this->stats['reservations']['imported'] + 
                        $this->stats['options']['imported'];
        
        $totalErrors = $this->stats['subnets']['errors'] + 
                      $this->stats['reservations']['errors'] + 
                      $this->stats['options']['errors'];

        echo "\n" . Colors::BOLD;
        echo "Total Imported: " . Colors::GREEN . $totalImported . Colors::RESET . "\n";
        
        if ($totalErrors > 0) {
            echo Colors::BOLD . "Total Errors: " . Colors::RED . $totalErrors . Colors::RESET . "\n";
        }
        
        echo "\n";
    }

    /**
     * Print statistics line
     */
    private function printStatLine($label, $stats) {
        echo Colors::BOLD . str_pad($label . ":", 20) . Colors::RESET;
        echo Colors::GREEN . $stats['imported'] . " imported" . Colors::RESET . ", ";
        echo Colors::YELLOW . $stats['skipped'] . " skipped" . Colors::RESET;
        
        if ($stats['errors'] > 0) {
            echo ", " . Colors::RED . $stats['errors'] . " errors" . Colors::RESET;
        }
        
        echo "\n";
    }

    /**
     * Output functions
     */
    private function success($message) {
        echo Colors::GREEN . $message . Colors::RESET . "\n";
    }

    private function info($message) {
        echo Colors::BLUE . $message . Colors::RESET . "\n";
    }

    private function warning($message) {
        echo Colors::YELLOW . $message . Colors::RESET . "\n";
    }

    private function error($message) {
        echo Colors::RED . $message . Colors::RESET . "\n";
    }
}

// Main execution
if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

// Get config file from arguments
$configFile = $argv[1] ?? '/etc/kea/kea-dhcp6.conf';

$importer = new KeaConfigImporter();
$success = $importer->import($configFile);

exit($success ? 0 : 1);
