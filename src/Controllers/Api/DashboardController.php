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
            
            ApiResponse::success([
                'total_switches' => $totalSwitches,
                'total_bvi' => $totalBVI,
                'latest_switch' => $latestSwitch,
                'recent_switches' => array_reverse($recentSwitches),
                'dhcp' => $dhcpStats,
                'radius' => $radiusStats
            ]);
        } catch (\Exception $e) {
            ApiResponse::error('Failed to retrieve dashboard statistics: ' . $e->getMessage(), 500);
        }
    }
    
    /**
     * Get DHCP statistics from cin_bvi_dhcp_subnet and Kea
     */
    private function getDhcpStatistics()
    {
        try {
            // Count configured subnets
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM cin_bvi_dhcp_subnet");
            $totalSubnets = $stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Count active leases from Kea (if available)
            $keaConfig = require BASE_PATH . '/config/kea.php';
            $totalLeases = 0;
            $assignedLeases = 0;
            
            try {
                // Try to get lease stats from Kea
                $keaMonitor = new KeaStatusMonitor($keaConfig['servers']);
                $keaServers = $keaMonitor->getServersStatus();
                
                foreach ($keaServers as $server) {
                    if ($server['online'] && isset($server['leases'])) {
                        $totalLeases += $server['leases']['total'] ?? 0;
                        $assignedLeases += $server['leases']['assigned'] ?? 0;
                    }
                }
            } catch (\Exception $e) {
                error_log("Could not fetch lease stats from Kea: " . $e->getMessage());
            }
            
            // Count reservations
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM hosts WHERE ip_reservations_mode = 'out-of-pool'");
            $totalReservations = $stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;
            
            return [
                'total_subnets' => $totalSubnets,
                'total_leases' => $totalLeases,
                'assigned_leases' => $assignedLeases,
                'utilization_percent' => $totalLeases > 0 ? round(($assignedLeases / $totalLeases) * 100, 1) : 0,
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
            // Count total NAS devices
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM nas");
            $totalNas = $stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Count RADIUS users
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM radcheck");
            $totalUsers = $stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Count active sessions (radacct entries with acctstoptime IS NULL)
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM radacct WHERE acctstoptime IS NULL");
            $activeSessions = $stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;
            
            // Get today's authentication attempts
            $stmt = $this->db->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN authdate >= CURDATE() THEN 1 ELSE 0 END) as today
                FROM radpostauth 
                WHERE authdate >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $authStats = $stmt->fetch(\PDO::FETCH_ASSOC);
            $totalAuth24h = $authStats['total'] ?? 0;
            
            return [
                'total_nas' => $totalNas,
                'total_users' => $totalUsers,
                'active_sessions' => $activeSessions,
                'auth_last_24h' => $totalAuth24h
            ];
        } catch (\Exception $e) {
            error_log("RADIUS stats error: " . $e->getMessage());
            return [
                'total_nas' => 0,
                'total_users' => 0,
                'active_sessions' => 0,
                'auth_last_24h' => 0
            ];
        }
    }

    /**
     * Get Kea server status
     */
    public function getKeaStatus()
    {
        try {
            $keaConfig = require BASE_PATH . '/config/kea.php';
            $keaMonitor = new KeaStatusMonitor($keaConfig['servers']);
            $keaServers = $keaMonitor->getServersStatus();
            $haStatus = $keaMonitor->getHAStatus();
            
            ApiResponse::success([
                'servers' => $keaServers,
                'ha_status' => $haStatus
            ]);
        } catch (\Exception $e) {
            ApiResponse::error('Failed to retrieve Kea status: ' . $e->getMessage(), 500);
        }
    }
}
