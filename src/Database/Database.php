<?php

namespace App\Database;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private static $instance = null;
    private $connection;

    private function __construct()
    {
        // Load environment variables if not already loaded
        if (!isset($_ENV['DB_HOST'])) {
            $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
            $dotenv->load();
        }

        try {
            $dsn = sprintf(
                "mysql:host=%s;dbname=%s;charset=utf8mb4",
                $_ENV['DB_HOST'],
                $_ENV['DB_NAME']
            );

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false
            ];

            $this->connection = new PDO(
                $dsn,
                $_ENV['DB_USER'],
                $_ENV['DB_PASSWORD'],
                $options
            );
        } catch (PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw new PDOException("Database connection failed: " . $e->getMessage());
        }
    }

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $db = new self();
            self::$instance = $db->connection;
        }
        return self::$instance;
    }

    private function __clone()
    {
    }

    // Changed from private to public
    public function __wakeup()
    {
        throw new RuntimeException("Cannot unserialize singleton");
    }
}
