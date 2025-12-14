<?php

// Start output buffering early for API routes to prevent HTML errors in JSON
ob_start();

define('BASE_PATH', __DIR__);

require_once BASE_PATH . '/vendor/autoload.php';

use App\Controllers\Api\UserController;
use App\Controllers\Api\IPv6Controller;
use App\Controllers\Api\NetworkController;
use App\Controllers\Api\DHCPController;

use App\Models\User;
use App\Models\IPv6Subnet;

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);
error_log("Script started - BASE_PATH: " . BASE_PATH);

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->load();

// Initialize session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true
    ]);
}

try {
    // Initialize router and dependencies
    $router = new App\Router();
    $database = \App\Database\Database::getInstance();
    $apiKeyModel = new App\Models\ApiKey($database);
    $userModel = new \App\Models\User($database);  
    $auth = new \App\Auth\Authentication($database);
    $dhcpModel = new \App\Models\DHCP($database);

    
    // Debug log
    error_log("Request URI: " . $_SERVER['REQUEST_URI']);
    error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);

    // Public routes
    $router->get('/', function() use ($auth) {
        if ($auth->isLoggedIn()) {
            header('Location: /dashboard');
            exit();
        } else {
            $loginPath = BASE_PATH . '/views/login.php';
            error_log("Attempting to load login file from: " . $loginPath);
            if (!file_exists($loginPath)) {
                error_log("Login file not found at: " . $loginPath);
                throw new Exception("Login file not found at: " . $loginPath);
            }
            require $loginPath;
        }
    });

    // Login route

    $router->post('/login', function() use ($auth) {
        if (empty($_POST['username']) || empty($_POST['password'])) {
            $_SESSION['error'] = 'Username and password are required';
            header('Location: /');
            exit();
        }
    
        if ($auth->login($_POST['username'], $_POST['password'])) {
            // Successful login - just redirect
            header('Location: /dashboard');
            exit();
        } else {
            $_SESSION['error'] = 'Invalid username or password';
            header('Location: /');
            exit();
        }
    });
    
    
    // Logout route 
    $router->get('/logout', function() use ($auth) {
        $auth->logout();
        header('Location: /');
        exit();
    });

    // Documentation route
    $router->get('/api/docs/spec', function() {
        header('Content-Type: application/json');
        $swaggerFile = BASE_PATH . '/swagger.json';
        if (file_exists($swaggerFile)) {
            echo file_get_contents($swaggerFile);
        } else {
            // Fallback to generated documentation if swagger.json doesn't exist
            echo json_encode(\App\Documentation\ApiDocumentation::getSpecification());
        }
    });
    

    // User Management Routes
    $router->get('/users', function() {
        require BASE_PATH . '/views/users/index.php';
    });
    
    // Admin Tools Routes
        $router->get('/admin/tools', function() use ($auth) {
        $title = 'Admin Tools';
        require BASE_PATH . '/views/admin/tools.php';
    })->middleware(new \App\Middleware\AuthMiddleware($auth, true));

    $router->get('/admin/settings', function() use ($auth) {
        $title = 'System Settings';
        require BASE_PATH . '/views/admin/settings.php';
    })->middleware(new \App\Middleware\AuthMiddleware($auth, true));

    $router->get('/admin/import-wizard', function() use ($auth) {
        $title = 'Kea Configuration Import Wizard';
        require BASE_PATH . '/views/admin/import-wizard.php';
    })->middleware(new \App\Middleware\AuthMiddleware($auth, true));

    $router->get('/admin/kea-servers', [new \App\Controllers\KeaServerController(), 'index'])
        ->middleware(new \App\Middleware\AuthMiddleware($auth, true));
    
    // Kea Servers API routes
    $router->get('/api/kea-servers', [new \App\Controllers\KeaServerController(), 'getServers'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));
    $router->post('/api/kea-servers', [new \App\Controllers\KeaServerController(), 'create'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));
    $router->put('/api/kea-servers/{id}', [new \App\Controllers\KeaServerController(), 'update'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));
    $router->delete('/api/kea-servers/{id}', [new \App\Controllers\KeaServerController(), 'delete'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));
    $router->get('/api/kea-servers/{id}/test', [new \App\Controllers\KeaServerController(), 'testConnection'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));
    
    $router->get('/api/users', [new UserController($userModel, $auth), 'list'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));
    $router->post('/api/users', [new UserController($userModel, $auth), 'create'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));
    $router->get('/api/users/{id}', [new UserController($userModel, $auth), 'getById'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));
    $router->put('/api/users/{id}', [new UserController($userModel, $auth), 'update'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));
    $router->delete('/api/users/{id}', [new UserController($userModel, $auth), 'delete'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));

    // User validation routes
    $router->get('/api/users/check-username/{username}', [new UserController($userModel, $auth), 'checkUsername'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));
    $router->get('/api/users/check-email/{email}', [new UserController($userModel, $auth), 'checkEmail'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));

    // Dashboard API routes
    $router->get('/api/dashboard/stats', [new \App\Controllers\Api\DashboardController(), 'getStats'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));
    $router->get('/api/dashboard/kea-status', [new \App\Controllers\Api\DashboardController(), 'getKeaStatus'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));

 

    // BVI Interfaces Overview
    $router->get('/bvi', function() use ($auth) {
        $currentPage = 'bvi';
        require BASE_PATH . '/views/bvi/index.php';
    })->middleware(new \App\Middleware\AuthMiddleware($auth));

    // RADIUS Clients (802.1X) Management
    $router->get('/radius', function() use ($auth) {
        $currentPage = 'radius';
        require BASE_PATH . '/views/radius/index.php';
    })->middleware(new \App\Middleware\AuthMiddleware($auth));

    // RADIUS Import Form
    $router->get('/radius/import', function() use ($database, $auth) {
        $controller = new \App\Controllers\RadiusImportController($database);
        $controller->showImportForm();
    })->middleware(new \App\Middleware\AuthMiddleware($auth));

    // RADIUS Import Action
    $router->post('/radius/import', function() use ($database, $auth) {
        $controller = new \App\Controllers\RadiusImportController($database);
        $controller->import();
    })->middleware(new \App\Middleware\AuthMiddleware($auth));

    // RADIUS Update Names After Import
    $router->post('/radius/update-names', function() use ($database, $auth) {
        $controller = new \App\Controllers\RadiusImportController($database);
        $controller->updateNames();
    })->middleware(new \App\Middleware\AuthMiddleware($auth));

    // RADIUS Delete Clients After Import
    $router->post('/radius/delete-clients', function() use ($database, $auth) {
        $controller = new \App\Controllers\RadiusImportController($database);
        $controller->deleteClients();
    })->middleware(new \App\Middleware\AuthMiddleware($auth));

    // RADIUS Authentication Logs
    $router->get('/radius/logs', function() use ($database, $auth) {
        $controller = new \App\Controllers\RadiusLogsController($database);
        $controller->index();
    })->middleware(new \App\Middleware\AuthMiddleware($auth));

    // RADIUS Settings (Admin Only)
    $router->get('/radius/settings', function() use ($auth) {
        $currentPage = 'radius-settings';
        require BASE_PATH . '/views/radius/settings.php';
    })->middleware(new \App\Middleware\AuthMiddleware($auth));

    // Prefixes Routes
    $router->get('/prefixes', function() use ($auth) {
        $currentPage = 'prefixes';
        require BASE_PATH . '/views/prefixes/index.php';
    })->middleware(new \App\Middleware\AuthMiddleware($auth));

    // Subnets Routes (Network management)
    $router->get('/subnets', function() use ($auth) {
        $currentPage = 'subnets';
        require BASE_PATH . '/views/subnets/index.php';
    })->middleware(new \App\Middleware\AuthMiddleware($auth));
    
    // DHCP Management
    $router->get('/dhcp', function() use ($auth) {
        $currentPage = 'dhcp';
        $subPage = 'subnets';
        require BASE_PATH . '/views/dhcp/index.php';
    })->middleware(new \App\Middleware\AuthMiddleware($auth));

    // DHCP Subnets (same as /dhcp)
    $router->get('/dhcp/subnets', function() use ($auth) {
        $currentPage = 'dhcp';
        $subPage = 'subnets';
        require BASE_PATH . '/views/dhcp/index.php';
    })->middleware(new \App\Middleware\AuthMiddleware($auth));

    // DHCP API Routes
    $router->get('/api/dhcp/subnets', [new DHCPController($dhcpModel, $auth), 'getAllSubnets'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));
    $router->post('/api/dhcp/subnets/check-duplicate', [new DHCPController($dhcpModel, $auth), 'checkDuplicate'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));
    $router->post('/api/dhcp/subnets', [new DHCPController($dhcpModel, $auth), 'create'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));
    $router->put('/api/dhcp/subnets/{id}', [new DHCPController($dhcpModel, $auth), 'update'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));
    $router->delete('/api/dhcp/subnets/{id}', [new DHCPController($dhcpModel, $auth), 'delete'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));
    $router->delete('/api/dhcp/orphaned-subnets/{keaId}', [new DHCPController($dhcpModel, $auth), 'deleteOrphaned'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, false));
    $router->post('/api/dhcp/link-orphaned-subnet', [new DHCPController($dhcpModel, $auth), 'linkOrphanedSubnet'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));
    $router->post('/api/dhcp/create-cin-and-link', [new DHCPController($dhcpModel, $auth), 'createCINAndLink'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));

    // DHCPv6 Options Definitions API Routes
    $optionsDefModel = new \App\Models\DHCPv6OptionsDefModel($database);
    $router->get('/api/dhcp/optionsdef', [new \App\Controllers\Api\DHCPv6OptionsDefController($optionsDefModel, $auth), 'list'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));
    $router->post('/api/dhcp/optionsdef', [new \App\Controllers\Api\DHCPv6OptionsDefController($optionsDefModel, $auth), 'create'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));
    $router->put('/api/dhcp/optionsdef/{code}', [new \App\Controllers\Api\DHCPv6OptionsDefController($optionsDefModel, $auth), 'update'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));
    $router->delete('/api/dhcp/optionsdef/{code}', [new \App\Controllers\Api\DHCPv6OptionsDefController($optionsDefModel, $auth), 'delete'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));

    // DHCPv6 Options API Routes
    $optionsModel = new \App\Models\DHCPv6OptionsModel($database);
    $router->get('/api/dhcp/options', [new \App\Controllers\Api\DHCPv6OptionsController($optionsModel, $optionsDefModel, $auth), 'list'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));
    $router->post('/api/dhcp/options', [new \App\Controllers\Api\DHCPv6OptionsController($optionsModel, $optionsDefModel, $auth), 'create'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));
    $router->put('/api/dhcp/options/{code}', [new \App\Controllers\Api\DHCPv6OptionsController($optionsModel, $optionsDefModel, $auth), 'update'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));
    $router->delete('/api/dhcp/options/{code}', [new \App\Controllers\Api\DHCPv6OptionsController($optionsModel, $optionsDefModel, $auth), 'delete'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));

    // DHCPv6 Lease/Reservation API Routes
    $leaseModel = new \App\Models\DHCPv6LeaseModel($database);
    $router->get('/api/dhcp/leases/{switchId}/{bviId}/{from}/{limit}', [new \App\Controllers\Api\DHCPv6LeaseController($leaseModel), 'getLeases'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));
    $router->delete('/api/dhcp/leases', [new \App\Controllers\Api\DHCPv6LeaseController($leaseModel), 'deleteLease'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));
    $router->post('/api/dhcp/static', [new \App\Controllers\Api\DHCPv6LeaseController($leaseModel), 'addStaticLease'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));
    $router->get('/api/dhcp/static/{subnetId}', [new \App\Controllers\Api\DHCPv6LeaseController($leaseModel), 'getStaticLeases'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));

    // DHCPv6 Advanced Lease Search
    $router->get('/api/dhcp/search-leases', [new \App\Controllers\Api\DHCPv6LeaseSearchController(), 'searchLeases'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));

    // IPv6 Subnets (Kea DHCP management)
    $router->get('/ipv6', function() use ($auth) {
        $currentPage = 'ipv6';
        require BASE_PATH . '/views/ipv6/index.php';
    });

    $router->get('/api/ipv6/subnets', [IPv6Controller::class, 'list']);
    $router->get('/api/ipv6/subnets/{subnetId}', [IPv6Controller::class, 'getById']);
    $router->post('/api/ipv6/subnets', [IPv6Controller::class, 'create']);
    $router->put('/api/ipv6/subnets/{subnetId}', [IPv6Controller::class, 'update']);
    $router->delete('/api/ipv6/subnets/{subnetId}', [IPv6Controller::class, 'delete']);
    $router->get('/api/ipv6/bvi/{bviId}/subnets', [IPv6Controller::class, 'getByBvi']);

    // DHCP Routes - Leases
    $router->get('/leases', function() {
        require BASE_PATH . '/leases.php';
    })->middleware(new \App\Middleware\AuthMiddleware($auth));

    $router->get('/dhcp/leases', function() {
        require BASE_PATH . '/views/dhcp/leases.php';
    })->middleware(new \App\Middleware\AuthMiddleware($auth));

    $router->get('/dhcp/options', function() {
        require BASE_PATH . '/views/dhcp/options.php';
    })->middleware(new \App\Middleware\AuthMiddleware($auth));

    $router->get('/dhcp/optionsdef', function() {
        require BASE_PATH . '/views/dhcp/optionsdef.php';
    })->middleware(new \App\Middleware\AuthMiddleware($auth));

    $router->get('/dhcp/search', function() {
        require BASE_PATH . '/views/dhcp/search.php';
    })->middleware(new \App\Middleware\AuthMiddleware($auth));

    // Load configuration for Kea DHCPv6 servers
   // $dhcp6Client = new \App\Kea\DHCPv6Client($_ENV['KEA_API_ENDPOINT'], $_ENV['KEA_PRIMARY_URL']);
    
    // Note: Authentication is handled by middleware on individual routes

    // API Key Management Routes
    $router->get('/api/keys', [new \App\Controllers\Api\ApiKeyController($apiKeyModel, $auth), 'list'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));
    $router->post('/api/keys', [new \App\Controllers\Api\ApiKeyController($apiKeyModel, $auth), 'create'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));
    $router->post('/api/keys/{id}/deactivate', [new \App\Controllers\Api\ApiKeyController($apiKeyModel, $auth), 'deactivate'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));
    $router->delete('/api/keys/{id}', [new \App\Controllers\Api\ApiKeyController($apiKeyModel, $auth), 'delete'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));


    // CIN Switch Routes - Read Only with Combined Auth
    $cinSwitchController = new \App\Controllers\Api\CinSwitch($database);
    $router->get('/api/switches', [$cinSwitchController, 'getAll'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));
    $router->get('/api/switches/{id}', [$cinSwitchController, 'getById'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));

    // CIN Switch Routes - Read/Write with Combined Auth
    $router->post('/api/switches', [$cinSwitchController, 'create'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));
    $router->put('/api/switches/{id}', [$cinSwitchController, 'update'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));
    $router->delete('/api/switches/{id}', [$cinSwitchController, 'delete'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));

    // Validation Check Routes with Combined Auth
    $router->get('/api/switches/check-exists', [$cinSwitchController, 'hostnameExists'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));
    $router->get('/api/switches/check-bvi', [$cinSwitchController, 'checkBvi'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));
    $router->get('/api/switches/check-ipv6', [$cinSwitchController, 'checkIpv6'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));

    // BVI check routes with Combined Auth
    $router->get('/api/switches/{switchId}/bvi/check-exists', [\App\Controllers\Api\BVIController::class, 'checkBVIExists'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));
    $router->get('/api/switches/bvi/check-ipv6', [\App\Controllers\Api\BVIController::class, 'checkIPv6Exists'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));


    // BVI Interface Routes with Combined Auth
    $router->get('/api/switches/{switchId}/bvi', [\App\Controllers\Api\BVIController::class, 'index'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));
    $router->post('/api/switches/{switchId}/bvi', [\App\Controllers\Api\BVIController::class, 'create'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));
    $router->put('/api/switches/{switchId}/bvi/{bviId}', [\App\Controllers\Api\BVIController::class, 'update'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));
    $router->delete('/api/switches/{switchId}/bvi/{bviId}', [\App\Controllers\Api\BVIController::class, 'delete'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));
    $router->get('/api/switches/{switchId}/bvi/{bviId}', [\App\Controllers\Api\BVIController::class, 'show'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));

    // RADIUS Client Routes (for 802.1X authentication)
    require_once BASE_PATH . '/src/Controllers/Api/RadiusController.php';
    require_once BASE_PATH . '/src/Models/RadiusClient.php';
    $radiusController = new \App\Controllers\Api\RadiusController(new \App\Models\RadiusClient($database), $auth);
    
    // Read routes
    $router->get('/api/radius/clients', [$radiusController, 'getAllClients'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));
    $router->get('/api/radius/clients/{id}', [$radiusController, 'getClientById'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));
    
    // Write routes
    $router->post('/api/radius/clients', [$radiusController, 'createClient'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));
    $router->put('/api/radius/clients/{id}', [$radiusController, 'updateClient'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));
    $router->delete('/api/radius/clients/{id}', [$radiusController, 'deleteClient'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));
    
    // Sync route
    $router->post('/api/radius/sync', [$radiusController, 'syncBviInterfaces'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));
    
    // Global secret management
    $router->get('/api/radius/global-secret', [$radiusController, 'getGlobalSecret'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));
    $router->put('/api/radius/global-secret', [$radiusController, 'updateGlobalSecret'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));
    
    // RADIUS servers status and sync
    $router->get('/api/radius/servers/status', [$radiusController, 'getServersStatus'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));
    $router->post('/api/radius/servers/sync', [$radiusController, 'forceSyncServers'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));
    $router->get('/api/radius/servers/config', [$radiusController, 'getServersConfig'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));
    $router->put('/api/radius/servers/config', [$radiusController, 'updateServerConfig'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));
    $router->post('/api/radius/servers/test', [$radiusController, 'testServerConnection'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));

    // Admin Tools - Backup/Import/Export Routes (Admin Only)
    $adminController = new \App\Controllers\Api\AdminController($database);
    
    $router->get('/api/admin/export/kea-config', [$adminController, 'exportKeaConfig'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));
    $router->post('/api/admin/import/kea-config', [$adminController, 'importKeaConfig'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));
    $router->post('/api/admin/import/kea-config/preview', [$adminController, 'previewKeaConfig'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));
    $router->post('/api/admin/import/kea-config/execute', [$adminController, 'executeKeaImport'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));
    $router->get('/api/admin/backup/kea-database', [$adminController, 'backupKeaDatabase'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));
    $router->get('/api/admin/backup/kea-leases', [$adminController, 'backupKeaLeases'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));
    $router->get('/api/admin/export/kea-leases-csv', [$adminController, 'exportKeaLeasesCSV'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));
    $router->get('/api/admin/export/radius-clients', [$adminController, 'exportRadiusClients'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));
    $router->get('/api/admin/backup/radius-database/{type}', [$adminController, 'backupRadiusDatabase'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));
    $router->get('/api/admin/backup/full-system', [$adminController, 'fullSystemBackup'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));
    $router->get('/api/admin/backups/list', [$adminController, 'listBackups'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));
    $router->get('/api/admin/backup/download/{filename}', [$adminController, 'downloadBackup'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));
    $router->delete('/api/admin/backup/delete/{filename}', [$adminController, 'deleteBackup'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));
    $router->post('/api/admin/restore/kea-database', [$adminController, 'restoreKeaDatabase'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));
    $router->post('/api/admin/restore/server-backup/{filename}', [$adminController, 'restoreFromServerBackup'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));
    $router->post('/api/admin/clear-cin-data', [$adminController, 'clearCinData'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));
    $router->post('/api/admin/import-leases', [$adminController, 'importLeases'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));
    
    // RADIUS orphan management routes
    $router->get('/api/admin/radius/check-orphans', [$adminController, 'checkRadiusOrphans'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));
    $router->post('/api/admin/radius/clean-orphans', [$adminController, 'cleanRadiusOrphans'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));
    
    // Kea config viewer routes
    $router->get('/api/admin/kea-config/view', [$adminController, 'viewKeaConfig'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));
    $router->get('/api/admin/kea-config/download-conf', [$adminController, 'downloadKeaConfigConf'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));

    // Web UI Routes with Auth Middleware
    $router->get('/dashboard', function() {
        $dashboardPath = BASE_PATH . '/views/dashboard.php';
        if (!file_exists($dashboardPath)) {
            throw new \Exception("Dashboard file not found at: " . $dashboardPath);
        }
        require $dashboardPath;
    })->middleware(new \App\Middleware\AuthMiddleware($auth));
    

    $router->get('/switches', function() {
        require BASE_PATH . '/views/switches/index.php';
    })->middleware(new \App\Middleware\AuthMiddleware($auth));

    $router->get('/switches/add', function() {
        require BASE_PATH . '/views/switches/add.php';
    })->middleware(new \App\Middleware\AuthMiddleware($auth));

    // Route for displaying the CIN Switch edit form
    $router->get('/switches/edit/{id}', function($id) {
        $_GET['id'] = $id;
        require BASE_PATH . '/views/switches/edit.php';
    })->middleware(new \App\Middleware\AuthMiddleware($auth));

    $router->get('/switches/bvi/list/{switchId}', function($switchId) {
        $_GET['switchId'] = $switchId;
        require BASE_PATH . '/views/switches/bvi/bvilist.php';
    })->middleware(new \App\Middleware\AuthMiddleware($auth));

    $router->get('/switches/{switchId}/bvi', function($switchId) {
        $_GET['switchId'] = $switchId;
        require BASE_PATH . '/views/switches/bvi/bvilist.php';
    })->middleware(new \App\Middleware\AuthMiddleware($auth));

    $router->get('/switches/{switchId}/bvi/add', function($switchId) {
        $_GET['switchId'] = $switchId; // Make sure the view has access to the ID
        $viewPath = BASE_PATH . '/views/switches/bvi/add.php';
        
        if (!file_exists($viewPath)) {
            error_log("BVI add view not found at: " . $viewPath);
            throw new \Exception("View file not found: " . $viewPath);
        }
        
        require $viewPath;
    })->middleware(new \App\Middleware\AuthMiddleware($auth));
    

    // Route for displaying the BVI edit form
    $router->get('/switches/{switchId}/bvi/{bviId}/edit', function($switchId, $bviId) {
        $_GET['switchId'] = $switchId;
        $_GET['bviId'] = $bviId;
        require BASE_PATH . '/views/switches/bvi/edit.php';
    })->middleware(new \App\Middleware\AuthMiddleware($auth));

    // API Keys management page
    $router->get('/api-keys', function() {
        require BASE_PATH . '/views/api-keys/index.php';
    })->middleware(new \App\Middleware\AuthMiddleware($auth));

    // 404 Handler
    $router->setNotFoundHandler(function() {
        header("HTTP/1.0 404 Not Found");
        require BASE_PATH . '/views/errors/404.php';
    });

    // Handle CORS
    $router->handleCORS();

    // Dispatch the router
    $result = $router->dispatch();
    
    // If result is an array (API response), output as JSON
    if (is_array($result)) {
        // Clean output buffer for API responses to prevent HTML errors in JSON
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: application/json');
        echo json_encode($result);
        exit;
    }

} catch (\Exception $e) {
    error_log("Critical error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo "An error occurred. Please check the error logs for more details.";
}
