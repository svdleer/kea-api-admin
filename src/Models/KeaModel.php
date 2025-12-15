<?php
namespace App\Models;

use Exception;
use App\Database\Database;

class KeaModel
{
    private array $keaServers;
    private string $keaService;
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->keaService = 'dhcp6';
        
        // Load active Kea servers from database
        $stmt = $this->db->prepare("SELECT id, name, api_url FROM kea_servers WHERE is_active = 1 ORDER BY priority");
        $stmt->execute();
        $this->keaServers = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        if (empty($this->keaServers)) {
            throw new Exception('No active Kea servers configured in database');
        }
    }

    public function sendKeaCommand($command, $arguments = []) 
    {
        // Send command to primary server (first in list)
        $primaryServer = $this->keaServers[0];
        
        $data = [
            "command" => $command,
            "service" => [$this->keaService],
            "arguments" => $arguments
        ];
        
        $ch = curl_init($primaryServer['api_url']);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new Exception("Kea API Error ({$primaryServer['name']}): $error");
        }
        
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("Kea API HTTP Error ({$primaryServer['name']}): HTTP $httpCode - " . substr($response, 0, 200));
        }

        return $response;
    }
}