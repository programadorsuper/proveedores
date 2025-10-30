<?php
session_start();
$config = require __DIR__ . '/../../config/config.php';
$base = rtrim($config['base_url'], '/');
$error = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Proveedores</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <link rel="stylesheet" href="./config/colors.css">
    <style>
        body {
            background: var(--main-gradient);
        }

        .login-box {
            margin-top: 8vh;
        }
    </style>
</head>

<body>
    <div class="login-box">
        <div class="card card-outline card-primary">
            <div class="card-header text-center">
                <a href="<?= $base ?>/login" class="h1"><b>Proveedores</b> Login</a>
            </div>
            <div class="card-body">
                <p class="login-box-msg">Inicia sesión para acceder</p>
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <form action="<?= $base ?>/login/auth" method="post">
                    <div class="input-group mb-3">
                        <input type="text" name="user" class="form-control" placeholder="Usuario" required>
                        <div class="input-group-append">
                            <div class="input-group-text"><span class="fas fa-user"></span></div>
                        </div>
                    </div>
                    <div class="input-group mb-3">
                        <input type="password" name="password" class="form-control" placeholder="Contraseña" required>
                        <div class="input-group-append">
                            <div class="input-group-text"><span class="fas fa-lock"></span></div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary btn-block">Entrar</button>
                        </div>
                    </div>
                </form>
            </div>
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