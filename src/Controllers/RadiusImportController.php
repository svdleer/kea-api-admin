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

            foreach ($clients as $client) {
                try {
                    // Check if client already exists
                    $radiusClientModel = new RadiusClient($this->db);
                    if ($radiusClientModel->nasnameExists($client['ip_address'])) {
                        $skipped++;
                        error_log("Skipping duplicate client: {$client['name']} ({$client['ip_address']})");
                        continue;
                    }
                    
                    // Step 1: Create BVI interface first (to get BVI ID for RADIUS client)
                    try {
                        $bviId = $this->createBviInterface($client);
                        $bviCreated++;
                    } catch (\Exception $e) {
                        // Don't fail the whole import if BVI creation fails
                        error_log("Failed to create BVI for {$client['name']}: " . $e->getMessage());
                        $errors[] = "Failed to create BVI for {$client['name']}: " . $e->getMessage();
                        continue;
                    }
                    
                    // Step 2: Create RADIUS client using the BVI ID
                    $radiusResult = $this->callApiWithJson(
                        $this->radiusController,
                        'createClient',
                        [
                            'bvi_interface_id' => $bviId,
                            'ipv6_address' => $client['ip_address'],
                            'secret' => $client['secret'] ?? '',
                            'shortname' => $client['name']
                        ]
                    );
                    
                    if (isset($radiusResult['success']) && $radiusResult['success']) {
                        $imported++;
                    } else {
                        $errors[] = "Failed to import {$client['name']}: " . ($radiusResult['message'] ?? 'Unknown error');
                        error_log("RADIUS API error for {$client['name']}: " . ($radiusResult['message'] ?? 'Unknown error'));
                    }
                } catch (\Exception $e) {
                    $errors[] = "Failed to import {$client['name']}: " . $e->getMessage();
                    error_log("Import error for {$client['name']}: " . $e->getMessage());
                }
            }

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
                'errors' => $errors
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
        // BVI is always BVI100
        $bviNumber = 100;

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
                return $existingBvi['id'];
            }
            
            // Create BVI only using API
            $bviResult = $this->callApiWithJson(
                $this->bviController,
                'create',
                [
                    'interface_number' => $bviNumber,
                    'ipv6_address' => $client['ip_address']
                ],
                $switchId
            );
            
            if (isset($bviResult['id'])) {
                return $bviResult['id'];
            }
            throw new \Exception("Failed to create BVI: " . ($bviResult['error'] ?? 'Unknown error'));
        }
        
        // Create both switch and BVI using CinSwitch API
        $switchResult = $this->callApiWithJson(
            $this->switchController,
            'create',
            [
                'hostname' => $switchHostname,
                'interface_number' => $bviNumber,
                'ipv6_address' => $client['ip_address']
            ]
        );
        
        if (!isset($switchResult['id'])) {
            throw new \Exception("Failed to create switch: " . ($switchResult['error'] ?? 'Unknown error'));
        }
        
        $switchId = $switchResult['id'];
        
        // Get the BVI ID that was created with the switch
        $stmt = $this->db->prepare("SELECT id FROM cin_switch_bvi_interfaces WHERE switch_id = ? AND interface_number = ?");
        $stmt->execute([$switchId, $bviNumber]);
        $bvi = $stmt->fetch();
        
        if (!$bvi) {
            throw new \Exception("BVI was not created with switch");
        }
        
        return $bvi['id'];
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
