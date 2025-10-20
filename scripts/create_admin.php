<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Database\Database;

$db = Database::getInstance();

$username = 'admin';
$email = 'silvester.vanderleer@vodafoneziggo.com';
$password = password_hash('admin123', PASSWORD_DEFAULT);

$stmt = $db->prepare("INSERT INTO users (username, email, password, is_admin) VALUES (?, ?, ?, true)");
$stmt->execute([$username, $email, $password]);

echo "Admin user created successfully!\n";
