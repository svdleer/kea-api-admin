<?php

namespace App\Controllers\Api;

use App\Database\Database;
use App\Models\CinSwitch;
use App\Kea\KeaStatusMonitor;
use App\Helpers\ApiResponse;

class DashboardController
{
    private $db;
    private $cinSwitch;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->cinSwitch = new CinSwitch($this->db);
    }

    /**
     * Get dashboard statistics
     */
    public function getStats()
    {
        // Clear any output buffers and suppress errors
        while (ob_get_level()) {
            ob_end_clean();
        }
        ob_start();
        
        try {
            $switches = $this->cinSwitch->getAllSwitches();
            $totalSwitches = count($switches);
            
            // Calculate total BVI count from all switches
            $totalBVI = 0;
            foreach ($switches as $switch) {
                $totalBVI += $this->cinSwitch->getBviCount($switch['id']);
            }
            
            // Get latest switch
            $latestSwitch = !empty($switches) ? end($switches) : null;
            
            // Get recent switches (last 5)
            $recentSwitches = array_slice($switches, -5);
            
            // Get DHCP statistics
            $dhcpStats = $this->getDhcpStatistics();
            
            // Get RADIUS statistics
            $radiusStats = $this->getRadiusStatistics();
            
            // Clean output buffer before sending JSON
            ob_end_clean();
            
            ApiResponse::success([
                'total_switches' => $totalSwitches,
                'total_bvi' => $totalBVI,
                'latest_switch' => $latestSwitch,
                'recent_switches' => array_reverse($recentSwitches),
                'dhcp' => $dhcpStats,
                'radius' => $radiusStats
            ]);
        } catch (\Exception $e) {
            // Clean output buffer before sending error
            while (ob_get_level()) {
                ob_end_clean();
            }
            ApiResponse::error('Failed to retrieve dashboard statistics: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get DHCP statistics from Kea API
     */
    private function getDhcpStatistics()
    {
        try {
            // Get active servers from database
            $stmt = $this->db->query(
                "SELECT * FROM kea_servers WHERE is_active = 1 ORDER BY priority ASC"
            );
            $dbServers = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Convert database servers to format expected by KeaStatusMonitor
            $servers = array_map(function($server) {
                return [
                    'name' => $server['name'],
                    'url' => $server['api_url']
                ];
            }, $dbServers);
            
            $keaMonitor = new KeaStatusMonitor($servers);
            $keaServers = $keaMonitor->getServersStatus();
            
            $totalSubnets = 0;
            $totalLeases = 0;
            $assignedLeases = 0;
            $totalReservations = 0;
            
            // In HA setup, both servers share the same lease database
            // Try primary first, then fall back to any online server that has valid stats
            foreach ($keaServers as $server) {
                if ($server['online']) {
                    $serverName = $server['name'] ?? 'unknown';
                    $hasValidStats = isset($server['leases']['total']) && $server['leases']['total'] > 0;
                    
                    // Skip mock/secondary servers with fake data
                    if (strtolower($serverName) === 'secondary' && !$hasValidStats) {
                        continue;
                    }
                    
                    error_log("Using server: $serverName (Total leases: " . ($server['leases']['total'] ?? 0) . ")");
                    
                    // Get subnet count from Kea
                    if (isset($server['subnets'])) {
                        $totalSubnets = intval($server['subnets']);
                    }
                    
                    // Get lease stats from Kea
                    if (isset($server['leases']) && is_array($server['leases'])) {
                        $totalLeases = intval($server['leases']['total'] ?? 0);
                        $assignedLeases = intval($server['leases']['assigned'] ?? 0);
                    }
                    
                    // If we found valid statistics, stop looking
                    if ($totalLeases > 0) {
                        break;
                    }
                }
            }
            
            // Get reservations count from Kea database (hosts table)
            try {
                $stmt = $this->db->query("SELECT COUNT(*) as total FROM hosts");
                $result = $stmt->fetch(\PDO::FETCH_ASSOC);
                $totalReservations = $result ? intval($result['total']) : 0;
            } catch (\Exception $e) {
                error_log("Could not count hosts from Kea DB: " . $e->getMessage());
            }
            
            error_log("Kea stats - Subnets: $totalSubnets, Total leases: $totalLeases, Assigned: $assignedLeases, Reservations: $totalReservations");
            
            // For IPv6, calculate utilization with more precision or use count display
            $utilizationPercent = 0;
            if ($totalLeases > 0) {
                $utilizationPercent = ($assignedLeases / $totalLeases) * 100;
                // If percentage is very small (< 0.01%), show more decimals
                if ($utilizationPercent > 0 && $utilizationPercent < 0.01) {
                    $utilizationPercent = round($utilizationPercent, 6);
                } else {
                    $utilizationPercent = round($utilizationPercent, 2);
                }
            }
            
            return [
                'total_subnets' => $totalSubnets,
                'total_leases' => $totalLeases,
                'assigned_leases' => $assignedLeases,
                'utilization_percent' => $utilizationPercent,
                'total_reservations' => $totalReservations
            ];
        } catch (\Exception $e) {
            error_log("DHCP stats error: " . $e->getMessage());
            return [
                'total_subnets' => 0,
                'total_leases' => 0,
                'assigned_leases' => 0,
                'utilization_percent' => 0,
                'total_reservations' => 0
            ];
        }
    }
    
    /**
     * Get RADIUS statistics
     */
    private function getRadiusStatistics()
    {
        try {
            // Get active RADIUS servers from database
            $stmt = $this->db->query(
                "SELECT * FROM radius_server_config WHERE enabled = 1 ORDER BY display_order ASC"
            );
            $radiusServers = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            if (empty($radiusServers)) {
                return [
                    'total_nas' => 0,
                    'total_users' => 0,
                    'active_sessions' => 0,
                    'auth_last_24h' => 0,
                    'auth_success_24h' => 0,
                    'auth_failed_24h' => 0,
                    'servers' => []
                ];
            }
            
            $totalNas = 0;
            $totalUsers = 0;
            $activeSessions = 0;
            $totalAuth24h = 0;
            $authSuccess24h = 0;
            $authFailed24h = 0;
            $serverStats = [];
            
            // Query each RADIUS server
            foreach ($radiusServers as $server) {
                try {
                    // Connect to RADIUS server's MySQL
                    $radiusDb = new \PDO(
                        "mysql:host={$server['host']};port={$server['port']};dbname={$server['database']};charset=utf8mb4",
                        $server['username'],
                        $server['password'],
                        [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
                    );
                    
                    $serverStat = ['name' => $server['name'], 'online' => true];
                    
                    // Count NAS devices
                    $stmt = $radiusDb->query("SELECT COUNT(*) as total FROM nas");
                    $nasCount = $stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;
                    $serverStat['nas'] = $nasCount;
                    $totalNas += $nasCount;
                    
                    // Count users (from radcheck)
                    $stmt = $radiusDb->query("SELECT COUNT(DISTINCT username) as total FROM radcheck");
                    $userCount = $stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;
                    $serverStat['users'] = $userCount;
                    $totalUsers = max($totalUsers, $userCount); // Use max since users might be duplicated
                    
                    // Count active sessions
                    $stmt = $radiusDb->query("SELECT COUNT(*) as total FROM radacct WHERE acctstoptime IS NULL");
                    $sessions = $stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;
                    $serverStat['active_sessions'] = $sessions;
                    $activeSessions += $sessions;
                    
                    // Auth attempts last 24h
                    $stmt = $radiusDb->query("
                        SELECT COUNT(*) as total
                        FROM radpostauth 
                        WHERE authdate >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    ");
                    $auth24h = $stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;
                    $serverStat['auth_24h'] = $auth24h;
                    $totalAuth24h += $auth24h;
                    
                    // Successful auth last 24h
                    $stmt = $radiusDb->query("
                        SELECT COUNT(*) as total
                        FROM radpostauth 
                        WHERE authdate >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                        AND reply = 'Access-Accept'
                    ");
                    $success = $stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;
                    $serverStat['auth_success'] = $success;
                    $authSuccess24h += $success;
                    
                    // Failed auth last 24h
                    $stmt = $radiusDb->query("
                        SELECT COUNT(*) as total
                        FROM radpostauth 
                        WHERE authdate >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                        AND reply = 'Access-Reject'
                    ");
                    $failed = $stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;
                    $serverStat['auth_failed'] = $failed;
                    $authFailed24h += $failed;
                    
                    $serverStats[] = $serverStat;
                    
                } catch (\Exception $e) {
                    error_log("Failed to connect to RADIUS server {$server['name']}: " . $e->getMessage());
                    $serverStats[] = [
                        'name' => $server['name'],
                        'online' => false,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            return [
                'total_nas' => $totalNas,
                'total_users' => $totalUsers,
                'active_sessions' => $activeSessions,
                'auth_last_24h' => $totalAuth24h,
                'auth_success_24h' => $authSuccess24h,
                'auth_failed_24h' => $authFailed24h,
                'servers' => $serverStats
            ];
        } catch (\Exception $e) {
            error_log("RADIUS stats error: " . $e->getMessage());
            return [
                'total_nas' => 0,
                'total_users' => 0,
                'active_sessions' => 0,
                'auth_last_24h' => 0,
                'auth_success_24h' => 0,
                'auth_failed_24h' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get Kea server status
     */
    public function getKeaStatus()
    {
        // Clear any output buffers and suppress errors
        while (ob_get_level()) {
            ob_end_clean();
        }
        ob_start();
        
        try {
            // Get active servers from database
            $stmt = $this->db->query(
                "SELECT * FROM kea_servers WHERE is_active = 1 ORDER BY priority ASC"
            );
            $dbServers = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Convert database servers to format expected by KeaStatusMonitor
            $servers = array_map(function($server) {
                return [
                    'name' => $server['name'],
                    'url' => $server['api_url']
                ];
            }, $dbServers);
            
            $keaMonitor = new KeaStatusMonitor($servers);
            $keaServers = $keaMonitor->getServersStatus();
            $haStatus = $keaMonitor->getHAStatus();
            
            // Clean output buffer before sending JSON
            ob_end_clean();
            
            ApiResponse::success([
                'servers' => $keaServers,
                'ha_status' => $haStatus
            ]);
        } catch (\Exception $e) {
            // Clean output buffer before sending error
            while (ob_get_level()) {
                ob_end_clean();
            }
            ApiResponse::error('Failed to retrieve Kea status: ' . $e->getMessage(), 500);
        }
    }
}
