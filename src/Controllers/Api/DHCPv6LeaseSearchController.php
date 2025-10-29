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
        try {
            // Get filter parameters
            $ipv6Address = $_GET['ipv6_address'] ?? null;
            $duid = $_GET['duid'] ?? null;
            $hostname = $_GET['hostname'] ?? null;
            $switchId = $_GET['switch_id'] ?? null;
            $bviId = $_GET['bvi_id'] ?? null;
            $subnetId = $_GET['subnet_id'] ?? null;
            $leaseType = $_GET['lease_type'] ?? null;
            $state = $_GET['state'] ?? null;
            $dateFrom = $_GET['date_from'] ?? null;
            $dateTo = $_GET['date_to'] ?? null;
            $export = $_GET['export'] ?? null;
            
            // Pagination
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $pageSize = isset($_GET['page_size']) ? min(500, max(10, intval($_GET['page_size']))) : 50;
            $offset = ($page - 1) * $pageSize;

            // Build the query
            $sql = "SELECT 
                        l.address,
                        l.duid,
                        l.valid_lifetime,
                        l.expire,
                        l.subnet_id,
                        l.pref_lifetime,
                        l.lease_type,
                        l.iaid,
                        l.prefix_len,
                        l.fqdn_fwd,
                        l.fqdn_rev,
                        l.hostname,
                        l.hwaddr,
                        l.state,
                        l.user_context,
                        s.subnet,
                        s.name as subnet_name,
                        bvi.id as bvi_id,
                        bvi.interface_number as bvi_number,
                        sw.id as switch_id,
                        sw.hostname as switch_hostname
                    FROM lease6 l
                    LEFT JOIN dhcp6_subnet s ON l.subnet_id = s.subnet_id
                    LEFT JOIN cin_switch_bvi_interfaces bvi ON s.bvi_id = bvi.id
                    LEFT JOIN cin_switches sw ON bvi.cin_switch_id = sw.id
                    WHERE 1=1";

            $params = [];

            // Apply filters
            if ($ipv6Address) {
                $sql .= " AND l.address LIKE :ipv6_address";
                $params[':ipv6_address'] = '%' . $ipv6Address . '%';
            }

            if ($duid) {
                // Support both with and without colons/dashes
                $cleanDuid = str_replace([':', '-', ' '], '', $duid);
                $sql .= " AND REPLACE(REPLACE(REPLACE(HEX(l.duid), ':', ''), '-', ''), ' ', '') LIKE :duid";
                $params[':duid'] = '%' . $cleanDuid . '%';
            }

            if ($hostname) {
                $sql .= " AND l.hostname LIKE :hostname";
                $params[':hostname'] = '%' . $hostname . '%';
            }

            if ($switchId) {
                $sql .= " AND sw.id = :switch_id";
                $params[':switch_id'] = $switchId;
            }

            if ($bviId) {
                $sql .= " AND bvi.id = :bvi_id";
                $params[':bvi_id'] = $bviId;
            }

            if ($subnetId) {
                $sql .= " AND l.subnet_id = :subnet_id";
                $params[':subnet_id'] = $subnetId;
            }

            if ($leaseType !== null && $leaseType !== '') {
                $sql .= " AND l.lease_type = :lease_type";
                $params[':lease_type'] = $leaseType;
            }

            if ($state !== null && $state !== '') {
                $sql .= " AND l.state = :state";
                $params[':state'] = $state;
            }

            if ($dateFrom) {
                $timestamp = strtotime($dateFrom);
                if ($timestamp !== false) {
                    $sql .= " AND l.expire >= :date_from";
                    $params[':date_from'] = $timestamp;
                }
            }

            if ($dateTo) {
                $timestamp = strtotime($dateTo);
                if ($timestamp !== false) {
                    $sql .= " AND l.expire <= :date_to";
                    $params[':date_to'] = $timestamp;
                }
            }

            // Get total count
            $countSql = "SELECT COUNT(*) as total FROM (" . $sql . ") as subquery";
            $countStmt = $this->db->prepare($countSql);
            foreach ($params as $key => $value) {
                $countStmt->bindValue($key, $value);
            }
            $countStmt->execute();
            $totalCount = $countStmt->fetch(\PDO::FETCH_ASSOC)['total'];

            // If export to CSV
            if ($export === 'csv') {
                $this->exportToCSV($sql, $params);
                return;
            }

            // Add ordering and pagination
            $sql .= " ORDER BY l.expire DESC LIMIT :limit OFFSET :offset";
            
            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $pageSize, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            
            $stmt->execute();
            $leases = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Convert binary DUID to hex for display
            foreach ($leases as &$lease) {
                if ($lease['duid']) {
                    $lease['duid'] = bin2hex($lease['duid']);
                }
                if ($lease['hwaddr']) {
                    $lease['hwaddr'] = bin2hex($lease['hwaddr']);
                }
            }

            ApiResponse::success([
                'data' => $leases,
                'total' => $totalCount,
                'page' => $page,
                'page_size' => $pageSize,
                'total_pages' => ceil($totalCount / $pageSize)
            ]);

        } catch (\Exception $e) {
            error_log("Lease search error: " . $e->getMessage());
            ApiResponse::error('Failed to search leases: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Export search results to CSV
     */
    private function exportToCSV($sql, $params)
    {
        try {
            // Execute query without pagination
            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            $leases = $stmt->fetchAll(\PDO::FETCH_ASSOC);

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
                'Switch',
                'BVI Interface',
                'Subnet',
                'Lease Type',
                'State',
                'Valid Lifetime',
                'Expires',
                'IAID',
                'Prefix Length'
            ]);
            
            // Write data rows
            foreach ($leases as $lease) {
                $duid = $lease['duid'] ? bin2hex($lease['duid']) : '';
                $hwaddr = $lease['hwaddr'] ? bin2hex($lease['hwaddr']) : '';
                $leaseType = $lease['lease_type'] == 0 ? 'IA_NA (Address)' : 
                            ($lease['lease_type'] == 2 ? 'IA_PD (Prefix)' : 'Unknown');
                $state = $lease['state'] == 0 ? 'Active' : 
                        ($lease['state'] == 1 ? 'Declined' : 'Expired');
                $expires = $lease['expire'] ? date('Y-m-d H:i:s', $lease['expire']) : '';
                
                fputcsv($output, [
                    $lease['address'],
                    $duid,
                    $hwaddr,
                    $lease['hostname'] ?: '',
                    $lease['switch_hostname'] ?: '',
                    'BVI' . ($lease['bvi_number'] ?: ''),
                    $lease['subnet'] ?: '',
                    $leaseType,
                    $state,
                    $lease['valid_lifetime'],
                    $expires,
                    $lease['iaid'],
                    $lease['prefix_len']
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
