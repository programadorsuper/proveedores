<?php

class Auth
{
    public static function check(): void
    {
        $config = require __DIR__ . '/../../config/config.php';

        $sessionName = $config['session']['name'] ?? $config['session_name'] ?? null;
        if ($sessionName && session_status() === PHP_SESSION_NONE) {
            session_name($sessionName);
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['auth_user'])) {
            $basePath = rtrim($config['base_path'] ?? $config['base_url'] ?? '', '/');
            $loginTarget = $basePath !== '' ? $basePath . '/login' : '/login';
            header('Location: ' . $loginTarget);
            exit;
        }
    }
}
