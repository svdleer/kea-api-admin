<?php

namespace App\Models;

use PDO;
use Exception;

class User {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    public function getAllUsers(): array {
        try {
            error_log("Starting getAllUsers query");
            
            $stmt = $this->db->prepare("SELECT id, username, email, is_admin, active FROM users");
            $stmt->execute();
            
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return $results ?: [];
            
        } catch (\PDOException $e) {
            error_log("Database error in getAllUsers: " . $e->getMessage());
            throw new Exception("Error fetching users: " . $e->getMessage());
        }
    }

    public function getUserById(int $id): ?array {
        try {
            error_log("Getting user by ID: " . $id);
            
            $stmt = $this->db->prepare("SELECT id, username, email, is_admin, active FROM users WHERE id = ?");
            $stmt->execute([$id]);
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("User found: " . ($user ? 'yes' : 'no'));
            
            return $user ?: null;
            
        } catch (\PDOException $e) {
            error_log("Database error in getUserById: " . $e->getMessage());
            throw new Exception("Error fetching user: " . $e->getMessage());
        }
    }

    public function getUserByUsername(string $username): ?array {
        try {
            error_log("Getting user by username: " . $username);
            
            $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("User found: " . ($user ? 'yes' : 'no'));
            
            return $user ?: null;
            
        } catch (\PDOException $e) {
            error_log("Database error in getUserByUsername: " . $e->getMessage());
            throw new Exception("Error fetching user by username: " . $e->getMessage());
        }
    }

    public function getUserByEmail(string $email): ?array {
        try {
            error_log("Getting user by email: " . $email);
            
            $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            error_log("User found: " . ($user ? 'yes' : 'no'));
            
            return $user ?: null;
            
        } catch (\PDOException $e) {
            error_log("Database error in getUserByEmail: " . $e->getMessage());
            throw new Exception("Error fetching user by email: " . $e->getMessage());
        }
    }

    public function createUser(string $username, string $email, string $password, bool $isAdmin = false): int {
        try {
            error_log("Creating new user: " . $username);
            
            $stmt = $this->db->prepare(
                "INSERT INTO users (username, email, password, is_admin, active) 
                 VALUES (?, ?, ?, ?, true)"
            );
            
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $stmt->execute([$username, $email, $hashedPassword, $isAdmin]);
            
            $userId = (int)$this->db->lastInsertId();
            error_log("User created with ID: " . $userId);
            
            return $userId;
            
        } catch (\PDOException $e) {
            error_log("Database error in createUser: " . $e->getMessage());
            throw new Exception("Error creating user: " . $e->getMessage());
        }
    }

    public function updateUser(int $id, array $data): bool {
        try {
            
            $updates = [];
            $values = [];

            foreach(['username', 'email', 'is_admin', 'active'] as $field) {
                if (isset($data[$field])) {
                    $updates[] = "$field = ?";
                    $values[] = $data[$field];
                }
            }

            if (isset($data['password'])) {
                $updates[] = "password = ?";
                $values[] = password_hash($data['password'], PASSWORD_DEFAULT);
            }

            if (empty($updates)) {
                error_log("No fields to update");
                return false;
            }

            $values[] = $id;
            $sql = "UPDATE users SET " . implode(", ", $updates) . " WHERE id = ?";
            
            error_log("Update SQL: " . $sql);
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($values);
            
            error_log("Update result: " . ($result ? 'success' : 'failed'));
            
            return $result;
            
        } catch (\PDOException $e) {
            error_log("Database error in updateUser: " . $e->getMessage());
            throw new Exception("Error updating user: " . $e->getMessage());
        }
    }

    public function deleteUser(int $id): bool {
        try {
            error_log("Deleting user ID: " . $id);
            
            $stmt = $this->db->prepare("DELETE FROM users WHERE id = ?");
            $result = $stmt->execute([$id]);
            
            error_log("Delete result: " . ($result ? 'success' : 'failed'));
            
            return $result;
            
        } catch (\PDOException $e) {
            error_log("Database error in deleteUser: " . $e->getMessage());
            throw new Exception("Error deleting user: " . $e->getMessage());
        }
    }

    public function verifyPassword(string $password, string $hash): bool {
        try {
            error_log("Verifying password");
            return password_verify($password, $hash);
        } catch (Exception $e) {
            error_log("Error in verifyPassword: " . $e->getMessage());
            throw new Exception("Error verifying password: " . $e->getMessage());
        }
    }

    public function changePassword(int $userId, string $newPassword): bool {
        try {
            error_log("Changing password for user ID: " . $userId);
            
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $stmt = $this->db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $result = $stmt->execute([$hashedPassword, $userId]);
            
            error_log("Password change result: " . ($result ? 'success' : 'failed'));
            
            return $result;
            
        } catch (\PDOException $e) {
            error_log("Database error in changePassword: " . $e->getMessage());
            throw new Exception("Error changing password: " . $e->getMessage());
        }
    }

    public function toggleUserStatus(int $userId): bool {
        try {
            error_log("Toggling status for user ID: " . $userId);
            
            $stmt = $this->db->prepare("UPDATE users SET active = NOT active WHERE id = ?");
            $result = $stmt->execute([$userId]);
            
            error_log("Toggle status result: " . ($result ? 'success' : 'failed'));
            
            return $result;
            
        } catch (\PDOException $e) {
            error_log("Database error in toggleUserStatus: " . $e->getMessage());
            throw new Exception("Error toggling user status: " . $e->getMessage());
        }
    }

    public function isLastAdmin(int $userId): bool {
        try {
            error_log("Checking if user ID: " . $userId . " is last admin");
            
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as admin_count 
                FROM users 
                WHERE is_admin = 1
            ");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $user = $this->getUserById($userId);
            
            $isLastAdmin = ($result['admin_count'] <= 1 && $user && $user['is_admin']);
            error_log("Is last admin: " . ($isLastAdmin ? 'yes' : 'no'));
            
            return $isLastAdmin;
            
        } catch (\PDOException $e) {
            error_log("Database error in isLastAdmin: " . $e->getMessage());
            throw new Exception("Error checking admin status: " . $e->getMessage());
        }
    }
    
    public function usernameExists($username) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetchColumn() > 0;
    }
    
    public function emailExists($email) {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetchColumn() > 0;
    }
    


}
