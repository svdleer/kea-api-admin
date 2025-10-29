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
            $keaConfig = require BASE_PATH . '/config/kea.php';
            $keaMonitor = new KeaStatusMonitor($keaConfig['servers']);
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
            // Check if RADIUS tables exist
            $tables = ['nas', 'radcheck', 'radacct', 'radpostauth'];
            $existingTables = [];
            
            foreach ($tables as $table) {
                $stmt = $this->db->query("SHOW TABLES LIKE '$table'");
                if ($stmt->rowCount() > 0) {
                    $existingTables[] = $table;
                }
            }
            
            error_log("RADIUS tables found: " . implode(', ', $existingTables));
            
            // Count total NAS devices
            $totalNas = 0;
            if (in_array('nas', $existingTables)) {
                $stmt = $this->db->query("SELECT COUNT(*) as total FROM nas");
                $totalNas = $stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;
                error_log("NAS count: $totalNas");
            }
            
            // Count RADIUS users
            $totalUsers = 0;
            if (in_array('radcheck', $existingTables)) {
                $stmt = $this->db->query("SELECT COUNT(*) as total FROM radcheck");
                $totalUsers = $stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;
                error_log("RADIUS users: $totalUsers");
            }
            
            // Count active sessions (radacct entries with acctstoptime IS NULL)
            $activeSessions = 0;
            if (in_array('radacct', $existingTables)) {
                $stmt = $this->db->query("SELECT COUNT(*) as total FROM radacct WHERE acctstoptime IS NULL");
                $activeSessions = $stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;
                error_log("Active sessions: $activeSessions");
            }
            
            // Get today's authentication attempts
            $totalAuth24h = 0;
            if (in_array('radpostauth', $existingTables)) {
                $stmt = $this->db->query("
                    SELECT COUNT(*) as total
                    FROM radpostauth 
                    WHERE authdate >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ");
                $totalAuth24h = $stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;
                error_log("Auth last 24h: $totalAuth24h");
            }
            
            return [
                'total_nas' => $totalNas,
                'total_users' => $totalUsers,
                'active_sessions' => $activeSessions,
                'auth_last_24h' => $totalAuth24h,
                'tables_available' => $existingTables
            ];
        } catch (\Exception $e) {
            error_log("RADIUS stats error: " . $e->getMessage());
            return [
                'total_nas' => 0,
                'total_users' => 0,
                'active_sessions' => 0,
                'auth_last_24h' => 0,
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
            $keaConfig = require BASE_PATH . '/config/kea.php';
            $keaMonitor = new KeaStatusMonitor($keaConfig['servers']);
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
