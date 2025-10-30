<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($title) ? $title : 'Página Pública' ?></title>
    <link rel="stylesheet" href="/proveedores/config/colors.css">
    <?php if (!empty($css)) foreach ($css as $c) echo "<link rel='stylesheet' href='/proveedores/assets/css/$c'>\n"; ?>
    <style>
        body {
            background: #f5f5f5;
            margin: 0;
        }

        .container {
            max-width: 800px;
            margin: 40px auto;
            background: #fff;
            border-radius: 10px;
            padding: 32px;
        }
    </style>
</head>

<body>
    <?= $content ?>
    <footer style="margin-top:40px;text-align:center;color:#888;">
        &copy; <?= date('Y') ?> Mi Empresa | contacto@tucorreo.com | WhatsApp: +52 123 456 7890
    </footer>
    <?php if (!empty($js)) foreach ($js as $j) echo "<script src='/proveedores/assets/js/$j'></script>\n"; ?>
</body>

</html>