<?php
$pageTitle = isset($title) ? (string)$title : 'Dashboard';
$basePath = isset($basePath) ? rtrim((string)$basePath, '/') : '';
$themeBase = ($basePath !== '' ? $basePath : '') . '/assets/theme';
$themeAssets = $themeBase;
$appAssets = $assets ?? [];
$userData = $user ?? [];
$userName = $userData['username'] ?? 'Invitado';
$userRoles = $userData['roles'] ?? [];
$rolesLabel = !empty($userRoles) ? implode(', ', $userRoles) : 'Sin roles';
$menus = $menus ?? [];
$contact = $contact ?? [];
$supportEmail = $contact['support_email'] ?? '';
$supportPhone = $contact['support_phone'] ?? '';
$companyName = $contact['company'] ?? 'Superpapelera';
$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$normalizedCurrent = $currentPath;
if ($basePath !== '' && strpos($normalizedCurrent, $basePath) === 0) {
    $normalizedCurrent = substr($normalizedCurrent, strlen($basePath));
    if ($normalizedCurrent === false) {
        $normalizedCurrent = '/';
    }
}
$normalizedCurrent = $normalizedCurrent === '' ? '/' : $normalizedCurrent;

$renderMenu = static function (array $items, string $basePath, string $current) use (&$renderMenu): string {
    if (empty($items)) {
        return '';
    }

    $html = '';
    foreach ($items as $item) {
        $hasChildren = !empty($item['children']);
        $route = $item['route'] ?? '#';
        $href = $route === '#' ? '#' : ($basePath !== '' ? $basePath . $route : $route);
        $isActive = $route !== '#' && ($current === $route || $current === rtrim($route, '/'));
        $itemClasses = ['menu-item'];
        if ($hasChildren) {
            $itemClasses[] = 'menu-accordion';
        }
        if ($isActive) {
            $itemClasses[] = 'here';
            $itemClasses[] = 'show';
        }

        $html .= '<div class="' . implode(' ', $itemClasses) . '"';
        if ($hasChildren) {
            $html .= ' data-kt-menu-trigger="click"';
        }
        $html .= '>';

        $iconHtml = '<span class="menu-icon"><i class="' . htmlspecialchars($item['icon'] ?? 'fa-solid fa-circle', ENT_QUOTES, 'UTF-8') . ' fs-3"></i></span>';
        $titleHtml = '<span class="menu-title">' . htmlspecialchars($item['label'] ?? 'Menu', ENT_QUOTES, 'UTF-8') . '</span>';
        if ($hasChildren) {
            $html .= '<span class="menu-link">';
            $html .= $iconHtml . $titleHtml . '<span class="menu-arrow"></span>';
            $html .= '</span>';
        } else {
            $html .= '<a class="menu-link' . ($isActive ? ' active' : '') . '" href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '">';
            $html .= $iconHtml . $titleHtml;
            $html .= '</a>';
        }

        if ($hasChildren) {
            $html .= '<div class="menu-sub menu-sub-accordion">';
            foreach ($item['children'] as $child) {
                $childRoute = $child['route'] ?? '#';
                $childHref = $childRoute === '#' ? '#' : ($basePath !== '' ? $basePath . $childRoute : $childRoute);
                $childActive = $childRoute !== '#' && ($current === $childRoute || $current === rtrim($childRoute, '/'));
                $childClasses = 'menu-item' . ($childActive ? ' here' : '');
                $html .= '<div class="' . $childClasses . '">';
                $html .= '<a class="menu-link" href="' . htmlspecialchars($childHref, ENT_QUOTES, 'UTF-8') . '">';
                $html .= '<span class="menu-bullet"><span class="bullet bullet-dot"></span></span>';
                $html .= '<span class="menu-title">' . htmlspecialchars($child['label'] ?? 'Submenu', ENT_QUOTES, 'UTF-8') . '</span>';
                $html .= '</a>';
                if (!empty($child['children'])) {
                    $html .= '<div class="menu-sub menu-sub-accordion menu-active-bg">';
                    $html .= $renderMenu($child['children'], $basePath, $current);
                    $html .= '</div>';
                }
                $html .= '</div>';
            }
            $html .= '</div>';
        }

        $html .= '</div>';
    }

    return $html;
};
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> | <?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Poppins:300,400,500,600,700">
    <link rel="stylesheet" href="<?= htmlspecialchars($themeAssets . '/plugins/global/plugins.bundle.css', ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars($themeAssets . '/css/style.bundle.css', ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(($basePath !== '' ? $basePath : '') . '/assets/plugins/fontawesome/css/all.min.css', ENT_QUOTES, 'UTF-8') ?>">
