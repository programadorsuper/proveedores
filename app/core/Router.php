<?php

require_once __DIR__ . '/AuthManager.php';

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
        if ($uri === '') {
            $uri = '/';
        }

        foreach ($this->routes as $route) {
            $routeUri = $route['uri'];
            if ($basePath !== '' && strpos($routeUri, $basePath) === 0) {
                $routeUri = substr($routeUri, strlen($basePath)) ?: '';
            }
            $routeUri = '/' . ltrim($routeUri, '/');
            $routeUri = rtrim($routeUri, '/');
            if ($routeUri === '') {
                $routeUri = '/';
            }

            if ($route['method'] !== $requestMethod) {
                continue;
            }

            $pattern = $this->buildPattern($routeUri);
            if (preg_match($pattern, $uri, $matches)) {
                [$controller, $action] = explode('@', $route['action']);
                require_once __DIR__ . '/../controllers/' . $controller . '.php';
                $controllerObj = new $controller();

                if (!empty($route['auth'])) {
                    AuthManager::requireAuth(true);
                }

                $params = $this->extractParams($matches);
                call_user_func_array([$controllerObj, $action], $params);
                return;
            }
        }

        http_response_code(404);
        echo '404 Not Found';
    }

    private function buildPattern(string $routeUri): string
    {
        $routeUri = $routeUri === '' ? '/' : $routeUri;
        $routeUri = '/' . ltrim($routeUri, '/');
        $routeUri = rtrim($routeUri, '/');
        if ($routeUri === '') {
            $routeUri = '/';
        }

        $escaped = preg_quote($routeUri, '#');
        $pattern = preg_replace('#\\\\\{([a-zA-Z0-9_]+)\\\\\}#', '(?P<$1>[^/]+)', $escaped);

        return '#^' . $pattern . '$#';
    }

    private function extractParams(array $matches): array
    {
        if (empty($matches)) {
            return [];
        }

        $params = array_filter(
            $matches,
            static function ($key) {
                return !is_int($key);
            },
            ARRAY_FILTER_USE_KEY
        );

        return array_values($params);
    }
}
