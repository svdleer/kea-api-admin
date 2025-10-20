<?php

namespace App\Controllers\Api;

use App\Models\User;
use App\Auth\Authentication;

class UserController {
    private User $userModel;
    private Authentication $auth;

    public function __construct(User $userModel, Authentication $auth) {
        $this->userModel = $userModel;
        $this->auth = $auth;
    }

    public function list() {
        try {
            // Debug point 1
            error_log("Starting list method in UserController");
    
            $users = $this->userModel->getAllUsers();
            
            // Debug point 2
            error_log("Users from database: " . print_r($users, true));
    
            // Set proper headers
            header('Content-Type: application/json');
            
            // Create response array
            $response = ['users' => $users];
            
            // Debug point 3
            error_log("Response before encoding: " . print_r($response, true));
            
            // Encode and output
            $jsonResponse = json_encode($response);
            
            // Debug point 4
            error_log("Final JSON response: " . $jsonResponse);
            
            // Make sure to actually output the response
            echo $jsonResponse;
            exit; // Add explicit exit to prevent any additional output
            
        } catch (\Exception $e) {
            // Debug point 5
            error_log("Error in list method: " . $e->getMessage());
            
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['error' => 'Failed to fetch users']);
            exit;
        }
    }
    

    public function create() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
    
            if (!isset($data['username']) || !isset($data['email']) || !isset($data['password'])) {
                http_response_code(400);
                echo json_encode(['error' => 'Missing required fields']);
                exit;
            }
    
            $isAdmin = isset($data['is_admin']) ? (bool)$data['is_admin'] : false;
            
            $userId = $this->userModel->createUser(
                $data['username'],
                $data['email'],
                $data['password'],
                $isAdmin
            );
    
            if ($userId) {
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'success',
                    'id' => $userId, 
                    'message' => 'User created successfully'
                ]);
                exit;
            }
    
            http_response_code(500);
            echo json_encode(['error' => 'Failed to create user']);
            exit;
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Server error']);
            exit;
        }
    }
    

    public function update($userId) {
        try {
            error_log("Starting update method for user ID: " . $userId);
            
            $data = json_decode(file_get_contents('php://input'), true);
            error_log("Received update data: " . print_r($data, true));
            
            if (empty($data)) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'No data provided'
                ]);
                exit;
            }
    
            $success = $this->userModel->updateUser($userId, $data);
            
            header('Content-Type: application/json');
            if ($success) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'User updated successfully'
                ]);
            } else {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Failed to update user'
                ]);
            }
            exit;
    
        } catch (\Exception $e) {
            error_log("Error in update method: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Server error'
            ]);
            exit;
        }
    }
    

    public function delete($userId) {
        try {
            error_log("Starting delete method for user ID: " . $userId);
    
            // First check if this is an admin user
            $user = $this->userModel->getUserById($userId);
            if (!$user) {
                http_response_code(404);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'User not found'
                ]);
                exit;
            }
    
            // If user is admin, check if they're the last admin
            if ($user['is_admin']) {
                $allUsers = $this->userModel->getAllUsers();
                $adminUsers = array_filter($allUsers, function($user) {
                    return $user['is_admin'] == 1;
                });
    
                if (count($adminUsers) <= 1) {
                    http_response_code(400);
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Cannot delete the last admin user'
                    ]);
                    exit;
                }
            }
    
            // Proceed with deletion if all checks pass
            if ($this->userModel->deleteUser($userId)) {
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'success',
                    'message' => 'User deleted successfully'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Failed to delete user'
                ]);
            }
            exit;
    
        } catch (\Exception $e) {
            error_log("Error in delete method: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Server error'
            ]);
            exit;
        }
    }
    

    public function getById($userId) {
        try {
            error_log("Starting getById method for user ID: " . $userId);
            
            $user = $this->userModel->getUserById($userId);
            
            if (!$user) {
                http_response_code(404);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'User not found'
                ]);
                exit;
            }
    
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'user' => $user
            ]);
            exit;
    
        } catch (\Exception $e) {
            error_log("Error in getById method: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Server error'
            ]);
            exit;
        }
    }

    public function checkUsername($username) {
        try {
            $exists = $this->userModel->usernameExists($username);
            echo json_encode(['available' => !$exists]);
            exit;
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Server error']);
            exit;
        }
    }
    
    public function checkEmail($email) {
        try {
            $exists = $this->userModel->emailExists($email);
            echo json_encode(['available' => !$exists]);
            exit;
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Server error']);
            exit;
        }
    }
    
    
}
