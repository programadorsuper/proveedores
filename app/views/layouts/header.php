<?php if (!isset($base)) {
    $config = require __DIR__ . '/../../../config/config.php';
    $base = rtrim($config['base_url'], '/');
} ?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Proveedores</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="/proveedores/config/colors.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: var(--main-gradient);
        }

        .content-wrapper {
            background: var(--card-bg);
        }
    </style>
</head>

<body class="hold-transition sidebar-mini">
    <div class="wrapper">
        <!-- Navbar -->
        <nav class="main-header navbar navbar-expand navbar-white navbar-light">
            <ul class="navbar-nav ml-auto">
                <li class="nav-item">
                    <a class="nav-link" href="<?= $base ?>/login/logout"><i class="fas fa-sign-out-alt"></i> Salir</a>
                </li>
            </ul>
        </nav>
        <!-- Sidebar -->
        <aside class="main-sidebar sidebar-dark-primary elevation-4" style="background:var(--sidebar-bg);">
            <a href="#" class="brand-link">
                <span class="brand-text font-weight-light">Proveedores</span>
            </a>
            <div class="sidebar">
                <nav class="mt-2">
                    <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
                        <!-- Aquí puedes incluir el menú dinámico si lo necesitas -->
                    </ul>
                </nav>
            </div>
        </aside>
        <div class="content-wrapper">