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
            
            ApiResponse::success([
                'total_switches' => $totalSwitches,
                'total_bvi' => $totalBVI,
                'latest_switch' => $latestSwitch,
                'recent_switches' => array_reverse($recentSwitches)
            ]);
        } catch (\Exception $e) {
            ApiResponse::error('Failed to retrieve dashboard statistics: ' . $e->getMessage(), 500);
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
