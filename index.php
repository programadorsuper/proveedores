<?php

require_once __DIR__ . '/app/core/Router.php';
require_once __DIR__ . '/app/core/Database.php';

$router = new Router();
require_once __DIR__ . '/routes/web.php';

$config = require __DIR__ . '/config/config.php';
$baseUrl = rtrim($config['base_url'] ?? '', '/');
$basePath = rtrim($config['base_path'] ?? $baseUrl, '/');

$sessionName = $config['session']['name'] ?? $config['session_name'] ?? null;
if ($sessionName && session_status() === PHP_SESSION_NONE) {
    session_name($sessionName);
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uriPath = parse_url($requestUri, PHP_URL_PATH) ?: '/';

$isRootRequest = $uriPath === '/'
    || $uriPath === ''
    || ($basePath !== '' && ($uriPath === $basePath || $uriPath === $basePath . '/'));

if ($isRootRequest) {
    $target = !empty($_SESSION['auth_user'])
        ? ($basePath !== '' ? $basePath . '/home' : '/home')
        : ($basePath !== '' ? $basePath . '/login' : '/login');
    header('Location: ' . $target);
    exit;
}

$router->dispatch($requestUri, $requestMethod);
