<?php

namespace App\Controllers;

use App\Models\RadiusServerConfig;

class RadiusLogsController
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function index()
    {
        // Get RADIUS servers
        $radiusConfigModel = new RadiusServerConfig($this->db);
        $radiusServers = $radiusConfigModel->getAllServers();
        
        // Filter only enabled servers
        $radiusServers = array_filter($radiusServers, function($server) {
            return $server['enabled'] == 1;
        });

        $logs = [];
        $nasStats = [];

        // Query each RADIUS server
        foreach ($radiusServers as $server) {
            try {
                // Connect to RADIUS server's MySQL
                $radiusDb = new \PDO(
                    "mysql:host={$server['host']};port={$server['port']};dbname={$server['database']};charset=utf8mb4",
                    $server['username'],
                    $server['password'],
                    [
                        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                        \PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
                    ]
                );

                // Get recent authentication logs (last 24 hours)
                $stmt = $radiusDb->query("
                    SELECT 
                        ra.username,
                        ra.reply,
                        ra.authdate,
                        COALESCE(n.nasname, 'Unknown') as nas_ip,
                        COALESCE(n.shortname, 'Unknown') as nas_name
                    FROM radpostauth ra
                    LEFT JOIN (
                        SELECT DISTINCT nasname, shortname 
                        FROM nas
                    ) n ON ra.pass = n.secret OR ra.username IN (
                        SELECT DISTINCT username FROM radacct WHERE nasipaddress = n.nasname
                    )
                    WHERE ra.authdate >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    ORDER BY ra.authdate DESC
                    LIMIT 200
                ");
                
                $serverLogs = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                
                foreach ($serverLogs as $log) {
                    $log['server'] = $server['name'];
                    $logs[] = $log;
                }

                // Get NAS statistics
                $stmt = $radiusDb->query("
                    SELECT 
                        COALESCE(n.nasname, 'Unknown') as nas_ip,
                        COALESCE(n.shortname, 'Unknown') as nas_name,
                        SUM(CASE WHEN ra.reply = 'Access-Accept' THEN 1 ELSE 0 END) as success_count,
                        SUM(CASE WHEN ra.reply = 'Access-Reject' THEN 1 ELSE 0 END) as failed_count,
                        COUNT(*) as total_count
                    FROM radpostauth ra
                    LEFT JOIN nas n ON EXISTS (
                        SELECT 1 FROM radacct 
                        WHERE username = ra.username 
                        AND nasipaddress = n.nasname 
                        LIMIT 1
                    )
                    WHERE ra.authdate >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    GROUP BY nas_ip, nas_name
                    ORDER BY total_count DESC
                ");
                
                $serverNasStats = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                
                foreach ($serverNasStats as $stat) {
                    $key = $stat['nas_ip'];
                    if (!isset($nasStats[$key])) {
                        $nasStats[$key] = [
                            'nas_ip' => $stat['nas_ip'],
                            'nas_name' => $stat['nas_name'],
                            'success_count' => 0,
                            'failed_count' => 0,
                            'total_count' => 0
                        ];
                    }
                    $nasStats[$key]['success_count'] += $stat['success_count'];
                    $nasStats[$key]['failed_count'] += $stat['failed_count'];
                    $nasStats[$key]['total_count'] += $stat['total_count'];
                }

            } catch (\Exception $e) {
                error_log("Failed to get logs from RADIUS server {$server['name']}: " . $e->getMessage());
            }
        }

        // Sort logs by date (most recent first)
        usort($logs, function($a, $b) {
            return strtotime($b['authdate']) - strtotime($a['authdate']);
        });

        // Sort NAS stats by total count
        usort($nasStats, function($a, $b) {
            return $b['total_count'] - $a['total_count'];
        });

        ob_start();
        include __DIR__ . '/../../views/radius/logs.php';
        $content = ob_get_clean();

        $auth = $GLOBALS['auth'];
        require_once __DIR__ . '/../../views/layout.php';
    }
}
