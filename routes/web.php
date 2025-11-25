<?php

$config = require __DIR__ . '/../config/config.php';
$basePath = rtrim($config['base_path'] ?? $config['base_url'] ?? '', '/');
$prefix = $basePath !== '' ? $basePath : '';

$router->add($prefix !== '' ? $prefix : '/', 'HomeController@index', 'GET', true);
$router->add($prefix . '/home', 'HomeController@index', 'GET', true);
$router->add($prefix . '/home/stats', 'HomeController@stats', 'POST', true);
$router->add($prefix . '/kpis', 'KpisController@index', 'GET', true);
$router->add($prefix . '/login', 'LoginController@index', 'GET', false);
$router->add($prefix . '/login', 'LoginController@index', 'POST', false);
$router->add($prefix . '/logout', 'LoginController@logout', 'GET', true);

$router->add($prefix . '/info', 'InfoController@publico', 'GET', false);

// Ventas
$router->add($prefix . '/ventas', 'SalesController@index', 'GET', true);
$router->add($prefix . '/ventas/periodos', 'SalesController@periods', 'GET', true);
$router->add($prefix . '/ventas/sellout', 'SalesController@sellout', 'GET', true);
$router->add($prefix . '/ventas/sellinout', 'SalesController@sellInOut', 'GET', true);
$router->add($prefix . '/ventas/comparativos', 'SalesController@sellInOut', 'GET', true);

// Compras
$router->add($prefix . '/compras', 'PurchasesController@index', 'GET', true);
$router->add($prefix . '/compras/periodos', 'PurchasesController@periods', 'GET', true);
$router->add($prefix . '/compras/sellin', 'PurchasesController@sellin', 'GET', true);

// Inventario
$router->add($prefix . '/inventario', 'InventoryController@index', 'GET', true);
$router->add($prefix . '/inventario/cobertura', 'InventoryController@cover', 'GET', true);
$router->add($prefix . '/inventario/quiebres', 'InventoryController@breaks', 'GET', true);

// Rotaciones
$router->add($prefix . '/rotaciones', 'RotationsController@index', 'GET', true);
$router->add($prefix . '/rotaciones/turnover', 'RotationsController@turnover', 'GET', true);

// Tickets
$router->add($prefix . '/tickets', 'TicketsController@index', 'GET', true);
$router->add($prefix . '/tickets/buscar', 'TicketsController@search', 'POST', true);
$router->add($prefix . '/tickets/detalle', 'TicketsController@detail', 'GET', true);
$router->add($prefix . '/tickets/review', 'TicketsController@markReviewed', 'POST', true);
$router->add($prefix . '/tickets/puntos', 'TicketsController@addPoints', 'POST', true);
$router->add($prefix . '/tickets/descargar', 'TicketsController@download', 'GET', true);

// Ordenes
$router->add($prefix . '/ordenes', 'OrdersController@index', 'GET', true);
$router->add($prefix . '/ordenes/nuevas', 'OrdersController@nuevas', 'GET', true);
$router->add($prefix . '/ordenes/backorder', 'OrdersController@backorder', 'GET', true);
$router->add($prefix . '/ordenes/entradas', 'OrdersController@entradas', 'GET', true);

$router->add($prefix . '/exportaciones', 'ExportsController@index', 'GET', true);
$router->add($prefix . '/exportaciones/generar', 'ExportsController@create', 'POST', true);

$router->add($prefix . '/proveedores', 'ProvidersController@index', 'GET', true);

// Otros modulos
$router->add($prefix . '/otros', 'OthersController@index', 'GET', true);
$router->add($prefix . '/otros/devoluciones', 'OthersController@devoluciones', 'GET', true);
$router->add($prefix . '/otros/inventario', 'OthersController@inventario', 'GET', true);

// Usuarios y reportes
$router->add($prefix . '/usuarios', 'UsersController@index', 'GET', true);
$router->add($prefix . '/reportes', 'ReportsController@index', 'GET', true);
