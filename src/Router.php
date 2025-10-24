<?php

namespace App;

class Router
{
    private $routes = [];
    private $notFoundHandler;

    public function get($path, $handler)
    {
        $this->addRoute('GET', $path, $handler);
        return $this;
    }

    public function post($path, $handler)
    {
        $this->addRoute('POST', $path, $handler);
        return $this;
    }

    public function put($path, $handler)
    {
        $this->addRoute('PUT', $path, $handler);
        return $this;
    }

    public function delete($path, $handler)
    {
        $this->addRoute('DELETE', $path, $handler);
        return $this;
    }

    private function addRoute($method, $path, $handler)
    {
        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler
        ];
    }

    public function middleware($middleware)
    {
        $lastRouteIndex = count($this->routes) - 1;
        if ($lastRouteIndex >= 0) {
            $this->routes[$lastRouteIndex]['middleware'] = $middleware;
        }
        return $this;
    }

    private function getPattern($path)
    {
        // Convert route parameters like {id} to regex pattern
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '([^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    private function executeController($handler, $params = [])
    {
        if (is_array($handler)) {
            if (is_string($handler[0])) {
                // Handle different controller types
                $controllerClass = $handler[0];
                $controller = match ($controllerClass) {
                    \App\Controllers\Api\UserController::class => new $controllerClass(
                        new \App\Models\User(\App\Database\Database::getInstance()),
                        new \App\Auth\Authentication(\App\Database\Database::getInstance())
                    ),
                    \App\Controllers\Api\IPv6Controller::class => new $controllerClass(
                        new \App\Models\IPv6Subnet(\App\Database\Database::getInstance()),
                        new \App\Auth\Authentication(\App\Database\Database::getInstance())
                    ),
                    default => new $controllerClass(\App\Database\Database::getInstance())
                };
                
                $method = $handler[1];
                return call_user_func_array([$controller, $method], $params);
            }
            // If controller is already instantiated
            return call_user_func_array($handler, $params);
        }
        return call_user_func_array($handler, $params);
    }
    
    
    

    public function dispatch()
    {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $method = $_SERVER['REQUEST_METHOD'];

        foreach ($this->routes as $route) {
            if ($route['method'] === $method) {
                $pattern = $this->getPattern($route['path']);
                if (preg_match($pattern, $uri, $matches)) {
                    array_shift($matches); // Remove the full match

                    // Handle middleware if present
                    if (isset($route['middleware'])) {
                        $middlewareResult = $route['middleware']->handle();
                        if ($middlewareResult !== true) {
                            return $middlewareResult;
                        }
                    }

                    // Execute the handler with parameters
                    return $this->executeController($route['handler'], $matches);
                }
            }
        }

        // If no route matches, call the 404 handler
        if ($this->notFoundHandler) {
            return call_user_func($this->notFoundHandler);
        }

        // Default 404 response
        header("HTTP/1.0 404 Not Found");
        echo "404 Not Found";
    }

    public function setNotFoundHandler($handler)
    {
        $this->notFoundHandler = $handler;
    }

    public function handleCORS()
    {
        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-API-Key');
            header('Access-Control-Max-Age: 86400'); // 24 hours
            exit(0);
        }

        // Handle actual requests
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-API-Key');
    }
}
