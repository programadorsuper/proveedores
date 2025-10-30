<?php

/** @var array $usuarios */
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuarios - Proveedores</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="/proveedores/config/colors.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="hold-transition sidebar-mini">
    <div class="wrapper">
        <div class="content-wrapper p-4">
            <h2>Usuarios</h2>
            <table class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th>Rol</th>
                        <th>Activo</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                        <tr>
                            <td><?= $u['id'] ?></td>
                            <td><?= htmlspecialchars($u['user']) ?></td>
                            <td><?= htmlspecialchars($u['rol']) ?></td>
                            <td><?= $u['activo'] ? 'Sí' : 'No' ?></td>
                            <td>
                                <a href="/proveedores/public/usuarios/permisos/<?= $u['id'] ?>" class="btn btn-sm btn-info"><i class="fas fa-key"></i> Permisos</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <footer style="position:fixed;bottom:0;left:0;width:100vw;background:rgba(0,0,0,0.7);color:#fff;padding:8px 0;text-align:center;z-index:1000;font-size:1rem;">
        📧 contacto@tucorreo.com | 📱 WhatsApp: +52 123 456 7890
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/plugins/jquery/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>

</html>