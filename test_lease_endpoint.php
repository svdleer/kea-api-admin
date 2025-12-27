<?php
// Test script to debug lease endpoint
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->load();

echo "Testing DHCPv6LeaseController initialization...\n";

try {
    $database = \App\Database\Database::getInstance();
    echo "✓ Database connected\n";
    
    $controller = new \App\Controllers\Api\DHCPv6LeaseController();
    echo "✓ Controller created\n";
    
    // Simulate the request
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/api/dhcp/leases/146/143/start/10';
    
    ob_start();
    $controller->getLeases('146', '143', 'start', '10');
    $output = ob_get_clean();
    
    echo "Response:\n";
    echo $output . "\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
