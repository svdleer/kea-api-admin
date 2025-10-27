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
    private $dhcpModel;
    private $stats = [
        'subnets' => ['imported' => 0, 'skipped' => 0, 'errors' => 0],
        'reservations' => ['imported' => 0, 'skipped' => 0, 'errors' => 0],
        'options' => ['imported' => 0, 'skipped' => 0, 'errors' => 0]
    ];

    public function __construct() {
        $this->db = Database::getInstance();
        // Use the DHCP model which handles Kea API communication
        $this->dhcpModel = new \App\Models\DHCP($this->db);
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

            // Check if subnet already exists in Kea
            $existingSubnets = $this->dhcpModel->getAllSubnetsfromKEA();
            $subnetExists = false;
            foreach ($existingSubnets as $existing) {
                if ($existing['subnet'] === $subnetPrefix) {
                    $subnetExists = true;
                    $this->stats['subnets']['skipped']++;
                    $this->warning("    Subnet already exists in Kea (ID: $subnetId)");
                    break;
                }
            }

            // Extract pool information
            $poolStart = null;
            $poolEnd = null;
            if (isset($subnet['pools']) && !empty($subnet['pools'])) {
                $firstPool = $subnet['pools'][0]['pool'];
                // Parse pool format: "2001:b88:8005:f006::2-2001:b88:8005:f006::fffe"
                if (preg_match('/^(.+?)\s*-\s*(.+?)$/', $firstPool, $matches)) {
                    $poolStart = trim($matches[1]);
                    $poolEnd = trim($matches[2]);
                }
            }

            // Extract relay address
            $relayAddress = null;
            if (isset($subnet['relay']['ip-addresses']) && !empty($subnet['relay']['ip-addresses'])) {
                $relayAddress = $subnet['relay']['ip-addresses'][0];
            }

            // Extract CCAP core address from options
            $ccapCore = null;
            if (isset($subnet['option-data'])) {
                foreach ($subnet['option-data'] as $option) {
                    if (($option['name'] ?? null) === 'ccap-core' || ($option['code'] ?? null) == 61) {
                        $ccapCore = $option['data'];
                        break;
                    }
                }
            }

            // Extract relay (BVI IP) - used for automatic BVI linking
            $relayAddress = null;
            if (isset($subnet['relay']['ip-addresses']) && !empty($subnet['relay']['ip-addresses'])) {
                $relayAddress = $subnet['relay']['ip-addresses'][0];
            }
            
            // Only create subnet in Kea if it doesn't exist
            if (!$subnetExists) {
                $arguments = [
                "remote" => [
                    "type" => "mysql"
                ],
                "server-tags" => ["all"],
                "subnets" => [
                    [
                        "subnet" => $subnetPrefix,
                        "id" => $subnetId,
                        "shared-network-name" => null,
                        "pools" => []
                    ]
                ]
            ];

            // Add pool if available
            if ($poolStart && $poolEnd) {
                $arguments['subnets'][0]['pools'][] = [
                    "pool" => $poolStart . " - " . $poolEnd
                ];
            }

            // Add relay if available
            if ($relayAddress) {
                $arguments['subnets'][0]['relay'] = [
                    "ip-addresses" => [$relayAddress]
                ];
            }

            // Add CCAP core option if available
            if ($ccapCore) {
                $arguments['subnets'][0]['option-data'] = [
                    [
                        "name" => "ccap-core",
                        "code" => 61,
                        "space" => "vendor-4491",
                        "csv-format" => true,
                        "data" => $ccapCore,
                        "always-send" => true
                    ]
                ];
            }

            // Add lifetimes
            if (isset($subnet['valid-lifetime'])) {
                $arguments['subnets'][0]['valid-lifetime'] = $subnet['valid-lifetime'];
            }
            if (isset($subnet['preferred-lifetime'])) {
                $arguments['subnets'][0]['preferred-lifetime'] = $subnet['preferred-lifetime'];
            }

            // Send command to Kea directly via HTTP
            $keaApiUrl = $_ENV['KEA_API_ENDPOINT'];
            $data = [
                "command" => 'remote-subnet6-set',
                "service" => ['dhcp6'],
                "arguments" => $arguments
            ];

            $ch = curl_init($keaApiUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

            $responseJson = curl_exec($ch);
            if (curl_errno($ch)) {
                throw new Exception('Kea API Error: ' . curl_error($ch));
            }
            curl_close($ch);

            $response = json_decode($responseJson, true);

            if (isset($response[0]['result']) && $response[0]['result'] === 0) {
                $this->stats['subnets']['imported']++;
                $this->success("    ✓ Imported subnet to Kea: $subnetPrefix (ID: $subnetId)");
                
                if ($poolStart && $poolEnd) {
                    $this->info("      Pool: $poolStart - $poolEnd");
                }
                if ($relayAddress) {
                    $this->info("      Relay: $relayAddress");
                }
                if ($ccapCore) {
                    $this->info("      CCAP Core: $ccapCore");
                }

                // Import reservations if present
                if (isset($subnet['reservations']) && !empty($subnet['reservations'])) {
                    $this->info("    Found " . count($subnet['reservations']) . " reservations (skipping - manual linking needed)");
                }
            } else {
                throw new Exception("Kea API returned error: " . json_encode($response));
            }
            } // End of if (!$subnetExists)
            
            // Link subnet to BVI interface (works for both new and existing subnets)
            if ($relayAddress) {
                try {
                    // Find BVI interface with matching IP address
                    $stmt = $this->db->prepare(
                        "SELECT id FROM cin_switch_bvi_interfaces WHERE bvi_ipv6 = ?"
                    );
                    $stmt->execute([$relayAddress]);
                    $bviInterface = $stmt->fetch(\PDO::FETCH_ASSOC);
                    
                    if ($bviInterface) {
                        // Check if link already exists
                        $stmt = $this->db->prepare(
                            "SELECT id FROM cin_bvi_dhcp_core WHERE bvi_interface_id = ? AND kea_subnet_id = ?"
                        );
                        $stmt->execute([$bviInterface['id'], $subnetId]);
                        
                        if (!$stmt->fetch()) {
                            // Create link between BVI interface and subnet
                            $stmt = $this->db->prepare(
                                "INSERT INTO cin_bvi_dhcp_core (bvi_interface_id, kea_subnet_id, dhcp_subnet, dhcp_pool_start, dhcp_pool_end) 
                                 VALUES (?, ?, ?, ?, ?)"
                            );
                            $stmt->execute([
                                $bviInterface['id'],
                                $subnetId,
                                $subnetPrefix,
                                $poolStart,
                                $poolEnd
                            ]);
                            $this->success("    ✓ Linked to BVI interface (ID: {$bviInterface['id']})");
                        } else {
                            $this->info("    → Already linked to BVI interface");
                        }
                    } else {
                        $this->warning("    ⚠ No BVI interface found with relay IP: $relayAddress");
                        if (!$subnetExists) {
                            $this->warning("    → Subnet created but not linked. You can link it manually later.");
                        }
                    }
                } catch (Exception $e) {
                    $this->warning("    ⚠ Failed to link BVI: " . $e->getMessage());
                }
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
