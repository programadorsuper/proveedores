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
        'pgsql' => [
            'driver'   => 'pgsql',
            'host'     => getenv('DB_HOST')     ?: '192.168.1.239',
            'port'     => getenv('DB_PORT')     ?: '5432',
            'database' => getenv('DB_DATABASE') ?: 'kensei',
            'username' => getenv('DB_USERNAME') ?: 'datos',
            'password' => (getenv('DB_PASSWORD') !== false) ? getenv('DB_PASSWORD') : 'V9r#T7p$Xz2!LmQ8',
            'schema'       => getenv('DB_SCHEMA') ?: 'proveedores',
            'search_path'  => getenv('DB_SEARCH_PATH') ?: 'proveedores,public',
            'sslmode'         => getenv('DB_PGSSL_MODE') ?: 'disable',  // disable | require | prefer | verify-ca | verify-full
            'sslcert'         => getenv('DB_PGSSL_CERT') ?: null,
            'sslkey'          => getenv('DB_PGSSL_KEY') ?: null,
            'sslrootcert'     => getenv('DB_PGSSL_ROOTCERT') ?: null,
            'app_name'        => getenv('APP_NAME') ?: 'proveedores_mvc',
            'connect_timeout' => (int)(getenv('DB_CONNECT_TIMEOUT') ?: 5),
            'options' => [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT            => (int)(getenv('DB_CONNECT_TIMEOUT') ?: 5),
                // PDO::ATTR_PERSISTENT => true, // opcional
            ],
        ],

        'firebird' => [
            'driver'   => 'firebird',
            'host'     => getenv('FB_HOST')     ?: '127.0.0.1',
            'port'     => getenv('FB_PORT')     ?: '3050',
            'database' => getenv('FB_DATABASE') ?: 'C:\path\to\legacy.fdb',
            'username' => getenv('FB_USERNAME') ?: 'SYSDBA',
            'password' => (getenv('FB_PASSWORD') !== false) ? getenv('FB_PASSWORD') : 'masterkey',
            'charset'  => getenv('FB_CHARSET')  ?: 'UTF8',
            'role'     => getenv('FB_ROLE')     ?: null,
            'dialect'  => (int)(getenv('FB_DIALECT') ?: 3),
            'options'  => [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ],
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
