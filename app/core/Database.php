<?php

/**
 * Administra conexiones PDO reutilizables para Postgres y Firebird.
 */
class Database
{
    protected static array $config = [];
    protected static array $connections = [];

    /**
     * Obtiene una conexión PDO, reutilizando instancias existentes.
     */
    public static function connection(?string $name = null): PDO
    {
        self::loadConfig();

        $name = $name ?: (self::$config['default'] ?? 'pgsql');

        if (isset(self::$connections[$name]) && self::$connections[$name] instanceof PDO) {
            return self::$connections[$name];
        }

        $connections = self::$config['connections'] ?? [];

        if (!isset($connections[$name])) {
            throw new RuntimeException("Conexión '{$name}' no configurada en config/database.php");
        }

        $settings = $connections[$name];
        $driver = $settings['driver'] ?? 'pgsql';

        switch ($driver) {
            case 'pgsql':
                $pdo = self::createPostgresConnection($settings);
                break;
            case 'firebird':
                $pdo = self::createFirebirdConnection($settings);
                break;
            default:
                throw new RuntimeException("Driver '{$driver}' no soportado");
        }

        self::$connections[$name] = $pdo;
        return $pdo;
    }

    public static function pgsql(): PDO
    {
        return self::connection('pgsql');
    }

    public static function firebird(): PDO
    {
        return self::connection('firebird');
    }

    /**
     * Crea una conexión PDO a PostgreSQL con soporte SSL y search_path.
     */
    protected static function createPostgresConnection(array $settings): PDO
    {
        $host     = $settings['host']     ?? '127.0.0.1';
        $port     = $settings['port']     ?? '5432';
        $database = $settings['database'] ?? '';
        $username = $settings['username'] ?? '';
        $password = $settings['password'] ?? '';
        $schema   = $settings['schema']   ?? 'public';
        $sslmode  = $settings['sslmode']  ?? 'disable';
        $timeout  = $settings['connect_timeout'] ?? 5;
        $appName  = $settings['app_name'] ?? 'proveedores_mvc';
        $search   = $settings['search_path'] ?? "{$schema}, public";

        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s;connect_timeout=%d',
            $host,
            $port,
            $database,
            $sslmode,
            $timeout
        );

        $options = $settings['options'] ?? [];

        try {
            $pdo = new PDO($dsn, $username, $password, $options);

            // Asignar application_name y search_path
            $safeApp = str_replace("'", "''", $appName);
            $pdo->exec("SET application_name TO '{$safeApp}'");
            $pdo->exec("SET search_path TO {$search}");

            return $pdo;
        } catch (PDOException $exception) {
            $message = sprintf(
                'No fue posible conectar a PostgreSQL (%s:%s / BD:%s / sslmode:%s). Detalle: %s',
                $host,
                $port,
                $database,
                $sslmode,
                $exception->getMessage()
            );
            throw new RuntimeException($message, (int)$exception->getCode(), $exception);
        }
    }

    /**
     * Crea una conexión PDO a Firebird.
     */
    protected static function createFirebirdConnection(array $settings): PDO
    {
        $host     = $settings['host'] ?? '127.0.0.1';
        $port     = $settings['port'] ?? '3050';
        $database = $settings['database'] ?? '';
        $username = $settings['username'] ?? 'SYSDBA';
        $password = $settings['password'] ?? 'masterkey';
        $charset  = $settings['charset'] ?? 'UTF8';
        $role     = $settings['role'] ?? null;
        $dialect  = (int)($settings['dialect'] ?? 3);
        $options  = $settings['options'] ?? [];

        $dsn = sprintf(
            'firebird:dbname=%s/%s:%s;charset=%s',
            $host,
            $port,
            $database,
            $charset
        );

        try {
            $pdo = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $exception) {
            $message = sprintf(
                'No fue posible conectar a Firebird (%s:%s / BD:%s). Detalle: %s',
                $host,
                $port,
                $database,
                $exception->getMessage()
            );
            throw new RuntimeException($message, (int)$exception->getCode(), $exception);
        }

        if ($role) {
            $pdo->exec("SET ROLE {$role}");
        }

        return $pdo;
    }

    /**
     * Carga configuración desde /config/database.php.
     */
    protected static function loadConfig(): void
    {
        if (!empty(self::$config)) {
            return;
        }

        $path = __DIR__ . '/../../config/database.php';
        $config = require $path;

        if (!is_array($config)) {
            throw new RuntimeException("El archivo de configuración '{$path}' debe retornar un array");
        }

        self::$config = $config;
    }
}
