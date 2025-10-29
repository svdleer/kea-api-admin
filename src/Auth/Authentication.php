<?php

namespace App\Auth;

use PDO;

class Authentication
{
    private $db;
    private const HASH_ALGO = PASSWORD_DEFAULT;
    private const MIN_PASSWORD_LENGTH = 8;
    private const SESSION_LIFETIME = 1800; // 30 minutes

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Attempt to login a user
     */
    public function login(string $username, string $password): bool
    {
        try {
            // error_log("=== Starting login attempt for user: " . $username . " ===");
            // error_log("Current Session ID: " . session_id());
            
            $stmt = $this->db->prepare(
                "SELECT id, username, password, is_admin, email 
                 FROM users 
                 WHERE username = :username"
            );
            
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // error_log("Database query executed. User found: " . ($user ? "Yes" : "No"));
            
            if (!$user) {
                // error_log("No user found with username: " . $username);
                return false;
            }

            // error_log("Verifying password for user: " . $username);
            
            if (password_verify($password, $user['password'])) {
                // error_log("Password verification successful");
                
                // Clear any existing session data
                $_SESSION = array();
                
                // Regenerate session ID
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_regenerate_id(true);
                }
                
                // error_log("New Session ID: " . session_id());

                // Store user data in session
                $_SESSION = [
                    'user_id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'is_admin' => $user['is_admin'],
                    'last_activity' => time(),
                    'logged_in' => true,
                    'ip_address' => $_SERVER['REMOTE_ADDR'],
                    'user_agent' => $_SERVER['HTTP_USER_AGENT']
                ];
                
                
                // Update last login timestamp
                $this->updateLastLogin($user['id']);
                
                // error_log("=== Login successful for user: " . $username . " ===");
                return true;
            }
            
            // error_log("Password verification failed for user: " . $username);
            return false;
        } catch (\PDOException $e) {
            error_log("Database error during login: " . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if user is logged in
     */
    public function isLoggedIn(): bool
    {
        // error_log("=== Checking login status ===");
        // error_log("Session status: " . session_status());
        // error_log("Session ID: " . session_id());
    
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            // error_log("No valid session data found");
            return false;
        }
    
        // Verify session integrity
        if ($_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR'] || 
            $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
            // error_log("Session integrity check failed");
            $this->logout();
            return false;
        }
    
        // Check session timeout
        if (time() - $_SESSION['last_activity'] > self::SESSION_LIFETIME) {
            // error_log("Session timed out. Current time: " . time() . ", Last activity: " . $_SESSION['last_activity']);
            $this->logout();
            return false;
        }
    
        // Update last activity time
        $_SESSION['last_activity'] = time();
        // error_log("Session is valid, user is logged in");
        return true;
    }

    /**
     * Log out the current user
     */
    public function logout(): void
    {
        // error_log("=== Logging out user ===");
        
        // Unset all session variables
        $_SESSION = array();

        // Destroy the session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }

        // Destroy the session
        session_destroy();
        // error_log("Session destroyed");
    }

    /**
     * Check if current user is admin
     */
    public function isAdmin(): bool
    {
        // error_log('User ID in session: ' . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'not set'));
        // error_log('Is admin in session: ' . (isset($_SESSION['is_admin']) ? $_SESSION['is_admin'] : 'not set'));
        
        if (isset($_SESSION['user_id'])) {
            try {
                $stmt = $this->db->prepare("SELECT is_admin FROM users WHERE id = :user_id");
                $stmt->execute(['user_id' => $_SESSION['user_id']]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                return isset($result['is_admin']) && $result['is_admin'] == true;
            } catch (\PDOException $e) {
                error_log('Database error in isAdmin: ' . $e->getMessage());
                return false;
            }
        }
        
        return false;
    }

    /**
     * Get current user ID
     */
    public function getCurrentUserId(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Get current username
     */
    public function getCurrentUsername(): ?string
    {
        return $_SESSION['username'] ?? null;
    }

    /**
     * Get current user email
     */
    public function getCurrentUserEmail(): ?string
    {
        return $_SESSION['email'] ?? null;
    }

    /**
     * Create a new user
     */
    public function createUser(string $username, string $email, string $password, bool $isAdmin = false): bool
    {
        try {
            if (!$this->validatePassword($password)) {
                throw new \Exception("Password does not meet requirements");
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \Exception("Invalid email format");
            }

            $hashedPassword = password_hash($password, self::HASH_ALGO);

            $stmt = $this->db->prepare(
                "INSERT INTO users (username, email, password, is_admin) 
                 VALUES (:username, :email, :password, :is_admin)"
            );

            return $stmt->execute([
                'username' => $username,
                'email' => $email,
                'password' => $hashedPassword,
                'is_admin' => $isAdmin
            ]);
        } catch (\PDOException $e) {
            error_log("User creation error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update user's password
     */
    public function updatePassword(int $userId, string $newPassword): bool
    {
        try {
            if (!$this->validatePassword($newPassword)) {
                return false;
            }

            $hashedPassword = password_hash($newPassword, self::HASH_ALGO);
            
            $stmt = $this->db->prepare(
                "UPDATE users 
                 SET password = :password 
                 WHERE id = :id"
            );

            return $stmt->execute([
                'password' => $hashedPassword,
                'id' => $userId
            ]);
        } catch (\PDOException $e) {
            error_log("Password update error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate password requirements
     */
    private function validatePassword(string $password): bool
    {
        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            return false;
        }

        // Check for at least one uppercase letter
        if (!preg_match('/[A-Z]/', $password)) {
            return false;
        }

        // Check for at least one lowercase letter
        if (!preg_match('/[a-z]/', $password)) {
            return false;
        }

        // Check for at least one number
        if (!preg_match('/[0-9]/', $password)) {
            return false;
        }

        return true;
    }

    /**
     * Update last login timestamp
     */
    private function updateLastLogin(int $userId): void
    {
        try {
            $stmt = $this->db->prepare(
                "UPDATE users 
                 SET last_login = CURRENT_TIMESTAMP 
                 WHERE id = :id"
            );
            
            $stmt->execute(['id' => $userId]);
        } catch (\PDOException $e) {
            error_log("Update last login error: " . $e->getMessage());
        }
    }

    /**
     * Check if username exists
     */
    public function usernameExists(string $username): bool
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) 
                 FROM users 
                 WHERE username = :username"
            );
            
            $stmt->execute(['username' => $username]);
            return (bool) $stmt->fetchColumn();
        } catch (\PDOException $e) {
            error_log("Username check error: " . $e->getMessage());
            return false;
        }
    }
}
