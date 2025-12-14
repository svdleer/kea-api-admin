<?php

namespace App\Controllers;

use App\Models\RadiusClient;
use App\Models\RadiusServerConfig;

class RadiusImportController
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
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
        header('Content-Type: application/json');

        if (!isset($_FILES['clients_conf'])) {
            echo json_encode(['success' => false, 'message' => 'No file uploaded']);
            return;
        }

        $file = $_FILES['clients_conf'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'File upload error']);
            return;
        }

        $content = file_get_contents($file['tmp_name']);
        
        try {
            $clients = $this->parseClientsConf($content);
            
            if (empty($clients)) {
                echo json_encode(['success' => false, 'message' => 'No clients found in file']);
                return;
            }

            $radiusClientModel = new RadiusClient($this->db);
            $imported = 0;
            $bviCreated = 0;
            $errors = [];

            foreach ($clients as $client) {
                try {
                    // Create RADIUS client
                    $radiusClientModel->create($client);
                    $imported++;
                    
                    // Also create BVI interface entry since the NAS IP is the BVI interface
                    try {
                        $this->createBviInterface($client);
                        $bviCreated++;
                    } catch (\Exception $e) {
                        // Don't fail the whole import if BVI creation fails
                        error_log("Failed to create BVI for {$client['name']}: " . $e->getMessage());
                    }
                } catch (\Exception $e) {
                    $errors[] = "Failed to import {$client['name']}: " . $e->getMessage();
                }
            }

            $message = "Successfully imported $imported RADIUS clients";
            if ($bviCreated > 0) {
                $message .= " and created $bviCreated BVI interfaces";
            }

            echo json_encode([
                'success' => true,
                'message' => $message,
                'imported' => $imported,
                'bviCreated' => $bviCreated,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Parse error: ' . $e->getMessage()]);
        }
    }

    private function createBviInterface($client)
    {
        // BVI is always BVI100
        $bviNumber = 100;

        // Check if BVI interface already exists with this IP
        $stmt = $this->db->prepare("
            SELECT id FROM cin_switch_bvi_interfaces 
            WHERE interface_ip = ?
        ");
        $stmt->execute([$client['ip_address']]);
        
        if ($stmt->fetch()) {
            // BVI already exists, skip
            return;
        }

        // Create BVI interface entry
        $stmt = $this->db->prepare("
            INSERT INTO cin_switch_bvi_interfaces 
            (switch_id, interface_number, interface_ip, description, created_at, updated_at)
            VALUES (NULL, ?, ?, ?, NOW(), NOW())
        ");
        
        $description = $client['name'] . ' - Imported from clients.conf';
        $stmt->execute([
            $bviNumber,
            $client['ip_address'],
            $description
        ]);
    }

    private function parseClientsConf($content)
    {
        $clients = [];
        $lines = explode("\n", $content);
        $currentClient = null;
        $inClientBlock = false;

        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip comments and empty lines
            if (empty($line) || $line[0] === '#') {
                continue;
            }

            // Check for client block start
            if (preg_match('/^client\s+([^\s{]+)\s*\{?/', $line, $matches)) {
                $inClientBlock = true;
                $currentClient = [
                    'name' => trim($matches[1]),
                    'ip_address' => '',
                    'secret' => '',
                    'type' => 'other',
                    'description' => ''
                ];
                continue;
            }

            // Check for block end
            if ($line === '}') {
                if ($currentClient && !empty($currentClient['ip_address']) && !empty($currentClient['secret'])) {
                    $clients[] = $currentClient;
                }
                $currentClient = null;
                $inClientBlock = false;
                continue;
            }

            // Parse client attributes
            if ($inClientBlock && $currentClient) {
                // ipaddr = 192.168.1.1
                if (preg_match('/ipaddr\s*=\s*(.+)/', $line, $matches)) {
                    $currentClient['ip_address'] = trim($matches[1]);
                }
                // secret = mysecret
                elseif (preg_match('/secret\s*=\s*(.+)/', $line, $matches)) {
                    $currentClient['secret'] = trim($matches[1]);
                }
                // shortname = MySwitch
                elseif (preg_match('/shortname\s*=\s*(.+)/', $line, $matches)) {
                    $currentClient['name'] = trim($matches[1]);
                }
                // nastype = other
                elseif (preg_match('/nastype\s*=\s*(.+)/', $line, $matches)) {
                    $currentClient['type'] = trim($matches[1]);
                }
            }
        }

        return $clients;
    }
}
