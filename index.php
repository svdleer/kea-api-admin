<?php

define('BASE_PATH', __DIR__);

require_once BASE_PATH . '/vendor/autoload.php';

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
    $auth = new \App\Auth\Authentication(\App\Database\Database::getInstance());
    $database = \App\Database\Database::getInstance();
    $apiKeyModel = new App\Models\ApiKey($database);

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
    
    

    $router->get('/logout', function() use ($auth) {
        $auth->logout();
        header('Location: /');
        exit();
    });

    // Check authentication for protected routes
    $publicRoutes = ['/', '/login', '/logout'];
    if (!$auth->isLoggedIn() && !in_array($_SERVER['REQUEST_URI'], $publicRoutes)) {
        error_log("Unauthorized access attempt to: " . $_SERVER['REQUEST_URI']);
        header('Location: /');
        exit();
    }

    // API Key Management Routes
    $router->get('/api/keys', [new \App\Controllers\Api\ApiKeyController($apiKeyModel, $auth), 'list'])
        ->middleware(new \App\Middleware\AuthMiddleware($auth));
    $router->post('/api/keys', [new \App\Controllers\Api\ApiKeyController($apiKeyModel, $auth), 'create'])
        ->middleware(new \App\Middleware\AuthMiddleware($auth));
$router->post('/api/keys/{id}/deactivate', [new \App\Controllers\Api\ApiKeyController($apiKeyModel, $auth), 'deactivate'])
        ->middleware(new \App\Middleware\AuthMiddleware($auth));

    // CIN Switch Routes - Read Only with Combined Auth
    $router->get('/api/switches', [\App\Controllers\Api\CinSwitch::class, 'getAll'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));
    $router->get('/api/switches/{id}', [\App\Controllers\Api\CinSwitch::class, 'getById'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));

    // CIN Switch Routes - Read/Write with Combined Auth
    $router->post('/api/switches', [\App\Controllers\Api\CinSwitch::class, 'create'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));
    $router->put('/api/switches/{id}', [\App\Controllers\Api\CinSwitch::class, 'update'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));
    $router->delete('/api/switches/{id}', [\App\Controllers\Api\CinSwitch::class, 'delete'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel, true));

    // Validation Check Routes with Combined Auth
    $router->get('/api/switches/check-exists', [\App\Controllers\Api\CinSwitch::class, 'hostnameExists'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));
    $router->get('/api/switches/check-bvi', [\App\Controllers\Api\CinSwitch::class, 'checkBvi'])
        ->middleware(new \App\Middleware\CombinedAuthMiddleware($auth, $apiKeyModel));
    $router->get('/api/switches/check-ipv6', [\App\Controllers\Api\CinSwitch::class, 'checkIpv6'])
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
    $router->dispatch();

} catch (\Exception $e) {
    error_log("Critical error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo "An error occurred. Please check the error logs for more details.";
}
