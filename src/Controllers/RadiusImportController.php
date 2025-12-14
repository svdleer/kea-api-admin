<?php

namespace App\Controllers;

use App\Models\RadiusClient;
use App\Models\RadiusServerConfig;
use App\Controllers\Api\CinSwitch;
use App\Controllers\Api\BVIController;
use App\Controllers\Api\RadiusController;

class RadiusImportController
{
    private $db;
    private $switchController;
    private $bviController;
    private $radiusController;

    public function __construct($db)
    {
        $this->db = $db;
        $this->switchController = new CinSwitch($db);
        $this->bviController = new BVIController();
        $this->radiusController = new RadiusController(new RadiusClient($db), new \App\Auth\Authentication($db));
    }
    
    /**
     * Helper to call API controller with JSON data
     */
    private function callApiWithJson($controller, $method, $data, ...$args)
    {
        // Register custom stream wrapper to mock php://input
        stream_wrapper_unregister("php");
        stream_wrapper_register("php", \App\Helpers\MockedInputStreamWrapper::class);
        file_put_contents("php://input", json_encode($data));
        
        ob_start();
        call_user_func_array([$controller, $method], $args);
        $response = ob_get_clean();
        
        // Restore original php stream wrapper
        stream_wrapper_restore("php");
        
        return json_decode($response, true);
    }

    public function showImportForm()
    {
        $currentPage = 'radius';
        ob_start();
        include __DIR__ . '/../../views/radius/import.php';
        $content = ob_get_clean();

        $auth = $GLOBALS['auth'];
        require_once __DIR__ . '/../../views/layout.php';
    }

