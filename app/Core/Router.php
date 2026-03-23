<?php

namespace App\Core;

class Router {
    private $routes = [];

    public function add($method, $path, $handler) {
        $path = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[a-zA-Z0-9_\-:]+)', $path);
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => '#^' . $path . '$#',
            'handler' => $handler
        ];
    }

    public function dispatch() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Handle subdirectory if any (e.g. /telegram/public/...)
        $scriptName = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        if ($scriptName !== '/' && $scriptName !== '.' && strpos($path, $scriptName) === 0) {
            $path = substr($path, strlen($scriptName));
        }
        $path = '/' . trim($path, '/');

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && preg_match($route['path'], $path, $matches)) {
                $handler = $route['handler'];
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                if (is_array($handler)) {
                    [$controller, $action] = $handler;
                    $controllerInstance = new $controller();
                    $controllerInstance->$action($params);
                } else {
                    $handler($params);
                }
                return;
            }
        }

        http_response_code(404);
        echo "404 Not Found";
    }
}
