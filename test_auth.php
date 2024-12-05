<?php
// test_auth.php
require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use App\Auth\Authentication;
use App\Database\Database;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$db = Database::getInstance();
$auth = new Authentication($db);

// Test username and password
$username = 'admin'; // replace with your actual username
$password = 'Admin123!'; // replace with your actual password

// Test database connection
echo "Testing database connection...\n";
try {
    $stmt = $db->query("SELECT 1");
    echo "Database connection successful!\n\n";
} catch (Exception $e) {
    echo "Database connection failed: " . $e->getMessage() . "\n\n";
}

// Test if user exists
echo "Testing if user exists...\n";
if ($auth->usernameExists($username)) {
    echo "User '$username' exists in database\n\n";
} else {
    echo "User '$username' does not exist in database\n\n";
}

// Test direct database query
echo "Testing direct user query...\n";
$stmt = $db->prepare("SELECT id, username, password, is_admin FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user) {
    echo "User found in database:\n";
    echo "ID: " . $user['id'] . "\n";
    echo "Username: " . $user['username'] . "\n";
    echo "Is Admin: " . ($user['is_admin'] ? 'Yes' : 'No') . "\n";
    echo "Password hash exists: " . (!empty($user['password']) ? 'Yes' : 'No') . "\n\n";
} else {
    echo "No user found in direct database query\n\n";
}

// Test login
echo "Testing login...\n";
if ($auth->login($username, $password)) {
    echo "Login successful!\n";
    echo "Session data:\n";
    print_r($_SESSION);
} else {
    echo "Login failed!\n";
}

// Print session information
echo "\nSession Information:\n";
echo "Session Status: " . session_status() . "\n";
echo "Session ID: " . session_id() . "\n";
echo "Session Data:\n";
print_r($_SESSION);
