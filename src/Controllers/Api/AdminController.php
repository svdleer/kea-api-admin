<?php

namespace App\Controllers\Api;

use PDO;

class AdminController
{
    private $db;

    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Export Kea configuration
     * GET /api/admin/export/kea-config
     */
    public function exportKeaConfig()
    {
        // This will be implemented to generate kea-dhcp6.conf
        $this->jsonResponse([
            'success' => false,
            'message' => 'Export functionality coming soon'
        ]);
    }

    /**
     * Import Kea configuration
     * POST /api/admin/import/kea-config
     */
    public function importKeaConfig()
    {
        if (!isset($_FILES['config'])) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'No file uploaded'
            ], 400);
            return;
        }

        $file = $_FILES['config'];
        $tmpPath = $file['tmp_name'];

        // Call the import script
        $scriptPath = BASE_PATH . '/scripts/import_kea_config.php';
        $output = [];
        $returnCode = 0;

        exec("php $scriptPath $tmpPath 2>&1", $output, $returnCode);

        if ($returnCode === 0) {
            // Parse output for statistics
            $this->jsonResponse([
                'success' => true,
                'message' => 'Configuration imported successfully',
                'stats' => [
                    'subnets' => ['imported' => 0, 'skipped' => 0],
                    'reservations' => ['imported' => 0, 'skipped' => 0],
                    'options' => ['imported' => 0, 'skipped' => 0]
                ],
                'output' => implode("\n", $output)
            ]);
        } else {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Import failed',
                'output' => implode("\n", $output)
            ], 500);
        }
    }

    /**
     * Backup Kea database
     * GET /api/admin/backup/kea-database
     */
    public function backupKeaDatabase()
    {
        $filename = 'kea-db-' . date('Y-m-d-His') . '.sql';
        $filepath = BASE_PATH . '/backups/' . $filename;

        // Create backups directory if not exists
        if (!file_exists(BASE_PATH . '/backups')) {
            mkdir(BASE_PATH . '/backups', 0755, true);
        }

        // Get database credentials from env
        $dbHost = $_ENV['DB_HOST'] ?? 'localhost';
        $dbName = $_ENV['DB_NAME'] ?? 'kea_db';
        $dbUser = $_ENV['DB_USER'] ?? 'kea_db_user';
        $dbPass = $_ENV['DB_PASSWORD'] ?? '';

        // Execute mysqldump
        $command = sprintf(
            "mysqldump -h %s -u %s -p'%s' %s > %s 2>&1",
            escapeshellarg($dbHost),
            escapeshellarg($dbUser),
            $dbPass,
            escapeshellarg($dbName),
            escapeshellarg($filepath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode === 0 && file_exists($filepath)) {
            // Download the file
            header('Content-Type: application/sql');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);
            exit;
        } else {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Backup failed: ' . implode("\n", $output)
            ], 500);
        }
    }

    /**
     * Backup Kea leases
     * GET /api/admin/backup/kea-leases
     */
    public function backupKeaLeases()
    {
        $filename = 'kea-leases-' . date('Y-m-d-His') . '.sql';
        $filepath = BASE_PATH . '/backups/' . $filename;

        if (!file_exists(BASE_PATH . '/backups')) {
            mkdir(BASE_PATH . '/backups', 0755, true);
        }

        // Backup only lease-related tables
        $dbHost = $_ENV['DB_HOST'] ?? 'localhost';
        $dbName = $_ENV['DB_NAME'] ?? 'kea_db';
        $dbUser = $_ENV['DB_USER'] ?? 'kea_db_user';
        $dbPass = $_ENV['DB_PASSWORD'] ?? '';

        $command = sprintf(
            "mysqldump -h %s -u %s -p'%s' %s lease6 hosts > %s 2>&1",
            escapeshellarg($dbHost),
            escapeshellarg($dbUser),
            $dbPass,
            escapeshellarg($dbName),
            escapeshellarg($filepath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode === 0 && file_exists($filepath)) {
            header('Content-Type: application/sql');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);
            exit;
        } else {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Backup failed'
            ], 500);
        }
    }

    /**
     * Export Kea leases to CSV
     * GET /api/admin/export/kea-leases-csv
     */
    public function exportKeaLeasesCSV()
    {
        $filename = 'kea-leases-' . date('Y-m-d-His') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['Address', 'DUID', 'Valid Lifetime', 'Expire', 'Subnet ID', 'Hostname']);

        $query = "SELECT 
            INET6_NTOA(address) as address,
            HEX(duid) as duid,
            valid_lifetime,
            expire,
            subnet_id,
            hostname
            FROM lease6
            ORDER BY expire DESC";

        $stmt = $this->db->query($query);

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    }

    /**
     * Export RADIUS clients
     * GET /api/admin/export/radius-clients
     */
    public function exportRadiusClients()
    {
        $filename = 'radius-clients-' . date('Y-m-d-His') . '.conf';
        
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $query = "SELECT nasname, shortname, type, secret, description FROM radius_clients ORDER BY nasname";
        $stmt = $this->db->query($query);

        echo "# FreeRADIUS clients.conf\n";
        echo "# Generated: " . date('Y-m-d H:i:s') . "\n\n";

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            echo "client " . $row['shortname'] . " {\n";
            echo "    ipv6addr = " . $row['nasname'] . "\n";
            echo "    secret = " . $row['secret'] . "\n";
            echo "    shortname = " . $row['shortname'] . "\n";
            echo "    nastype = " . $row['type'] . "\n";
            if ($row['description']) {
                echo "    # " . $row['description'] . "\n";
            }
            echo "}\n\n";
        }

        exit;
    }

    /**
     * Backup RADIUS database
     * GET /api/admin/backup/radius-database/{type}
     */
    public function backupRadiusDatabase($type)
    {
        // Get RADIUS server config from database
        require_once BASE_PATH . '/src/Models/RadiusServerConfig.php';
        $configModel = new \App\Models\RadiusServerConfig($this->db);
        
        $serverIndex = ($type === 'primary') ? 0 : 1;
        $server = $configModel->getServerByOrder($serverIndex);

        if (!$server) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'RADIUS server not configured'
            ], 404);
            return;
        }

        $filename = 'radius-' . $type . '-' . date('Y-m-d-His') . '.sql';
        $filepath = BASE_PATH . '/backups/' . $filename;

        if (!file_exists(BASE_PATH . '/backups')) {
            mkdir(BASE_PATH . '/backups', 0755, true);
        }

        $command = sprintf(
            "mysqldump -h %s -P %d -u %s -p'%s' %s > %s 2>&1",
            escapeshellarg($server['host']),
            $server['port'],
            escapeshellarg($server['username']),
            $server['password'],
            escapeshellarg($server['database']),
            escapeshellarg($filepath)
        );

        exec($command, $output, $returnCode);

        if ($returnCode === 0 && file_exists($filepath)) {
            header('Content-Type: application/sql');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);
            exit;
        } else {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Backup failed'
            ], 500);
        }
    }

    /**
     * Full system backup
     * GET /api/admin/backup/full-system
     */
    public function fullSystemBackup()
    {
        $this->jsonResponse([
            'success' => false,
            'message' => 'Full system backup coming soon'
        ]);
    }

    /**
     * List backups
     * GET /api/admin/backups/list
     */
    public function listBackups()
    {
        $backupsDir = BASE_PATH . '/backups';
        
        if (!file_exists($backupsDir)) {
            $this->jsonResponse([
                'success' => true,
                'backups' => []
            ]);
            return;
        }

        $files = scandir($backupsDir);
        $backups = [];

        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || $file === '.gitkeep') {
                continue;
            }

            $filepath = $backupsDir . '/' . $file;
            if (is_file($filepath)) {
                $backups[] = [
                    'filename' => $file,
                    'type' => $this->getBackupType($file),
                    'size' => $this->formatFileSize(filesize($filepath)),
                    'date' => date('Y-m-d H:i:s', filemtime($filepath))
                ];
            }
        }

        // Sort by date descending
        usort($backups, function($a, $b) {
            return strtotime($b['date']) - strtotime($a['date']);
        });

        $this->jsonResponse([
            'success' => true,
            'backups' => $backups
        ]);
    }

    /**
     * Download backup
     * GET /api/admin/backup/download/{filename}
     */
    public function downloadBackup($filename)
    {
        $filepath = BASE_PATH . '/backups/' . basename($filename);

        if (!file_exists($filepath)) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Backup file not found'
            ], 404);
            return;
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }

    /**
     * Delete backup
     * DELETE /api/admin/backup/delete/{filename}
     */
    public function deleteBackup($filename)
    {
        $filepath = BASE_PATH . '/backups/' . basename($filename);

        if (!file_exists($filepath)) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Backup file not found'
            ], 404);
            return;
        }

        if (unlink($filepath)) {
            $this->jsonResponse([
                'success' => true,
                'message' => 'Backup deleted successfully'
            ]);
        } else {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Failed to delete backup'
            ], 500);
        }
    }

    /**
     * Helper: Get backup type from filename
     */
    private function getBackupType($filename)
    {
        if (strpos($filename, 'kea-db') !== false) return 'Kea Database';
        if (strpos($filename, 'kea-leases') !== false) return 'Kea Leases';
        if (strpos($filename, 'radius-primary') !== false) return 'RADIUS Primary';
        if (strpos($filename, 'radius-secondary') !== false) return 'RADIUS Secondary';
        if (strpos($filename, 'full-system') !== false) return 'Full System';
        return 'Unknown';
    }

    /**
     * Helper: Format file size
     */
    private function formatFileSize($bytes)
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }

    /**
     * Helper: Send JSON response
     */
    private function jsonResponse($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
