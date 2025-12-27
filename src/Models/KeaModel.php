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
        
        // Don't throw in constructor - let methods handle this gracefully
        if (empty($this->keaServers)) {
            error_log("Warning: No active Kea servers configured in database");
        }
    }

    public function sendKeaCommand($command, $arguments = []) 
    {
        // Check if servers are configured before sending command
        if (empty($this->keaServers)) {
            throw new Exception('No active Kea servers configured. Please add servers in Admin → Kea Servers.');
        }
        
        $data = [
            "command" => $command,
            "service" => [$this->keaService],
            "arguments" => $arguments
        ];
        
        // Send to all servers (HA MySQL backend requires both servers to be updated)
        $responses = [];
        $errors = [];
        
        foreach ($this->keaServers as $server) {
            $ch = curl_init($server['api_url']);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);

            $response = curl_exec($ch);

            if (curl_errno($ch)) {
                $error = "Kea API Error on {$server['name']}: " . curl_error($ch);
                error_log($error);
                $errors[] = $error;
                curl_close($ch);
                continue;
            }
            
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode !== 200) {
                $error = "Kea API HTTP Error on {$server['name']}: HTTP $httpCode";
                error_log($error);
                $errors[] = $error;
                continue;
            }
            
            $responses[] = $response;
            error_log("Kea command '{$command}' succeeded on {$server['name']}");
        }
        
        // If ALL servers failed, throw an exception
        if (count($errors) === count($this->keaServers)) {
            throw new Exception("Kea command failed on all servers: " . implode("; ", $errors));
        }
        
        // Return the first successful response
        return $responses[0];
    }
}