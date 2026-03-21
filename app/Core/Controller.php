<?php

namespace App\Core;

class Controller {
    protected function render($view, $data = []) {
        extract($data);
        $viewPath = __DIR__ . '/../Modules/' . str_replace('.', '/', $view) . '.php';
        
        if (file_exists($viewPath)) {
            require $viewPath;
        } else {
            die("View $view not found at $viewPath");
        }
    }

    protected function json($data, $status = 200) {
        header('Content-Type: application/json');
        http_response_code($status);
        echo json_encode($data);
    }

    protected function redirect($url) {
        $base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
        if ($base !== '/' && $base !== '.' && strpos($url, 'http') !== 0) {
            $url = $base . '/' . ltrim($url, '/');
        }
        header("Location: $url");
        exit;
    }
}
