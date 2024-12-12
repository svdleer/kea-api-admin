<?php

namespace App\Kea;

class DHCPv6Client {
    private string $keaApiEndpoint;
    private array $servers;

    public function __construct(string $keaApiEndpoint, array $servers) {
        $this->keaApiEndpoint = $keaApiEndpoint;
        $this->servers = $servers;
    }

    public function applySubnetConfig(array $subnet): bool {
        // Build the Kea configuration for this subnet
        $subnetConfig = $this->buildSubnetConfig($subnet);
        
        // Apply configuration to all servers in the HA setup
        $success = true;
        foreach ($this->servers as $server) {
            if (!$this->sendConfigToServer($server, $subnetConfig)) {
                $success = false;
                break;
            }
        }
        
        return $success;
    }

    private function buildSubnetConfig(array $subnet): array {
        // Calculate the pool range
        $pool = [
            'pool' => $subnet['pool']['start'] . ' - ' . $subnet['pool']['end']
        ];

        // Basic subnet configuration
        return [
            'subnet6' => [
                [
                    'id' => $subnet['id'],
                    'subnet' => $subnet['prefix'],
                    'interface' => $subnet['bvi_name'],
                    'pools' => [$pool],
                    'option-data' => [
                        [
                            'name' => 'dns-servers',
                            'code' => 23,
                            'space' => 'dhcp6',
                            'csv-format' => true,
                            'data' => '2001:4860:4860::8888, 2001:4860:4860::8844'
                        ]
                    ],
                    'reservation-mode' => 'global',
                    'reservations-global' => true,
                    'reservations-in-subnet' => true,
                    'reservations-out-of-pool' => false
                ]
            ]
        ];
    }

    private function sendConfigToServer(array $server, array $config): bool {
        $ch = curl_init($server['url']);
        
        $payload = json_encode([
            'command' => 'config-set',
            'service' => ['dhcp6'],
            'arguments' => $config
        ]);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload)
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);

        if ($httpCode === 200) {
            $result = json_decode($response, true);
            return isset($result['result']) && $result['result'] === 0;
        }

        return false;
    }
}