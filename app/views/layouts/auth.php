<?php
$assets = $assets ?? [];
$cssBase = rtrim($assets['css'] ?? '/proveedores_mvc/assets/css', '/');
$jsBase  = rtrim($assets['js']  ?? '/proveedores_mvc/assets/js',  '/');
$assetVersion = $contact['year'] ?? date('Y');
$pageTitle = isset($title) ? (string)$title : 'Acceso';
$currentBasePath = $basePath ?? ($baseUrl ?? '');
?>
<!DOCTYPE html>
<html class="dark" lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>

  <!-- Tailwind + Plugins -->
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>

  <!-- Fuentes -->
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700;800&display=swap" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
        crossorigin="anonymous" referrerpolicy="no-referrer">

  <!-- Config Tailwind (colores/tema) -->
  <script id="tailwind-config">
    tailwind.config = {
      darkMode: "class",
      theme: {
        extend: {
          colors: {
            primary: "#1E88E5",
            "primary-deep": "#0D47A1",
            "background-light": "#f6f6f8",
            "background-dark": "#0A192F",
            "text-light": "#E0F2F1",
            "text-dark": "#8892B0",
          },
          fontFamily: {
            display: ["Manrope", "sans-serif"],
          },
          borderRadius: { DEFAULT: "0.5rem", lg: "1rem", xl: "1.5rem", full: "9999px" },
        },
      },
    };
  </script>

  <!-- Estilos propios -->
  <link rel="stylesheet" href="<?= htmlspecialchars($cssBase . '/login.css?v=' . urlencode((string)$assetVersion), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="bg-background-dark">
  <?= $content ?>

  <script>
    window.PROVEEDORES_MVC = {
      basePath: '<?= htmlspecialchars((string)$currentBasePath, ENT_QUOTES, 'UTF-8') ?>'
    };
  </script>
  <script src="<?= htmlspecialchars($jsBase . '/login.js?v=' . urlencode((string)$assetVersion), ENT_QUOTES, 'UTF-8') ?>" defer></script>
</body>
</html>
