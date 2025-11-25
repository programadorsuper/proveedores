<?php

/**
 * Configuración de conexiones a bases de datos para el ecosistema Proveedores.
 *
 * - pgsql: esquema `proveedores` alojado en Postgres (usuarios, roles, etc).
 * - firebird: conexión legacy requerida por algunos reportes.
 *
 * Crea un archivo `config/database.local.php` para sobreescribir credenciales sin
 * exponerlas en el repositorio.
 */

$config = [
    'default' => getenv('DB_CONNECTION') ?: 'pgsql',

    'connections' => [

        // -------------------------------
        //  CONEXIÓN PRINCIPAL: PostgreSQL
        // -------------------------------
        'pgsql' => [
            'driver'   => 'pgsql',
            'host'     => getenv('DB_HOST')     ?: '192.168.1.239',
            'port'     => getenv('DB_PORT')     ?: '5432',
            'database' => getenv('DB_DATABASE') ?: 'kensei',
            'username' => getenv('DB_USERNAME') ?: 'datos',
            'password' => (getenv('DB_PASSWORD') !== false) ? getenv('DB_PASSWORD') : 'V9r#T7p$Xz2!LmQ8',

            'schema'       => getenv('DB_SCHEMA') ?: 'proveedores',
            'search_path'  => getenv('DB_SEARCH_PATH') ?: 'proveedores,public',

            'sslmode'         => getenv('DB_PGSSL_MODE') ?: 'disable',
            'sslcert'         => getenv('DB_PGSSL_CERT') ?: null,
            'sslkey'          => getenv('DB_PGSSL_KEY') ?: null,
            'sslrootcert'     => getenv('DB_PGSSL_ROOTCERT') ?: null,

            'app_name'        => getenv('APP_NAME') ?: 'proveedores_mvc',
            'connect_timeout' => (int)(getenv('DB_CONNECT_TIMEOUT') ?: 5),

            'options' => [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT            => (int)(getenv('DB_CONNECT_TIMEOUT') ?: 5),
            ],
        ],

        // ------------------------------------------------
        //  CONEXIÓN FIREBIRD (SIN getenv, ESTILO UNIFORME)
        // ------------------------------------------------
        'firebird' => [
            'driver'   => 'firebird',

            // Datos directos
            'host'     => '192.168.1.15',
            'port'     => '3050',
            'database' => '/home/papelera/papelera.fdb',
            'charset'  => 'UTF8',

            'username' => 'SYSDBA',
            'password' => 'masterkey',

            // Si tu Database.php arma el DSN automáticamente,
            // estos campos son suficientes.
            // Si usa DSN fijo, te paso la versión DSN abajo.

            'options' => [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_PERSISTENT         => false,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],

            'init'    => [],
            'prewarm' => false,
        ],
    ],
];

/**
 * Permite sobrescribir credenciales sin versionar:
 * Crea `config/database.local.php` con un array parcial, ej.:
 * return ['connections' => ['pgsql' => ['host' => '10.10.100.10']]];
 */
$localOverride = __DIR__ . '/database.local.php';
if (is_file($localOverride)) {
    $localConfig = require $localOverride;
    if (is_array($localConfig)) {
        $config = array_replace_recursive($config, $localConfig);
    }
}

return $config;
