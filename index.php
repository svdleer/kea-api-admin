<?php

define('BASE_PATH', __DIR__);

require_once BASE_PATH . '/vendor/autoload.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
// No direct fix is applicable. The line should be removed or commented out in a production environment.

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(BASE_PATH);
$dotenv->load();

// Initialize session
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true
    ]);
}

// Initialize router
$router = new App\Router();

// Debug log
error_log("Request URI: " . $_SERVER['REQUEST_URI']);
error_log("Request Method: " . $_SERVER['REQUEST_METHOD']);

// Public routes (no auth required)
$router->get('/', function() {
    require BASE_PATH . '/views/login.php';
});

$router->post('/login', function() {
    $auth = new \App\Auth\Authentication(\App\Database\Database::getInstance());
    
    error_log("Login attempt - POST data: " . print_r($_POST, true));
    
    try {
        if (empty($_POST['username']) || empty($_POST['password'])) {
            throw new \Exception('Username and password are required');
        }

        if ($auth->login($_POST['username'], $_POST['password'])) {
            error_log("Login successful - redirecting to dashboard");
            header('Location: /dashboard');
            return;
        } else {
            error_log("Login failed - invalid credentials");
            $_SESSION['error'] = 'Invalid username or password';
            header('Location: /');
            return;
        }
    } catch (\Exception $e) {
        error_log("Login error: " . $e->getMessage());
        $_SESSION['error'] = $e->getMessage();
        header('Location: /');
        return;
    }
});

$router->get('/logout', function() {
    $auth = new \App\Auth\Authentication(\App\Database\Database::getInstance());
    $auth->logout();
    header('Location: /');
    throw new \Exception('Logout completed'); // import Exception
});

// Check authentication for all other routes
$auth = new \App\Auth\Authentication(\App\Database\Database::getInstance());
if (!$auth->isLoggedIn() && $_SERVER['REQUEST_URI'] !== '/' && $_SERVER['REQUEST_URI'] !== '/login' && $_SERVER['REQUEST_URI'] !== '/logout') {
    header('Location: /');
    throw new Exception('User not logged in and accessing restricted page'); // Exception handling instead of exit
}

// Protected routes (auth required)

// API Routes for Switches
$router->get('/api/switches/check-exists', function() {
    $controller = new \App\Controllers\Api\SwitchController();
    $controller->checkExists();
});


$router->get('/api/switches/check-ipv6', function() {
    $controller = new \App\Controllers\Api\SwitchController();
    $controller->checkIpv6();
});

$router->get('/api/switches', function() {
    $controller = new \App\Controllers\Api\SwitchController();
    $controller->getAll();
});

$router->post('/api/switches', function() {
    $controller = new \App\Controllers\Api\SwitchController();
    $controller->create();
});

$router->get('/api/switches/{id}', function($id) {
    $controller = new \App\Controllers\Api\SwitchController();
    $controller->getById($id);
});

$router->put('/api/switches/{id}', function($id) {
    $controller = new \App\Controllers\Api\SwitchController();
    $controller->update($id);
});

$router->delete('/api/switches/{id}', function($id) {
    $controller = new \App\Controllers\Api\SwitchController();
    $controller->delete($id);
});



// BVI Interface API Routes
$router->get('/api/switches/{id}/bvi', function($id) {
    $controller = new \App\Controllers\Api\BVIController();
    $controller->index($id);
});

$router->put('/api/switches/{id}/bvi', function($id) {
    $controller = new \App\Controllers\Api\BVIController();
    $controller->create($id);
});

$router->get('/api/switches/{id}/bvi/check-exists', function($id) {
    $controller = new \App\Controllers\Api\BVIController();
    $controller->checkBVIExists($id);
});

$router->post('/api/switches/{id}/bvi/{bviId}', function($id, $bviId) {
    $controller = new \App\Controllers\Api\BVIController();
    $controller->update($id, $bviId);
});


$router->get('/api/switches/{id}/bvi/{bviId}', function($id, $bviId) {
    $controller = new \App\Controllers\Api\BVIController();
    $controller->show($id, $bviId);
});



$router->delete('/api/switches/{id}/bvi/{bviId}', function($id, $bviId) {
    $controller = new \App\Controllers\Api\BVIController();
    $controller->delete($id, $bviId);
});


// Dashboard Web Route
$router->get('/dashboard', function() {
    require BASE_PATH . '/views/dashboard.php';
});

// Switch Web Routes
$router->get('/switches', function() {
    require BASE_PATH . '/views/switches/index.php';
});

// Route for displaying the CIN Switch add form

$router->get('/switches/add', function() {
    require BASE_PATH . '/views/switches/add.php';
});

// Route for displaying the CIN Switch edit form
$router->get('/switches/edit/{id}', function($id) {
    $_GET['id'] = $id;
    require BASE_PATH . '/views/switches/edit.php';
});


// Switch BVI Routes
// Route for displaying BVIs CIN swtich 

$router->get('/switches/bvi/list', function() {
    if (!isset($_GET['switchId'])) {
        header('Location: /switches');
        return;
    }
    require BASE_PATH . '/views/switches/bvi/bvilist.php';
});

// Route for displaying BVIs of a CIN swtich 
$router->get('/switches/{id}/bvi', function($id) {
    $_GET['id'] = $id;
    require BASE_PATH . '/views/switches/bvi/index.php';
});

// Route for displaying the BVI add form
$router->get('/switches/{id}/bvi/add', function($id) {
    $_GET['id'] = $id;
    require BASE_PATH . '/views/switches/bvi/add.php';
});

// Route for displaying the BVI edit form
$router->get('/switches/{switchId}/bvi/{bviId}/edit', function($switchId, $bviId) {
    $_GET['switchId'] = $switchId;
    $_GET['bviId'] = $bviId;
    require BASE_PATH . '/views/switches/bvi/edit.php';
});





// 404 Handler
$router->setNotFoundHandler(function() {
    error_log("404 - Page not found: " . $_SERVER['REQUEST_URI']);
    http_response_code(404);
    require BASE_PATH . '/views/errors/404.php';
});

// Handle CORS for API requests
$router->handleCORS();

// Dispatch the router
try {
    $router->dispatch();
} catch (\Exception $e) {
    error_log("Router error: " . $e->getMessage());
    http_response_code(500);
    require BASE_PATH . '/views/errors/500.php';
}
