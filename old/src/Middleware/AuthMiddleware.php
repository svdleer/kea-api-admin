<?php
namespace App\Middleware;

class AuthMiddleware
{
    private $auth;

    public function __construct($auth)
    {
        $this->auth = $auth;
    }

    public function handle()
    {
        error_log("AuthMiddleware: Checking authentication");
        if (!$this->auth->isLoggedIn()) {
            error_log("AuthMiddleware: Not logged in, redirecting to login");
            header('Location: /');
            exit();
        }
        error_log("AuthMiddleware: Authentication successful");
        return true;
    }
}
