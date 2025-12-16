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

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->load();

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
        try {
            $this->db = Database::getInstance();
            // Use the DHCP model which handles Kea API communication
            $this->dhcpModel = new \App\Models\DHCP($this->db);
        } catch (\Exception $e) {
            die("Failed to initialize database connection: " . $e->getMessage() . "\n");
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

        // STEP 1: Pre-process subnets to extract and create BVI interfaces
        if (isset($dhcp6Config['subnet6'])) {
            $this->info("\n" . Colors::CYAN . "Step 1: Creating BVI interfaces from relay IPs..." . Colors::RESET);
            $this->createBviInterfacesFromConfig($dhcp6Config['subnet6'], $configJson);
        }

        // STEP 2: Import subnets (now BVIs exist for linking)
        if (isset($dhcp6Config['subnet6'])) {
            $this->info("\n" . Colors::CYAN . "Step 2: Importing Subnets..." . Colors::RESET);
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
     * Create BVI interfaces from relay IPs in subnets
     * Parses comments to extract switch names
     */
    private function createBviInterfacesFromConfig($subnets, $configJson) {
        $lines = explode("\n", $configJson);
        $bviCreated = 0;
        $bviSkipped = 0;
        $bviErrors = 0;
        
        foreach ($subnets as $subnet) {
            if (!isset($subnet['relay']['ip-addresses'][0])) {
                continue; // No relay IP, skip
            }
            
            $relayIp = $subnet['relay']['ip-addresses'][0];
            $subnetPrefix = $subnet['subnet'];
            
            // Try to find comment above this subnet to extract switch name and BVI number
            $switchName = null;
            $bviNumber = null;
            
            // Search for comment like: ##### SWITCHNAME-CCAP... - SWITCHNAME-AR### #####
            foreach ($lines as $lineNum => $line) {
                if (strpos($line, '"subnet": "' . $subnetPrefix . '"') !== false) {
                    // Found the subnet, look backwards for comment
                    for ($i = $lineNum - 1; $i >= 0 && $i >= $lineNum - 15; $i--) {
                        $commentLine = trim($lines[$i]);
                        if (preg_match('/#####+\s*([A-Z0-9\-]+)-[A-Z]+\d+\s*-\s*([A-Z0-9\-]+)-AR(\d+)/', $commentLine, $matches)) {
                            $switchName = $matches[1];
                            $bviNumber = (int)$matches[3];
                            $this->info("  → Parsed comment: Switch=$switchName, BVI=$bviNumber");
                            break;
                        }
                    }
                    break;
                }
            }
            
            // Check if BVI interface already exists with this IP
            try {
                $stmt = $this->db->prepare("SELECT id FROM cin_switch_bvi_interfaces WHERE bvi_ipv6 = ?");
                $stmt->execute([$relayIp]);
                if ($stmt->fetch()) {
                    $bviSkipped++;
                    $this->info("  → BVI already exists for relay IP: $relayIp");
                    continue;
                }
            } catch (\PDOException $e) {
                $this->error("  ✗ Database error checking BVI: " . $e->getMessage());
                $bviErrors++;
                continue;
            }
            
            // Check if switch exists, create if not
            $switchId = null;
            if ($switchName) {
                $stmt = $this->db->prepare("SELECT id FROM cin_switches WHERE hostname = ?");
                $stmt->execute([$switchName]);
                $switch = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                if (!$switch) {
                    // Create switch
                    $stmt = $this->db->prepare("INSERT INTO cin_switches (hostname) VALUES (?)");
                    $stmt->execute([$switchName]);
                    $switchId = $this->db->lastInsertId();
                    $this->success("  ✓ Created switch: $switchName");
                } else {
                    $switchId = $switch['id'];
                }
            } else {
                // Create a generic switch
                $switchName = 'AUTO-IMPORT';
                $stmt = $this->db->prepare("SELECT id FROM cin_switches WHERE hostname = ?");
                $stmt->execute([$switchName]);
                $switch = $stmt->fetch(\PDO::FETCH_ASSOC);
                
                if (!$switch) {
                    $stmt = $this->db->prepare("INSERT INTO cin_switches (hostname) VALUES (?)");
                    $stmt->execute([$switchName]);
                    $switchId = $this->db->lastInsertId();
                } else {
                    $switchId = $switch['id'];
                }
            }
            
            // Create BVI interface
            $interfaceNumber = $bviNumber ?? 100; // Default to BVI100 if not found
            $stmt = $this->db->prepare(
                "INSERT INTO cin_switch_bvi_interfaces (switch_id, interface_number, bvi_ipv6, vlan_id) 
                 VALUES (?, ?, ?, ?)"
            );
            $stmt->execute([$switchId, $interfaceNumber, $relayIp, $interfaceNumber]);
            
            $bviCreated++;
            $this->success("  ✓ Created BVI$interfaceNumber for $switchName: $relayIp");
        }
        
        $this->info("\nBVI Interface Summary:");
        $this->info("  Created: $bviCreated");
        $this->info("  Skipped (already exist): $bviSkipped");
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

                // Use Kea API to add option definition
                $keaApiUrl = $_ENV['KEA_API_ENDPOINT'];
                $keaCommand = [
                    'command' => 'option-def6-set',
                    'service' => ['dhcp6'],
                    'arguments' => [
                        'option-defs' => [[
                            'code' => $code,
                            'name' => $name,
                            'type' => $type,
                            'space' => $space,
                            'array' => $optionDef['array'] ?? false,
                            'record-types' => $optionDef['record-types'] ?? null,
                            'encapsulate' => $optionDef['encapsulate'] ?? ''
                        ]],
                        'remote' => ['type' => 'mysql'],
                        'server-tags' => ['all']
                    ]
                ];

                $ch = curl_init($keaApiUrl);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($keaCommand));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                
                $this->debug("Sending option definition to Kea API: " . json_encode($keaCommand));
                $responseJson = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                $this->debug("Kea API response (HTTP $httpCode): $responseJson");
                $response = json_decode($responseJson, true);
                
                if (isset($response[0]['result']) && $response[0]['result'] === 0) {
                    $this->stats['options']['imported']++;
                    $this->success("  ✓ Imported option definition: $name (code $code)");
                } else {
                    $errorMsg = $response[0]['text'] ?? 'Unknown error';
                    $this->stats['options']['skipped']++;
                    $this->warning("  Option definition $code ($name) failed: $errorMsg");
                }

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

                // Use Kea API to set options
                $keaApiUrl = $_ENV['KEA_API_ENDPOINT'];
                
                if ($subnetId) {
                    // Subnet-specific option
                    $keaCommand = [
                        'command' => 'option6-subnet-set',
                        'service' => ['dhcp6'],
                        'arguments' => [
                            'subnets' => [[
                                'id' => $subnetId,
                                'option-data' => [[
                                    'code' => $code,
                                    'name' => $name,
                                    'data' => $data,
                                    'space' => $option['space'] ?? 'dhcp6'
                                ]]
                            ]],
                            'remote' => ['type' => 'mysql'],
                            'server-tags' => ['all']
                        ]
                    ];
                } else {
                    // Global option
                    $keaCommand = [
                        'command' => 'option6-global-set',
                        'service' => ['dhcp6'],
                        'arguments' => [
                            'options' => [[
                                'code' => $code,
                                'name' => $name,
                                'data' => $data,
                                'space' => $option['space'] ?? 'dhcp6'
                            ]],
                            'remote' => ['type' => 'mysql'],
                            'server-tags' => ['all']
                        ]
                    ];
                }

                $ch = curl_init($keaApiUrl);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($keaCommand));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                
                $this->debug("Sending option to Kea API: " . json_encode($keaCommand));
                $responseJson = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                $this->debug("Kea API response (HTTP $httpCode): $responseJson");
                $response = json_decode($responseJson, true);
                
                if (isset($response[0]['result']) && $response[0]['result'] === 0) {
                    $this->stats['options']['imported']++;
                    $this->info("  ✓ Imported option: $name");
                } else {
                    $errorMsg = $response[0]['text'] ?? 'Unknown error';
                    $this->stats['options']['skipped']++;
                    $this->warning("  Option $name failed: $errorMsg");
                }

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

            $this->debug("Sending subnet to Kea API: " . json_encode($data));
            $responseJson = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (curl_errno($ch)) {
                throw new Exception('Kea API Error: ' . curl_error($ch));
            }
            curl_close($ch);

            $this->debug("Kea API response (HTTP $httpCode): $responseJson");
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
                $errorMsg = $response[0]['text'] ?? 'Unknown error';
                $this->error("    ✗ Kea API error: $errorMsg");
                $this->debug("Full response: " . json_encode($response));
                throw new Exception("Kea API returned error: $errorMsg");
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
                // Use Kea API to add reservation
                $keaApiUrl = $_ENV['KEA_API_ENDPOINT'];
                
                $reservation_data = [
                    'subnet-id' => $subnetId,
                    'ip-addresses' => [$ipAddress]
                ];
                
                if (isset($reservation['hw-address'])) {
                    $reservation_data['hw-address'] = $reservation['hw-address'];
                }
                if (isset($reservation['duid'])) {
                    $reservation_data['duid'] = $reservation['duid'];
                }
                if ($hostname) {
                    $reservation_data['hostname'] = $hostname;
                }
                
                $keaCommand = [
                    'command' => 'reservation-add',
                    'service' => ['dhcp6'],
                    'arguments' => [
                        'reservation' => $reservation_data
                    ]
                ];

                $ch = curl_init($keaApiUrl);
                curl_setopt($ch, CURLOPT_POST, 1);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($keaCommand));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                
                $this->debug("Sending reservation to Kea API: " . json_encode($keaCommand));
                $responseJson = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                $this->debug("Kea API response (HTTP $httpCode): $responseJson");
                $response = json_decode($responseJson, true);
                
                if (isset($response[0]['result']) && $response[0]['result'] === 0) {
                    $this->stats['reservations']['imported']++;
                    $this->success("      ✓ Imported reservation: $ipAddress ($hostname)");
                } else {
                    $errorMsg = $response[0]['text'] ?? 'Unknown error';
                    $this->stats['reservations']['skipped']++;
                    $this->warning("      Reservation $ipAddress ($hostname) failed: $errorMsg");
                }
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

    private function debug($message) {
        echo Colors::CYAN . "[DEBUG] " . $message . Colors::RESET . "\n";
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
