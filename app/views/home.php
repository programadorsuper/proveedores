<?php
session_start();
$proveedor = $_SESSION['proveedor'];
require_once __DIR__ . '/../models/Proveedor.php';
$model = new Proveedor();
$menus = $model->getMenus($proveedor['id'], $proveedor['rol'], $proveedor['admin']);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Proveedores</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="/proveedores_mvc/config/colors.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.1/css/all.min.css" integrity="sha512-2SwdPD6INVrV/lHTZbO2nodKhrnDdJK9/kg2XD1r9uGqPo1cUbujc+IYdlYdEErWNu69gVcYgdxlmVmzTWnetw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
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
                    <a class="nav-link" href="/proveedores/public/login/logout"><i class="fas fa-sign-out-alt"></i> Salir</a>
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
                        <?php foreach ($menus as $menu): ?>
                            <li class="nav-item">
                                <a href="<?= htmlspecialchars($menu['url']) ?>" class="nav-link">
                                    <i class="nav-icon <?= htmlspecialchars($menu['icono']) ?>"></i>
                                    <p><?= htmlspecialchars($menu['nombre']) ?></p>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </nav>
            </div>
        </aside>
        <!-- Content Wrapper. Contains page content -->
        <div class="content-wrapper">
            <section class="content pt-4">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-12">
                            <h2>Bienvenido, <?= htmlspecialchars($proveedor['user']) ?></h2>
                            <p>Este es tu dashboard de proveedor. Aquí puedes ver tus ventas, compras y más.</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">Ventas</div>
                                <div class="card-body">
                                    <canvas id="ventasChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">Compras</div>
                                <div class="card-body">
                                    <canvas id="comprasChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </div>
    <footer style="position:fixed;bottom:0;left:0;width:100vw;background:rgba(0,0,0,0.7);color:#fff;padding:8px 0;text-align:center;z-index:1000;font-size:1rem;">
        📧 contacto@tucorreo.com | 📱 WhatsApp: +52 123 456 7890
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/plugins/jquery/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        const ventasChart = new Chart(document.getElementById('ventasChart'), {
            type: 'bar',
            data: {
                labels: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun'],
                datasets: [{
                    label: 'Ventas',
                    data: [12, 19, 3, 5, 2, 3],
                    backgroundColor: 'var(--main-color)'
                }]
            }
        });
        const comprasChart = new Chart(document.getElementById('comprasChart'), {
            type: 'line',
            data: {
                labels: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun'],
                datasets: [{
                    label: 'Compras',
                    data: [7, 11, 5, 8, 3, 7],
                    backgroundColor: 'var(--accent-color)',
                    borderColor: 'var(--main-color)',
                    fill: false
                }]
            }
        });
    </script>
</body>

</html>