    public function updateNames()
    {
        header('Content-Type: application/json');
        
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $edits = $input['edits'] ?? [];
            
            if (empty($edits)) {
                echo json_encode(['success' => true, 'message' => 'No changes to save']);
                return;
            }
            
            $radiusClient = new \App\Models\RadiusClient($this->db);
            $updated = 0;
            
            foreach ($edits as $edit) {
                try {
                    // Find client by IP address (nasname)
                    $stmt = $this->db->prepare("SELECT id FROM nas WHERE nasname = ?");
                    $stmt->execute([$edit['ip']]);
                    $client = $stmt->fetch();
                    
                    if ($client) {
                        // Update shortname in main database
                        $stmt = $this->db->prepare("UPDATE nas SET shortname = ? WHERE id = ?");
                        $stmt->execute([$edit['newName'], $client['id']]);
                        
                        // Sync to RADIUS servers
                        $updatedClient = $radiusClient->getClientById($client['id']);
                        if ($updatedClient) {
                            $radiusSync = new \App\Helpers\RadiusDatabaseSync();
                            $radiusSync->syncClientToAllServers($updatedClient, 'UPDATE');
                        }
                        
                        $updated++;
                    }
                } catch (\Exception $e) {
                    error_log("Failed to update client {$edit['ip']}: " . $e->getMessage());
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => "Updated $updated client name(s)"
            ]);
            
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Failed to update names: ' . $e->getMessage()
            ]);
        }
    }

    public function import()
    {
        // Disable error display to prevent breaking JSON
        ini_set('display_errors', '0');
        error_reporting(E_ALL);
        
        // Ensure no output before JSON
        ob_start();
        
        try {
            header('Content-Type: application/json');

            if (!isset($_FILES['clients_conf'])) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'No file uploaded']);
                return;
            }

            $file = $_FILES['clients_conf'];
            
            if ($file['error'] !== UPLOAD_ERR_OK) {
                ob_end_clean();
                echo json_encode(['success' => false, 'message' => 'File upload error']);
                return;
            }

            $content = file_get_contents($file['tmp_name']);
            
            // Log the file content for debugging
            error_log("Clients.conf content length: " . strlen($content));
            error_log("First 500 chars: " . substr($content, 0, 500));
            
            $clients = $this->parseClientsConf($content);
            
            error_log("Parsed clients count: " . count($clients));
            if (!empty($clients)) {
                error_log("First client: " . json_encode($clients[0]));
            }
            
            if (empty($clients)) {
                ob_end_clean();
                echo json_encode([
                    'success' => false, 
                    'message' => 'No clients found in file. Please check the file format.',
                    'debug' => [
                        'fileSize' => strlen($content),
                        'preview' => substr($content, 0, 200)
                    ]
                ]);
                return;
            }

            $radiusClientModel = new RadiusClient($this->db);
            $imported = 0;
            $bviCreated = 0;
            $skipped = 0;
            $errors = [];
            $importedClients = []; // Track imported client details

            foreach ($clients as $client) {
                error_log("Processing client {$client['name']} (loop iteration)");
                try {
                    // Check if client already exists
                    $radiusClientModel = new RadiusClient($this->db);
                    if ($radiusClientModel->nasnameExists($client['ip_address'])) {
                        $skipped++;
                        error_log("Skipping duplicate client: {$client['name']} ({$client['ip_address']})");
                        continue;
                    }
                    
                    error_log("Creating BVI for {$client['name']}");
                    // Step 1: Create BVI interface first (to get BVI ID for RADIUS client)
                    try {
                        $bviData = $this->createBviInterface($client);
                        $bviId = $bviData['bvi_id'];
                        $switchHostname = $bviData['switch_hostname'];
                        $bviCreated++;
                        error_log("BVI created successfully for {$client['name']}, ID: $bviId");
                    } catch (\Exception $e) {
                        // Don't fail the whole import if BVI creation fails
                        error_log("Failed to create BVI for {$client['name']}: " . $e->getMessage());
                        $errors[] = "Failed to create BVI for {$client['name']}: " . $e->getMessage();
                        continue;
                    }
                    
                    error_log("Creating RADIUS client for {$client['name']}");
                    // Step 2: Create RADIUS client using the model directly (not API to avoid exit())
                    try {
                        $radiusClientModel->createFromBvi(
                            $bviId,
                            $client['ip_address'],
                            $client['secret'] ?? null,
                            $client['name']
                        );
                        $imported++;
                        $importedClients[] = [
                            'name' => $client['name'],
                            'ip' => $client['ip_address'],
                            'switch' => $switchHostname
                        ];
                        error_log("Successfully imported {$client['name']}");
                    } catch (\Exception $e) {
                        $errors[] = "Failed to create RADIUS client for {$client['name']}: " . $e->getMessage();
                        error_log("RADIUS creation error for {$client['name']}: " . $e->getMessage());
                    }
                } catch (\Exception $e) {
                    $errors[] = "Failed to import {$client['name']}: " . $e->getMessage();
                    error_log("Import error for {$client['name']}: " . $e->getMessage());
                }
                error_log("Finished processing client {$client['name']}");
            }

            error_log("Import loop completed. Total imported: $imported, BVI created: $bviCreated, Skipped: $skipped, Errors: " . count($errors));

            $message = "Successfully imported $imported RADIUS clients";
            if ($skipped > 0) {
                $message .= " ($skipped skipped as duplicates)";
            }
            if ($bviCreated > 0) {
                $message .= " and created $bviCreated BVI interfaces";
            }

            ob_end_clean();
            echo json_encode([
                'success' => true,
                'message' => $message,
                'imported' => $imported,
                'skipped' => $skipped,
                'bviCreated' => $bviCreated,
                'errors' => $errors,
                'clients' => $importedClients
            ]);

        } catch (\Exception $e) {
            error_log("Import exception: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        } catch (\Error $e) {
            error_log("Import fatal error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            ob_end_clean();
            echo json_encode(['success' => false, 'message' => 'Fatal error: ' . $e->getMessage()]);
        }
    }

    private function createBviInterface($client)
    {
        // BVI interface number is 0 (GUI adds 100 for display as BVI100)
        $bviNumber = 0;

        // Extract switch hostname from client name
        // Example: "asdar151-bvi100" -> "asdar151"
        $switchHostname = $client['name'];
        if (preg_match('/^(.+?)-bvi\d+/i', $client['name'], $matches)) {
            $switchHostname = $matches[1];
        }
        
        // Check if switch already exists
        $stmt = $this->db->prepare("SELECT id FROM cin_switches WHERE hostname = ?");
        $stmt->execute([$switchHostname]);
        $existingSwitch = $stmt->fetch();
        
        if ($existingSwitch) {
            $switchId = $existingSwitch['id'];
            
            // Check if BVI already exists
            $stmt = $this->db->prepare("SELECT id FROM cin_switch_bvi_interfaces WHERE switch_id = ? AND interface_number = ?");
            $stmt->execute([$switchId, $bviNumber]);
            $existingBvi = $stmt->fetch();
            
            if ($existingBvi) {
                return ['bvi_id' => $existingBvi['id'], 'switch_hostname' => $switchHostname];
            }
            
            // Create BVI only
            $stmt = $this->db->prepare("
                INSERT INTO cin_switch_bvi_interfaces 
                (switch_id, interface_number, ipv6_address, created_at, updated_at) 
                VALUES (?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$switchId, $bviNumber, $client['ip_address']]);
            return ['bvi_id' => $this->db->lastInsertId(), 'switch_hostname' => $switchHostname];
        }
        
        // Create both switch and BVI in transaction
        $this->db->beginTransaction();
        
        try {
            // Create switch
            $stmt = $this->db->prepare("
                INSERT INTO cin_switches (hostname, created_at, updated_at) 
                VALUES (?, NOW(), NOW())
            ");
            $stmt->execute([$switchHostname]);
            $switchId = $this->db->lastInsertId();
            
            // Create BVI
            $stmt = $this->db->prepare("
                INSERT INTO cin_switch_bvi_interfaces 
                (switch_id, interface_number, ipv6_address, created_at, updated_at) 
                VALUES (?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$switchId, $bviNumber, $client['ip_address']]);
            $bviId = $this->db->lastInsertId();
            
            $this->db->commit();
            return ['bvi_id' => $bviId, 'switch_hostname' => $switchHostname];
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw new \Exception("Failed to create switch and BVI for $switchHostname: " . $e->getMessage());
        }
    }

    private function parseClientsConf($content)
    {
        $clients = [];
        $lines = explode("\n", $content);
        $currentClient = null;
        $inClientBlock = false;

        foreach ($lines as $line) {
            // Remove comments and trim
            $line = preg_replace('/#.*$/', '', $line);
            $line = trim($line);
            
            // Skip empty lines
            if (empty($line)) {
                continue;
            }

            // Check for client block start - more flexible pattern
            // Matches: client xxx {, client "xxx" {, client xxx{, etc.
            if (preg_match('/^client\s+["\']?([^\s{"\'"]+)["\']?\s*\{?/', $line, $matches)) {
                $inClientBlock = true;
                $currentClient = [
                    'name' => trim($matches[1], '"\''),
                    'ip_address' => '',
                    'secret' => '',
                    'type' => 'other',
                    'description' => ''
                ];
                continue;
            }

            // Check for block end
            if (strpos($line, '}') !== false) {
                if ($currentClient && !empty($currentClient['ip_address']) && !empty($currentClient['secret'])) {
                    $clients[] = $currentClient;
                }
                $currentClient = null;
                $inClientBlock = false;
                continue;
            }

            // Parse client attributes if we're in a block
            if ($inClientBlock && $currentClient) {
                // More flexible parsing - handles various spacing
                // ipaddr = 192.168.1.1 or ipaddr=192.168.1.1 or ipv6addr = 2001::/128
                if (preg_match('/^\s*(?:ipaddr|ipv6addr)\s*=\s*["\']?([^"\'\s\/]+)(?:\/\d+)?["\']?/', $line, $matches)) {
                    $currentClient['ip_address'] = trim($matches[1], '"\'');
                }
                // secret = mysecret or secret=mysecret
                elseif (preg_match('/^\s*secret\s*=\s*["\']?([^"\'\s]+)["\']?/', $line, $matches)) {
                    $currentClient['secret'] = trim($matches[1], '"\'');
                }
                // shortname = MySwitch or shortname="My Switch"
                elseif (preg_match('/^\s*shortname\s*=\s*["\']?(.+?)["\']?\s*$/', $line, $matches)) {
                    $currentClient['name'] = trim($matches[1], '"\'');
                }
                // nastype = other or nas_type = other
                elseif (preg_match('/^\s*(?:nastype|nas_type)\s*=\s*["\']?([^"\'\s]+)["\']?/', $line, $matches)) {
                    $currentClient['type'] = trim($matches[1], '"\'');
                }
                // require_message_authenticator = no (just ignore these)
                // Any other lines are ignored
            }
        }

        return $clients;
    }
}
