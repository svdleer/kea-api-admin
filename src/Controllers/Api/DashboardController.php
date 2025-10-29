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
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $totalSubnets = $result ? intval($result['total']) : 0;
            error_log("Total DHCP subnets: $totalSubnets");
            
            // Count active leases from Kea (if available)
            $totalLeases = 0;
            $assignedLeases = 0;
            
            try {
                $keaConfig = require BASE_PATH . '/config/kea.php';
                // Try to get lease stats from Kea
                $keaMonitor = new KeaStatusMonitor($keaConfig['servers']);
                $keaServers = $keaMonitor->getServersStatus();
                
                foreach ($keaServers as $server) {
                    if ($server['online'] && isset($server['leases']) && is_array($server['leases'])) {
                        $totalLeases += intval($server['leases']['total'] ?? 0);
                        $assignedLeases += intval($server['leases']['assigned'] ?? 0);
                    }
                }
                error_log("Kea lease stats - Total: $totalLeases, Assigned: $assignedLeases");
            } catch (\Exception $e) {
                error_log("Could not fetch lease stats from Kea: " . $e->getMessage());
            }
            
            // Count reservations - check if hosts table exists first
            $totalReservations = 0;
            $stmt = $this->db->query("SHOW TABLES LIKE 'hosts'");
            if ($stmt->rowCount() > 0) {
                // hosts table exists, try to count reservations
                try {
                    $stmt = $this->db->query("SELECT COUNT(*) as total FROM hosts");
                    $result = $stmt->fetch(\PDO::FETCH_ASSOC);
                    $totalReservations = $result ? intval($result['total']) : 0;
                    error_log("Total reservations: $totalReservations");
                } catch (\Exception $e) {
                    error_log("Could not count hosts: " . $e->getMessage());
                }
            } else {
                error_log("hosts table does not exist");
            }
            
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
