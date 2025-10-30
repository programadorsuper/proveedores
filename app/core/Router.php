<?php
class Router
{
    private array $routes = [];

    public function get($uri, $action): void
    {
        $this->add('GET', $uri, $action);
    }

    public function post($uri, $action): void
    {
        $this->add('POST', $uri, $action);
    }

    public function add($uri, $action, $method = 'GET', $auth = false): void
    {
        $uri = rtrim($uri, '/');
        $this->routes[] = compact('method', 'uri', 'action', 'auth');
    }

    public function dispatch($requestUri, $requestMethod): void
    {
        $config = require __DIR__ . '/../../config/config.php';
        $baseUrl = rtrim($config['base_url'] ?? '', '/');
        $basePath = rtrim($config['base_path'] ?? $baseUrl, '/');

        $uri = parse_url($requestUri, PHP_URL_PATH) ?: '/';

        if ($basePath !== '' && strpos($uri, $basePath) === 0) {
            $uri = substr($uri, strlen($basePath)) ?: '';
        }

        $uri = '/' . ltrim($uri, '/');
        $uri = rtrim($uri, '/');

        foreach ($this->routes as $route) {
            $routeUri = $route['uri'];
            if ($basePath !== '' && strpos($routeUri, $basePath) === 0) {
                $routeUri = substr($routeUri, strlen($basePath)) ?: '';
            }
            $routeUri = '/' . ltrim($routeUri, '/');
            $routeUri = rtrim($routeUri, '/');

            if ($route['method'] === $requestMethod && $routeUri === $uri) {
                [$controller, $action] = explode('@', $route['action']);
                require_once __DIR__ . '/../controllers/' . $controller . '.php';
                $controllerObj = new $controller();

                if (!empty($route['auth'])) {
                    $sessionName = $config['session']['name'] ?? $config['session_name'] ?? null;
                    if ($sessionName && session_status() === PHP_SESSION_NONE) {
                        session_name($sessionName);
                    }
                    if (session_status() === PHP_SESSION_NONE) {
                        session_start();
                    }
                    if (empty($_SESSION['auth_user'])) {
                        $loginTarget = $basePath !== '' ? $basePath . '/login' : '/login';
                        header('Location: ' . $loginTarget);
                        exit;
                    }
                }

                call_user_func([$controllerObj, $action]);
                return;
            }
        }

        http_response_code(404);
        echo '404 Not Found';
    }
}
