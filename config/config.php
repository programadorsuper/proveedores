<?php
// Configuracion general de la aplicacion MVC de proveedores.
return [
    'app_name' => 'Proveedor Nova Hub',
    'base_url' => '/proveedores_mvc',
    'base_path' => '/proveedores_mvc',
    'default_controller' => 'Home',
    'default_action' => 'index',
    'session_name' => 'proveedores_session',
    'session' => [
        'name' => 'proveedores_session',
    ],
    'auth' => [
        'remember_cookie' => 'proveedores_remember',
        'remember_lifetime_seconds' => 60 * 60 * 24 * 15, // 15 dias
        'session_check_ttl' => 0, // 0 = validar en cada peticion
    ],
    'assets' => [
        'css' => '/proveedores_mvc/assets/css',
        'js' => '/proveedores_mvc/assets/js',
    ],
    'contact' => [
        'support_name' => 'Ing. Ricardo Rivera',
        'support_phone' => '+52 1 55 7957 9866',
        'support_email' => 'sistemas4@superpapelera.com.mx',
        'company' => 'Super Papelera',
        'year' => (int)date('Y'),
    ],
    'color_file' => __DIR__ . '/colors.css',
];
