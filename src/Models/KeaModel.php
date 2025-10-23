<?php
namespace App\Models;

use Exception;

class KeaModel
{
    private string $keaApiUrl;
    private string $keaService;

    public function __construct()
    {
        $this->keaApiUrl = $_ENV['KEA_API_ENDPOINT'];
        $this->keaService = 'dhcp6';
    }

    public function sendKeaCommand($command, $arguments = []) 
    {
        $data = [
            "command" => $command,
            "service" => [$this->keaService],
            "arguments" => $arguments
        ];
        $ch = curl_init($this->keaApiUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception('Kea API Error: ' . curl_error($ch));
        }
        curl_close($ch);

        return $response;
    }
}
