<?php

require_once __DIR__ . '/../core/BaseController.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/AuthManager.php';
require_once __DIR__ . '/../models/Proveedor.php';

class LoginController extends BaseController
{
    protected array $config;
    protected string $baseUrl;
    protected string $basePath;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../../config/config.php';
        $this->baseUrl = rtrim($this->config['base_url'] ?? '', '/');
        $configuredPath = $this->config['base_path'] ?? null;
        if ($configuredPath !== null && $configuredPath !== '') {
            $this->basePath = rtrim($configuredPath, '/');
        } else {
            $this->basePath = $this->baseUrl;
        }

        AuthManager::boot();
    }

    public function index(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $isAjax = $this->isAjaxRequest();

        if ($method === 'POST') {
            $this->handleLogin($isAjax);
            return;
        }

        if ($this->isAuthenticated() && !$isAjax) {
            $target = $this->basePath !== '' ? $this->basePath . '/' : '/';
            header('Location: ' . $target);
            exit;
        }

        $error = $_SESSION['auth_error'] ?? null;
        $status = $_SESSION['auth_status'] ?? null;
        unset($_SESSION['auth_error'], $_SESSION['auth_status']);

        $assets = $this->config['assets'] ?? [];
        $this->render('login/index', [
            'title' => 'Acceso Proveedores',
            'error' => $error,
            'status' => $status,
            'baseUrl' => $this->baseUrl,
            'basePath' => $this->basePath,
            'contact' => $this->config['contact'] ?? [],
            'assets' => $assets,
        ], 'auth');
    }

    public function logout(): void
    {
        AuthManager::logout();
        $_SESSION['auth_status'] = 'Sesion finalizada correctamente.';

        $target = $this->basePath !== '' ? $this->basePath . '/login' : '/login';
        header('Location: ' . $target);
        exit;
    }

    protected function handleLogin(bool $isAjax): void
    {
        $username = trim($_POST['username'] ?? $_POST['user'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        $remember = isset($_POST['remember_me']) && in_array((string)$_POST['remember_me'], ['1', 'on', 'true'], true);

        if ($username === '' || $password === '') {
            $message = 'Debes capturar usuario y contrasena.';
            if ($isAjax) {
                $this->jsonResponse(['success' => false, 'message' => $message], 422);
            }
            $_SESSION['auth_error'] = $message;
            $this->redirectToLogin();
        }

        $model = new Proveedor();
        $user = $model->login($username, $password);

        if ($user) {
            AuthManager::login($user, $remember);
            $model->recordLogin($user['id'], $this->getIpAddress(), $_SERVER['HTTP_USER_AGENT'] ?? '');

            if ($isAjax) {
                $this->jsonResponse([
                    'success' => true,
                    'redirect' => $this->basePath !== '' ? $this->basePath . '/' : '/',
                ]);
            }

            $target = $this->basePath !== '' ? $this->basePath . '/' : '/';
            header('Location: ' . $target);
            exit;
        }

        $message = 'Credenciales incorrectas o usuario inactivo.';
        if ($isAjax) {
            $this->jsonResponse(['success' => false, 'message' => $message], 401);
        }

        $_SESSION['auth_error'] = $message;
        $this->redirectToLogin();
    }

    protected function redirectToLogin(): void
    {
        $target = $this->basePath !== '' ? $this->basePath . '/login' : '/login';
        header('Location: ' . $target);
        exit;
    }

    protected function isAjaxRequest(): bool
    {
        $requestedWith = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
        $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
        return $requestedWith === 'xmlhttprequest'
            || $requestedWith === 'fetch'
            || str_contains($accept, 'application/json');
    }

    protected function jsonResponse(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }

    protected function isAuthenticated(): bool
    {
        return AuthManager::check();
    }

    protected function getIpAddress(): string
    {
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($forwarded !== '') {
            $pieces = explode(',', $forwarded);
            return trim($pieces[0]);
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
}
