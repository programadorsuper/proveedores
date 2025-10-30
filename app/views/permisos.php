<?php

/** @var array $menus */
/** @var array $permisos */
/** @var int $usuario_id */
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Permisos de Usuario</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="/proveedores/config/colors.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="hold-transition sidebar-mini">
    <div class="wrapper">
        <div class="content-wrapper p-4">
            <h2>Permisos de Usuario</h2>
            <form action="/proveedores/public/usuarios/guardarPermisos/<?= $usuario_id ?>" method="post">
                <table class="table table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Menú</th>
                            <th>Activo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($menus as $menu): ?>
                            <tr>
                                <td><?= htmlspecialchars($menu['nombre']) ?></td>
                                <td>
                                    <input type="checkbox" name="permisos[<?= $menu['id'] ?>]" value="1" <?= (isset($permisos[$menu['id']]) && $permisos[$menu['id']]) ? 'checked' : '' ?>>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <button type="submit" class="btn btn-success">Guardar Permisos</button>
                <a href="/proveedores/public/usuarios" class="btn btn-secondary">Volver</a>
            </form>
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