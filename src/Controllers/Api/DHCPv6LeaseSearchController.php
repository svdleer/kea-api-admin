<?php

namespace App\Controllers\Api;

use App\Database\Database;
use App\Helpers\ApiResponse;

class DHCPv6LeaseSearchController
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Advanced lease search with multiple filters
     */
    public function searchLeases()
    {
        error_log("=== SEARCH LEASES START ===");
        
        // Clear output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        ob_start();
        
        try {
            header('Content-Type: application/json');
            error_log("Search: Headers set");
            
            // Get filter parameters
            $ipv6Address = $_GET['ipv6_address'] ?? null;
            $duid = $_GET['duid'] ?? null;
            $hostname = $_GET['hostname'] ?? null;
            $switchId = $_GET['switch_id'] ?? null;
            $bviId = $_GET['bvi_id'] ?? null;
            $subnetId = $_GET['subnet_id'] ?? null;
            $leaseType = $_GET['lease_type'] ?? null;
            
            // Convert MAC address to DUID if needed
            // DUID format: 00:03:00:01:<mac-address>
            // 00:03 = Type 3 (DUID-LL, Link-layer address)
            // 00:01 = Hardware type 1 (Ethernet)
            if ($duid) {
                // Normalize the DUID/MAC format (remove dots, dashes, spaces)
                $cleanDuid = preg_replace('/[.\-\s]/', ':', strtolower($duid));
                
                // Check if it's a MAC address (6 octets) without DUID prefix
                $parts = explode(':', $cleanDuid);
                if (count($parts) == 6) {
                    // It's a MAC address - convert to DUID-LL format
                    $duid = '00:03:00:01:' . $cleanDuid;
                    error_log("Search: Converted MAC to DUID: $cleanDuid -> $duid");
                } else {
                    $duid = $cleanDuid;
                }
            }
            
            error_log("Search params: duid=$duid, ipv6=$ipv6Address, hostname=$hostname, subnet=$subnetId");
            $state = $_GET['state'] ?? null;
            $dateFrom = $_GET['date_from'] ?? null;
            $dateTo = $_GET['date_to'] ?? null;
            $export = $_GET['export'] ?? null;
            
            // Pagination
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $pageSize = isset($_GET['page_size']) ? min(500, max(10, intval($_GET['page_size']))) : 50;

            // Use Kea API to search leases
            error_log("Search: Creating DHCP model");
            $dhcpModel = new \App\Models\DHCP($this->db);
            $leases = [];
            
            // Search by DUID using Kea API
            if ($duid) {
                error_log("Search: Calling lease6-get-by-duid with duid: $duid");
                try {
                    $result = $dhcpModel->sendKeaCommand('lease6-get-by-duid', [
                        'duid' => $duid
                    ]);
                    error_log("Search: Kea result: " . json_encode($result));
                    
                    if (isset($result[0]['arguments']['leases'])) {
                        $leases = $result[0]['arguments']['leases'];
                        error_log("Search: Found " . count($leases) . " leases");
                    } else {
                        error_log("Search: No leases found in response");
                    }
                } catch (\Exception $keaEx) {
                    error_log("Search: Kea API error: " . $keaEx->getMessage());
                    // Continue with empty leases array
                }
            }
            // Search by IPv6 address
            elseif ($ipv6Address) {
                $result = $dhcpModel->sendKeaCommand('lease6-get-by-address', [
                    'ip-address' => $ipv6Address
                ]);
                
                if (isset($result[0]['arguments'])) {
                    $leases = [$result[0]['arguments']];
                }
            }
            // Search by hostname
            elseif ($hostname) {
                $result = $dhcpModel->sendKeaCommand('lease6-get-by-hostname', [
                    'hostname' => $hostname
                ]);
                
                if (isset($result[0]['arguments']['leases'])) {
                    $leases = $result[0]['arguments']['leases'];
                }
            }
            // Get all leases from subnet
            elseif ($subnetId) {
                $result = $dhcpModel->sendKeaCommand('lease6-get-all', [
                    'subnets' => [(int)$subnetId]
                ]);
                
                if (isset($result[0]['arguments']['leases'])) {
                    $leases = $result[0]['arguments']['leases'];
                }
            }
            else {
                throw new \Exception('Please provide at least one search criterion: DUID, IPv6 address, hostname, or subnet ID');
            }

            // Apply additional filters to results (client-side filtering)
            if ($leaseType !== null && $leaseType !== '') {
                $leases = array_filter($leases, function($lease) use ($leaseType) {
                    return isset($lease['type']) && $lease['type'] == $leaseType;
                });
            }

            if ($state !== null && $state !== '') {
                $leases = array_filter($leases, function($lease) use ($state) {
                    return isset($lease['state']) && $lease['state'] == $state;
                });
            }

            // Filter by date range
            if ($dateFrom) {
                $dateFromTimestamp = strtotime($dateFrom);
                $leases = array_filter($leases, function($lease) use ($dateFromTimestamp) {
                    return isset($lease['cltt']) && $lease['cltt'] >= $dateFromTimestamp;
                });
            }

            if ($dateTo) {
                $dateToTimestamp = strtotime($dateTo . ' 23:59:59');
                $leases = array_filter($leases, function($lease) use ($dateToTimestamp) {
                    return isset($lease['cltt']) && $lease['cltt'] <= $dateToTimestamp;
                });
            }

            // Re-index array after filtering
            $leases = array_values($leases);
            
            $totalCount = count($leases);

            // Apply pagination
            $offset = ($page - 1) * $pageSize;
            $paginatedLeases = array_slice($leases, $offset, $pageSize);
            
            // Handle export
            if ($export === 'csv') {
                $this->exportLeasesToCSV($leases);
                return;
            }

            // Format leases for response
            $formattedLeases = array_map(function($lease) {
                return [
                    'address' => $lease['ip-address'] ?? '',
                    'duid' => $lease['duid'] ?? '',
                    'hwaddr' => $lease['hwaddr'] ?? '',
                    'hostname' => $lease['hostname'] ?? '',
                    'subnet_id' => $lease['subnet-id'] ?? 0,
                    'valid_lifetime' => $lease['valid-lft'] ?? 0,
                    'expire' => isset($lease['cltt'], $lease['valid-lft']) ? 
                        ($lease['cltt'] + $lease['valid-lft']) : 0,
                    'pref_lifetime' => $lease['preferred-lft'] ?? 0,
                    'lease_type' => $lease['type'] ?? 0,
                    'iaid' => $lease['iaid'] ?? 0,
                    'prefix_len' => $lease['prefix-len'] ?? 0,
                    'state' => $lease['state'] ?? 0,
                    'user_context' => $lease['user-context'] ?? null
                ];
            }, $paginatedLeases);

            $response = [
                'success' => true,
                'data' => [
                    'data' => $formattedLeases,
                    'total' => $totalCount,
                    'page' => $page,
                    'page_size' => $pageSize,
                    'total_pages' => ceil($totalCount / $pageSize)
                ]
            ];
            
            echo json_encode($response);
            ob_end_flush();

        } catch (\Exception $e) {
            error_log("Lease search error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            ob_clean();
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Failed to search leases: ' . $e->getMessage()
            ]);
            ob_end_flush();
        }
    }

    /**
     * Export search results to CSV
     */
    private function exportLeasesToCSV($leases)
    {
        try {
            // Set headers for CSV download
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="dhcp_leases_' . date('Y-m-d_His') . '.csv"');
            
            // Create output stream
            $output = fopen('php://output', 'w');
            
            // Write UTF-8 BOM for Excel compatibility
            fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
            
            // Write header row
            fputcsv($output, [
                'IPv6 Address',
                'DUID',
                'MAC Address',
                'Hostname',
                'Subnet ID',
                'Lease Type',
                'State',
                'Valid Lifetime',
                'Expires',
                'IAID',
                'Prefix Length'
            ]);
            
            // Write data rows
            foreach ($leases as $lease) {
                $leaseType = $lease['type'] ?? 0;
                $state = $lease['state'] ?? 0;
                $expires = isset($lease['cltt'], $lease['valid-lft']) ? 
                    date('Y-m-d H:i:s', $lease['cltt'] + $lease['valid-lft']) : '';
                
                fputcsv($output, [
                    $lease['ip-address'] ?? '',
                    $lease['duid'] ?? '',
                    $lease['hwaddr'] ?? '',
                    $lease['hostname'] ?? '',
                    $lease['subnet-id'] ?? 0,
                    $leaseType,
                    $state,
                    $lease['valid-lft'] ?? 0,
                    $expires,
                    $lease['iaid'] ?? 0,
                    $lease['prefix-len'] ?? 0
                ]);
            }
            
            fclose($output);
            exit;
            
        } catch (\Exception $e) {
            error_log("CSV export error: " . $e->getMessage());
            header('Content-Type: application/json');
            ApiResponse::error('Failed to export CSV: ' . $e->getMessage(), 500);
        }
    }
}