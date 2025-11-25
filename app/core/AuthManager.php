<?php

require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/../models/Proveedor.php';
require_once __DIR__ . '/../models/UserSessions.php';

class AuthManager
{
    protected const SESSION_KEY = 'auth_user';
    protected const SESSION_META_KEY = 'auth_meta';

    protected static bool $booted = false;
    protected static ?array $user = null;
    protected static ?array $meta = null;
    protected static array $config = [];
    protected static ?UserSessions $sessions = null;

    public static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        self::$config = require __DIR__ . '/../../config/config.php';
        self::startSession();
        self::$user = $_SESSION[self::SESSION_KEY] ?? null;
        $storedMeta = $_SESSION[self::SESSION_META_KEY] ?? [];
        self::$meta = is_array($storedMeta) ? $storedMeta : [];

        if (!self::$user) {
            self::attemptResumeFromCookie();
        }

        if (self::$user) {
            if (!self::validateClientFingerprint()) {
                self::logout();
            } elseif (self::needsRevalidation() && !self::refreshFromDatabase()) {
                self::logout();
            } else {
                $GLOBALS['auth_user'] = self::$user;
            }
        }

        self::$booted = true;
    }

    public static function user(): ?array
    {
        self::boot();
        return self::$user;
    }

    public static function check(): bool
    {
        self::boot();
        return self::$user !== null;
    }

    public static function requireAuth(bool $redirect = true): bool
    {
        self::boot();

        if (self::$user !== null) {
            return true;
        }

        if ($redirect) {
            self::redirectToLogin();
        }

        return false;
    }

    public static function login(array $user, bool $remember = false): void
    {
        self::boot();

        $userId = (int)($user['id'] ?? 0);
        if ($userId <= 0) {
            throw new RuntimeException('Usuario invalido para autenticacion.');
        }

        $record = self::fetchFullUserRecord($userId);
        if (!$record || empty($record['is_active'])) {
            throw new RuntimeException('No se pudo crear la sesion para el usuario especificado.');
        }

        $sessionUser = (new Proveedor())->hydrateSessionUser($record);
        self::persistSession($sessionUser, $record);

        if ($remember) {
            self::issueRememberCookie($userId);
        }
    }

    public static function logout(): void
    {
        self::boot();

        if (!empty(self::$meta['remember_selector_hash'])) {
            try {
                self::sessions()->revokeBySelectorHash(self::$meta['remember_selector_hash']);
            } catch (\Throwable $exception) {
                error_log('[AuthManager] Error revocando sesion persistente: ' . $exception->getMessage());
            }
        }

        self::clearRememberCookie();

        unset($_SESSION[self::SESSION_KEY], $_SESSION[self::SESSION_META_KEY]);
        self::$user = null;
        self::$meta = [];
        $GLOBALS['auth_user'] = null;

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

    }

    public static function redirectToLogin(): void
    {
        $basePath = rtrim(self::$config['base_path'] ?? self::$config['base_url'] ?? '', '/');
        $target = $basePath !== '' ? $basePath . '/login' : '/login';
        header('Location: ' . $target);
        exit;
    }

    protected static function startSession(): void
    {
        $sessionName = self::$config['session']['name'] ?? self::$config['session_name'] ?? null;
        if ($sessionName && session_status() === PHP_SESSION_NONE) {
            session_name($sessionName);
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    protected static function persistSession(array $sessionUser, array $record): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        self::$user = $sessionUser;
        self::$meta = [
            'fingerprint' => self::buildFingerprint($record),
            'last_check' => time(),
            'client_hash' => self::clientFingerprint(),
        ];

        $_SESSION[self::SESSION_KEY] = self::$user;
        $_SESSION[self::SESSION_META_KEY] = self::$meta;
        $GLOBALS['auth_user'] = self::$user;
    }

    protected static function refreshFromDatabase(): bool
    {
        if (empty(self::$user['id'])) {
            return false;
        }

        $record = self::fetchFullUserRecord((int)self::$user['id']);
        if (!$record || empty($record['is_active'])) {
            return false;
        }

        $fingerprint = self::buildFingerprint($record);
        if (!isset(self::$meta['fingerprint']) || !hash_equals(self::$meta['fingerprint'], $fingerprint)) {
            return false;
        }

        $sessionUser = (new Proveedor())->hydrateSessionUser($record);
        self::updateCachedUser($sessionUser);
        self::$meta['last_check'] = time();
        $_SESSION[self::SESSION_META_KEY] = self::$meta;

        return true;
    }

    protected static function updateCachedUser(array $sessionUser): void
    {
        self::$user = $sessionUser;
        $_SESSION[self::SESSION_KEY] = self::$user;
        $GLOBALS['auth_user'] = self::$user;
    }

    protected static function needsRevalidation(): bool
    {
        $ttl = (int)(self::$config['auth']['session_check_ttl'] ?? 0);
        if ($ttl <= 0) {
            return true;
        }

        $last = (int)(self::$meta['last_check'] ?? 0);
        return (time() - $last) >= $ttl;
    }

    protected static function validateClientFingerprint(): bool
    {
        $stored = self::$meta['client_hash'] ?? null;
        if ($stored === null) {
            return true;
        }

        return hash_equals($stored, self::clientFingerprint());
    }

    protected static function clientFingerprint(): string
    {
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $ip = self::getClientIp();
        return hash('sha256', $agent . '|' . $ip);
    }

    protected static function getClientIp(): string
    {
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($forwarded !== '') {
            $pieces = explode(',', $forwarded);
            return trim($pieces[0]);
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    protected static function buildFingerprint(array $record): string
    {
        $pieces = [
            (int)($record['id'] ?? 0),
            $record['password_hash'] ?? '',
            $record['password_plain'] ?? '',
            (int)!empty($record['must_change_password']),
            (int)!empty($record['is_active']),
            $record['last_password_reset'] ?? '',
            $record['updated_at'] ?? '',
        ];

        return hash('sha256', implode('|', $pieces));
    }

    protected static function issueRememberCookie(int $userId): void
    {
        try {
            $selector = bin2hex(random_bytes(9));
            $validator = bin2hex(random_bytes(32));
        } catch (\Throwable $exception) {
            error_log('[AuthManager] Error generando token remember-me: ' . $exception->getMessage());
            return;
        }

        $selectorHash = hash('sha256', $selector);
        $validatorHash = hash('sha256', $validator);
        $expiresAt = time() + self::rememberLifetime();

        try {
            self::sessions()->createPersistent(
                $userId,
                $selectorHash,
                $validatorHash,
                self::getClientIp(),
                $_SERVER['HTTP_USER_AGENT'] ?? '',
                $expiresAt
            );
        } catch (\Throwable $exception) {
            error_log('[AuthManager] Error guardando token remember-me: ' . $exception->getMessage());
            return;
        }

        self::setRememberCookie($selector . ':' . $validator, $expiresAt);
        self::$meta['remember_selector_hash'] = $selectorHash;
        $_SESSION[self::SESSION_META_KEY] = self::$meta;
    }

    protected static function attemptResumeFromCookie(): void
    {
        $cookie = $_COOKIE[self::rememberCookieName()] ?? null;
        if (!$cookie || !is_string($cookie)) {
            return;
        }

        $parts = explode(':', $cookie);
        if (count($parts) !== 2) {
            self::clearRememberCookie();
            return;
        }

        [$selector, $validator] = $parts;
        if ($selector === '' || $validator === '') {
            self::clearRememberCookie();
            return;
        }

        $selectorHash = hash('sha256', $selector);

        try {
            $session = self::sessions()->findActiveBySelectorHash($selectorHash);
        } catch (\Throwable $exception) {
            error_log('[AuthManager] Error consultando sesion persistente: ' . $exception->getMessage());
            self::clearRememberCookie();
            return;
        }

        if (!$session) {
            self::clearRememberCookie();
            return;
        }

        $expected = $session['validator_hash'] ?? '';
        if (!is_string($expected) || !hash_equals($expected, hash('sha256', $validator))) {
            self::sessions()->revoke((int)$session['id']);
            self::clearRememberCookie();
            return;
        }

        $record = self::fetchFullUserRecord((int)$session['user_id']);
        if (!$record || empty($record['is_active'])) {
            self::sessions()->revoke((int)$session['id']);
            self::clearRememberCookie();
            return;
        }

        $sessionUser = (new Proveedor())->hydrateSessionUser($record);
        self::persistSession($sessionUser, $record);
        self::$meta['remember_selector_hash'] = $selectorHash;
        $_SESSION[self::SESSION_META_KEY] = self::$meta;

        try {
            self::sessions()->touch((int)$session['id']);
        } catch (\Throwable $exception) {
            error_log('[AuthManager] Error actualizando ultimo acceso: ' . $exception->getMessage());
        }
    }

    protected static function rememberCookieName(): string
    {
        $authConfig = self::$config['auth'] ?? [];
        return $authConfig['remember_cookie'] ?? 'proveedores_remember';
    }

    protected static function rememberLifetime(): int
    {
        $authConfig = self::$config['auth'] ?? [];
        $lifetime = (int)($authConfig['remember_lifetime_seconds'] ?? (60 * 60 * 24 * 15));
        return max($lifetime, 3600);
    }

    protected static function setRememberCookie(string $value, int $expires): void
    {
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $params = session_get_cookie_params();
        $path = $params['path'] ?? '/';
        $domain = $params['domain'] ?? '';

        setcookie(
            self::rememberCookieName(),
            $value,
            [
                'expires' => $expires,
                'path' => $path,
                'domain' => $domain ?: '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }

    protected static function clearRememberCookie(): void
    {
        $params = session_get_cookie_params();
        $path = $params['path'] ?? '/';
        $domain = $params['domain'] ?? '';
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        setcookie(
            self::rememberCookieName(),
            '',
            [
                'expires' => time() - 3600,
                'path' => $path,
                'domain' => $domain ?: '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]
        );
    }

    protected static function fetchFullUserRecord(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $model = new Proveedor();
        return $model->findByIdWithRoles($userId);
    }

    protected static function sessions(): UserSessions
    {
        if (self::$sessions === null) {
            self::$sessions = new UserSessions();
        }

        return self::$sessions;
    }
}
