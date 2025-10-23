<?php

namespace App\Kea;

class KeaStatusMonitor {
    private array $servers;

    public function __construct(array $servers) {
        $this->servers = $servers;
    }

    /**
     * Get the status of all Kea servers
     * 
     * @return array Array of server statuses
     */
    public function getServersStatus(): array {
        $statuses = [];
        
        foreach ($this->servers as $server) {
            $statuses[] = $this->getServerStatus($server);
        }
        
        return $statuses;
    }

    /**
     * Get the status of a single Kea server
     * 
     * @param array $server Server configuration
     * @return array Server status information
     */
    private function getServerStatus(array $server): array {
        $status = [
            'name' => $server['name'],
            'url' => $server['url'],
            'online' => false,
            'version' => null,
            'uptime' => null,
            'response_time' => null,
            'error' => null,
            'leases' => null,
            'subnets' => null
        ];

        try {
            $startTime = microtime(true);
            
            // Try to get status from Kea control agent
            $response = $this->sendCommand($server['url'], 'status-get');
            
            $status['response_time'] = round((microtime(true) - $startTime) * 1000, 2);
            
            if ($response && isset($response['result']) && $response['result'] === 0) {
                $status['online'] = true;
                
                // Parse status information
                if (isset($response['arguments'])) {
                    $args = $response['arguments'];
                    
                    // Get uptime (pid indicates the service is running)
                    if (isset($args['pid'])) {
                        $status['uptime'] = $this->getUptimeFromPid($args['pid']);
                    }
                }
                
                // Try to get version info
                $versionResponse = $this->sendCommand($server['url'], 'version-get');
                if ($versionResponse && isset($versionResponse['arguments']['extended'])) {
                    $status['version'] = $versionResponse['arguments']['extended'];
                }
                
                // Try to get lease statistics
                $leaseStats = $this->sendCommand($server['url'], 'statistic-get-all');
                if ($leaseStats && isset($leaseStats['arguments'])) {
                    $status['leases'] = $this->parseLeaseStats($leaseStats['arguments']);
                }
                
                // Try to get subnet information
                $subnetResponse = $this->sendCommand($server['url'], 'subnet6-list');
                if ($subnetResponse && isset($subnetResponse['arguments']['subnets'])) {
                    $status['subnets'] = count($subnetResponse['arguments']['subnets']);
                }
            } else {
                $status['error'] = $response['text'] ?? ($response[0]['text'] ?? 'Unknown error');
                error_log("Kea response error for {$server['name']}: " . json_encode($response));
            }
        } catch (\Exception $e) {
            $status['error'] = $e->getMessage();
            // Log detailed error for debugging
            error_log("Kea Status Error for {$server['name']} ({$server['url']}): " . $e->getMessage());
        }

        return $status;
    }

    /**
     * Send a command to the Kea control agent
     * 
     * @param string $url Server URL
     * @param string $command Command to send
     * @param array $arguments Optional command arguments
     * @return array|null Response from server
     */
    private function sendCommand(string $url, string $command, array $arguments = []): ?array {
        $ch = curl_init($url);
        
        $payload = [
            'command' => $command,
            'service' => ['dhcp6']
        ];
        
        if (!empty($arguments)) {
            $payload['arguments'] = $arguments;
        }
        
        $payloadJson = json_encode($payload);

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payloadJson);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payloadJson)
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        
        curl_close($ch);

        if ($curlError) {
            throw new \Exception("Connection error: " . $curlError);
        }

        if ($httpCode !== 200) {
            throw new \Exception("HTTP error: " . $httpCode);
        }

        return json_decode($response, true);
    }

    /**
     * Get uptime information from process ID
     * 
     * @param int $pid Process ID
     * @return string|null Uptime string
     */
    private function getUptimeFromPid(int $pid): ?string {
        // Try to get process start time on Unix-like systems
        if (PHP_OS_FAMILY === 'Linux' || PHP_OS_FAMILY === 'Darwin') {
            $psOutput = shell_exec("ps -p $pid -o etime=");
            if ($psOutput) {
                return trim($psOutput);
            }
        }
        
        return "Running (PID: $pid)";
    }

    /**
     * Parse lease statistics from Kea response
     * 
     * @param array $statistics Statistics data
     * @return array Parsed lease statistics
     */
    private function parseLeaseStats(array $statistics): array {
        $leaseInfo = [
            'total' => 0,
            'assigned' => 0,
            'available' => 0
        ];

        foreach ($statistics as $stat) {
            if (isset($stat[0]) && isset($stat[1])) {
                $name = $stat[0];
                $value = $stat[1];
                
                if (strpos($name, 'assigned-nas') !== false) {
                    $leaseInfo['assigned'] += $value[0] ?? 0;
                } elseif (strpos($name, 'total-nas') !== false) {
                    $leaseInfo['total'] += $value[0] ?? 0;
                }
            }
        }
        
        $leaseInfo['available'] = $leaseInfo['total'] - $leaseInfo['assigned'];
        
        return $leaseInfo;
    }

    /**
     * Check if HA (High Availability) is working properly
     * 
     * @return array HA status information
     */
    public function getHAStatus(): array {
        $haStatus = [
            'configured' => false,
            'working' => false,
            'mode' => null,
            'primary_state' => null,
            'secondary_state' => null
        ];

        if (count($this->servers) >= 2) {
            $haStatus['configured'] = true;
            
            try {
                // Check primary server HA status
                $primaryResponse = $this->sendCommand($this->servers[0]['url'], 'ha-heartbeat');
                
                if ($primaryResponse && isset($primaryResponse['result']) && $primaryResponse['result'] === 0) {
                    $haStatus['working'] = true;
                    
                    if (isset($primaryResponse['arguments'])) {
                        $haStatus['mode'] = $primaryResponse['arguments']['mode'] ?? null;
                        $haStatus['primary_state'] = $primaryResponse['arguments']['state'] ?? null;
                    }
                }
                
                // Check secondary server HA status
                $secondaryResponse = $this->sendCommand($this->servers[1]['url'], 'ha-heartbeat');
                
                if ($secondaryResponse && isset($secondaryResponse['arguments'])) {
                    $haStatus['secondary_state'] = $secondaryResponse['arguments']['state'] ?? null;
                }
            } catch (\Exception $e) {
                $haStatus['error'] = $e->getMessage();
            }
        }

        return $haStatus;
    }
}
