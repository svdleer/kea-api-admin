<?php
namespace App\Middleware;

class AuthMiddleware {
    private $auth;

    public function __construct($auth) {
        $this->auth = $auth;
    }

public function handle() {
    if (!$this->auth->isLoggedIn()) {
        header('Location: login.php');
        throw new Exception('User not logged in'); // Requires: use Exception;
    }
}
}