</head>

<body id="kt_body" class="header-fixed header-tablet-and-mobile-fixed toolbar-enabled toolbar-fixed toolbar-tablet-and-mobile-fixed aside-enabled aside-fixed" style="--kt-toolbar-height:55px;--kt-toolbar-height-tablet-and-mobile:55px">
    <div class="d-flex flex-column flex-root">
        <div class="page d-flex flex-row flex-column-fluid">
            <div id="kt_aside" class="aside aside-dark aside-hoverable" data-kt-drawer="true" data-kt-drawer-name="aside" data-kt-drawer-activate="{default: true, lg: false}" data-kt-drawer-overlay="true" data-kt-drawer-width="{default:'200px', '300px': '250px'}" data-kt-drawer-direction="start" data-kt-drawer-toggle="#kt_aside_mobile_toggle">
                <div class="aside-logo flex-column-auto" id="kt_aside_logo">
                    <a href="<?= htmlspecialchars($basePath !== '' ? $basePath . '/home' : '/home', ENT_QUOTES, 'UTF-8') ?>">
                        <img alt="Logo" src="<?= htmlspecialchars($themeAssets . '/media/logos/logo-demo13.svg', ENT_QUOTES, 'UTF-8') ?>" class="h-20px logo">
                    </a>
                    <div id="kt_aside_toggle" class="btn btn-icon w-auto px-0 btn-active-color-primary aside-toggle" data-kt-toggle="true" data-kt-toggle-state="active" data-kt-toggle-target="body" data-kt-toggle-name="aside-minimize">
                        <span class="svg-icon svg-icon-1 rotate-180">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none">
                                <path d="M11.2657 11.4343L15.45 7.25C15.8642 6.83579 15.8642 6.16421 15.45 5.75C15.0358 5.33579 14.3642 5.33579 13.95 5.75L8.40712 11.2929C8.01659 11.6834 8.01659 12.3166 8.40712 12.7071L13.95 18.25C14.3642 18.6642 15.0358 18.6642 15.45 18.25C15.8642 17.8358 15.8642 17.1642 15.45 16.75L11.2657 12.5657C10.9533 12.2533 10.9533 11.7467 11.2657 11.4343Z" fill="black"></path>
                            </svg>
                        </span>
                    </div>
                </div>
                <div class="aside-menu flex-column-fluid">
                    <div class="hover-scroll-overlay-y my-2 py-5 py-lg-8" id="kt_aside_menu_wrapper" data-kt-scroll="true" data-kt-scroll-activate="{default: false, lg: true}" data-kt-scroll-height="auto" data-kt-scroll-dependencies="#kt_aside_logo, #kt_aside_footer" data-kt-scroll-wrappers="#kt_aside_menu" data-kt-scroll-offset="0">
                        <div class="menu menu-column menu-title-gray-800 menu-state-title-primary menu-state-icon-primary menu-state-bullet-primary menu-arrow-gray-500" id="#kt_aside_menu" data-kt-menu="true">
                            <?php if (!empty($menus)): ?>
                                <?= $renderMenu($menus, $basePath, $normalizedCurrent) ?>
                            <?php else: ?>
                                <div class="menu-item px-4">
                                    <span class="text-muted small">Sin menus asignados</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="aside-footer flex-column-auto py-4 px-4" id="kt_aside_footer">
                    <?php if ($supportEmail !== ''): ?>
                        <a href="mailto:<?= htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-light-primary w-100 mb-2">
                            <i class="fa-solid fa-life-ring me-2"></i> Soporte
                        </a>
                    <?php endif; ?>
                    <?php if ($supportPhone !== ''): ?>
                        <a href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', $supportPhone), ENT_QUOTES, 'UTF-8') ?>" class="btn btn-light w-100">
                            <i class="fa-solid fa-phone me-2"></i> <?= htmlspecialchars($supportPhone, ENT_QUOTES, 'UTF-8') ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="wrapper d-flex flex-column flex-row-fluid" id="kt_wrapper">
                <div id="kt_header" class="header align-items-stretch">
                    <div class="header-brand d-flex align-items-center">
                        <button type="button" class="btn btn-icon btn-active-color-primary me-3 d-lg-none" id="kt_aside_mobile_toggle" aria-label="Menú">
                            <i class="fa-solid fa-bars fs-2"></i>
                        </button>
                        <a href="#" class="brand-link d-flex align-items-center">
                            <span class="fs-5 fw-bold text-primary">Proveedor Nova Hub</span>
                        </a>
                    </div>
                    <div class="d-flex align-items-center flex-grow-1">
                        <div class="d-flex align-items-center w-100 justify-content-end pe-5">
                            <div class="d-flex align-items-center">
                                <div class="d-flex flex-column text-end me-3">
                                    <span class="fw-semibold text-dark"><?= htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') ?></span>
                                    <span class="text-muted fs-8"><?= htmlspecialchars($rolesLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                                <a href="<?= htmlspecialchars($basePath !== '' ? $basePath . '/logout' : '/logout', ENT_QUOTES, 'UTF-8') ?>" class="btn btn-sm btn-light-danger">
                                    <i class="fa-solid fa-power-off me-1"></i> Salir
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="toolbar py-4" id="kt_toolbar">
                    <div id="kt_toolbar_container" class="container-fluid d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center me-3">
                            <h1 class="d-flex text-dark fw-bolder fs-3 align-items-center my-1"><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                        </div>
                        <div class="d-flex align-items-center gap-3">
                            <button type="button" class="btn btn-sm btn-light-primary">
                                <i class="fa-solid fa-sliders me-2"></i> Filtros
                            </button>
                            <button type="button" class="btn btn-sm btn-light">
                                <i class="fa-solid fa-file-export me-2"></i> Exportar
                            </button>
                        </div>
                    </div>
                </div>
                <div class="content d-flex flex-column flex-column-fluid" id="kt_content">
                    <div class="container-fluid">
                        <?= $content ?>
                    </div>
                </div>
                <div class="footer py-4 d-flex flex-lg-column" id="kt_footer">
                    <div class="container-fluid d-flex flex-column flex-md-row align-items-center justify-content-between">
                        <div class="text-dark order-2 order-md-1">
                            <span class="text-muted fw-semibold me-1">© <?= htmlspecialchars((string)($contact['year'] ?? date('Y')), ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="text-gray-800 text-hover-primary"><?= htmlspecialchars($companyName, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <ul class="menu menu-gray-600 menu-hover-primary fw-semibold order-1">
                            <?php if ($supportEmail !== ''): ?>
                                <li class="menu-item">
                                    <a href="mailto:<?= htmlspecialchars($supportEmail, ENT_QUOTES, 'UTF-8') ?>" class="menu-link px-2">Soporte</a>
                                </li>
                            <?php endif; ?>
                            <?php if ($supportPhone !== ''): ?>
                                <li class="menu-item">
                                    <a href="tel:<?= htmlspecialchars(preg_replace('/[^0-9+]/', '', $supportPhone), ENT_QUOTES, 'UTF-8') ?>" class="menu-link px-2">Contacto</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="<?= htmlspecialchars($themeAssets . '/plugins/global/plugins.bundle.js', ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars(($basePath !== '' ? $basePath : '') . '/assets/plugins/apexcharts/apexcharts.min.js', ENT_QUOTES, 'UTF-8') ?>"></script>
    <script src="<?= htmlspecialchars($themeAssets . '/js/scripts.bundle.js', ENT_QUOTES, 'UTF-8') ?>"></script>
</body>

</html>
