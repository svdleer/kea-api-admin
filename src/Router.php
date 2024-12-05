<?php

namespace App;

class Router
{
    private $routes = [];
    private $notFoundHandler;
    private $basePath = '';

    public function setBasePath($basePath)
    {
        $this->basePath = $basePath;
    }

    public function get($path, $handler)
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post($path, $handler)
    {
        $this->addRoute('POST', $path, $handler);
    }

    public function put($path, $handler)
    {
        $this->addRoute('PUT', $path, $handler);
    }

    public function delete($path, $handler)
    {
        $this->addRoute('DELETE', $path, $handler);
    }

    private function addRoute($method, $path, $handler)
    {
        // Remove trailing slashes for consistency
        $path = rtrim($path, '/');
        
        // If path is empty, set it to /
        if (empty($path)) {
            $path = '/';
        }

        $this->routes[] = [
            'method' => $method,
            'path' => $path,
            'handler' => $handler
        ];
    }

    public function setNotFoundHandler($handler)
    {
        $this->notFoundHandler = $handler;
    }

    private function getUri()
    {
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri = rawurldecode($uri);
        return '/' . trim(substr($uri, strlen($this->basePath)), '/');
    }

    private function getRequestMethod()
    {
        $method = $_SERVER['REQUEST_METHOD'];

        // Handle PUT and DELETE methods from forms using _method parameter
        if ($method === 'POST') {
            if (isset($_POST['_method'])) {
                $method = strtoupper($_POST['_method']);
            } elseif (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
                $method = strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
            }
        }

        return $method;
    }

    private function convertRouteToRegex($route)
    {
        return '#^' . preg_replace('/\{([a-zA-Z0-9_]+)\}/', '([^/]+)', $route) . '$#';
    }

    public function dispatch()
    {
        $uri = $this->getUri();
        $method = $this->getRequestMethod();

        error_log("Dispatching: $method $uri");

        foreach ($this->routes as $route) {
            $pattern = $this->convertRouteToRegex($route['path']);
            error_log("Checking pattern: $pattern against URI: $uri");

            if (preg_match($pattern, $uri, $matches) && $route['method'] === $method) {
                error_log("Route matched! Extracting parameters...");
                array_shift($matches); // Remove the full match
                
                // Extract named parameters
                $params = [];
                if (preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $route['path'], $paramNames)) {
                    $paramNames = $paramNames[1];
                    foreach ($paramNames as $index => $name) {
                        $params[] = $matches[$index] ?? null;
                    }
                }

                error_log("Calling handler with parameters: " . print_r($params, true));
                
                try {
                    return call_user_func_array($route['handler'], $params);
                } catch (\Exception $e) {
                    error_log("Error in route handler: " . $e->getMessage());
                    throw $e;
                }
            }
        }

        // No route found
        error_log("No route found for: $method $uri");
        if (is_callable($this->notFoundHandler) && is_string($this->notFoundHandler)) {
            return call_user_func($this->notFoundHandler);
        } else {
            header("HTTP/1.0 404 Not Found");
            echo "404 Not Found";
        }
    }

    public function handleCORS()
    {
        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            header('Access-Control-Allow-Origin: https://trusted-origin.com');
            header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
            header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
            return;
        }

        // Handle actual requests
        header('Access-Control-Allow-Origin: https://trusted-origin.com');
    }
}
