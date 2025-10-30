<?php
$filters = $filters ?? ['period' => 'mtm', 'channel' => 'all', 'category' => 'all', 'provider_id' => null];
$providers = $providers ?? [];
$selectedProviderId = $selectedProviderId ?? ($filters['provider_id'] ?? null);
$summary = $summary ?? [];
$trend = $trend ?? ['categories' => [], 'series' => []];
$orders = $ordersDistribution ?? [];
$topProducts = $topProducts ?? [];
$permissions = $permissions ?? [];
$assets = $assets ?? [];
$basePath = $basePath ?? '';
$dashboardEndpoint = $dashboardEndpoint ?? ($basePath !== '' ? $basePath . '/home/stats' : '/home/stats');

$summaryDefaults = array_merge([
    'sellin' => 0,
    'sellin_growth' => 0,
    'sellout' => 0,
    'sellout_growth' => 0,
    'orders_pending' => 0,
    'orders_fulfilled' => 0,
    'returns' => 0,
    'alerts' => 0,
], $summary);

$formatCurrency = static function ($value): string {
    return '$' . number_format((float)$value, 0, '.', ',');
};
$formatNumber = static function ($value): string {
    return number_format((float)$value, 0, '.', ',');
};
$growthClass = static function ($value): string {
    return ($value ?? 0) >= 0 ? 'badge-light-success' : 'badge-light-danger';
};
$growthIcon = static function ($value): string {
    return ($value ?? 0) >= 0 ? 'fa-arrow-up' : 'fa-arrow-down';
};

$multipleProviders = count($providers) > 1;

$initialPayload = [
    'endpoint' => $dashboardEndpoint,
    'filters' => [
        'provider_id' => (int)($selectedProviderId ?? 0),
        'period' => $filters['period'] ?? 'mtm',
        'channel' => $filters['channel'] ?? 'all',
        'category' => $filters['category'] ?? 'all',
    ],
    'providers' => $providers,
    'summary' => $summaryDefaults,
    'trend' => $trend,
    'orders' => $orders,
    'topProducts' => $topProducts,
];

$jsBase = rtrim($assets['js'] ?? '/proveedores_mvc/assets/js', '/');
?>

<div class="dashboard-wrapper position-relative" data-dashboard="wrapper">
    <div class="dashboard-overlay d-none position-absolute top-0 start-0 w-100 h-100 bg-white bg-opacity-75 d-flex align-items-center justify-content-center" data-dashboard="loader" style="z-index: 50;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Cargando...</span>
        </div>
    </div>

    <div class="row g-5 g-xl-8">
        <div class="col-12">
            <div class="card card-flush border-0 shadow-sm">
                <div class="card-body py-5">
                    <form id="dashboard-filters" class="row g-3 align-items-end" autocomplete="off">
                        <?php if ($multipleProviders): ?>
                            <div class="col-xl-3 col-md-4">
                                <label class="form-label text-muted">Proveedor</label>
                                <select class="form-select" name="provider_id" id="filter-provider">
                                    <?php foreach ($providers as $provider): ?>
                                        <option value="<?= (int)$provider['external_id'] ?>" <?= (int)$provider['external_id'] === (int)$selectedProviderId ? 'selected' : '' ?>>
                                            <?= htmlspecialchars(trim(($provider['numero_proveedor'] !== null ? $provider['numero_proveedor'] . ' - ' : '') . ($provider['razon_social'] ?? 'Proveedor')), ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php elseif (!empty($providers)): ?>
                            <input type="hidden" name="provider_id" id="filter-provider" value="<?= (int)$selectedProviderId ?>">
                            <div class="col-xl-3 col-md-4">
                                <label class="form-label text-muted">Proveedor</label>
                                <div class="form-control-plaintext fw-semibold">
                                    <?= htmlspecialchars($providers[0]['name'] ?? 'Proveedor', ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="col-xl-3 col-md-4">
                            <label class="form-label text-muted">Periodo</label>
                            <select class="form-select" name="period" id="filter-period">
                                <option value="mtm" <?= ($filters['period'] ?? 'mtm') === 'mtm' ? 'selected' : '' ?>>Mes contra mes</option>
                                <option value="mtd" <?= ($filters['period'] ?? 'mtm') === 'mtd' ? 'selected' : '' ?>>Mes a la fecha</option>
                                <option value="qtd" <?= ($filters['period'] ?? 'mtm') === 'qtd' ? 'selected' : '' ?>>Trimestre a la fecha</option>
                                <option value="ytd" <?= ($filters['period'] ?? 'mtm') === 'ytd' ? 'selected' : '' ?>>Año a la fecha</option>
                            </select>
                        </div>

                        <div class="col-xl-3 col-md-4">
                            <label class="form-label text-muted">Canal</label>
                            <select class="form-select" name="channel" id="filter-channel">
                                <option value="all" <?= ($filters['channel'] ?? 'all') === 'all' ? 'selected' : '' ?>>Todos</option>
                                <option value="retail" <?= ($filters['channel'] ?? 'all') === 'retail' ? 'selected' : '' ?>>Retail</option>
                                <option value="mayoreo" <?= ($filters['channel'] ?? 'all') === 'mayoreo' ? 'selected' : '' ?>>Mayoreo</option>
                                <option value="online" <?= ($filters['channel'] ?? 'all') === 'online' ? 'selected' : '' ?>>Online</option>
                            </select>
                        </div>

                        <div class="col-xl-3 col-md-4">
                            <label class="form-label text-muted">Categoría</label>
                            <select class="form-select" name="category" id="filter-category">
                                <option value="all" <?= ($filters['category'] ?? 'all') === 'all' ? 'selected' : '' ?>>General</option>
                                <option value="papeleria" <?= ($filters['category'] ?? 'all') === 'papeleria' ? 'selected' : '' ?>>Papelería</option>
                                <option value="tecnologia" <?= ($filters['category'] ?? 'all') === 'tecnologia' ? 'selected' : '' ?>>Tecnología</option>
                                <option value="mobiliario" <?= ($filters['category'] ?? 'all') === 'mobiliario' ? 'selected' : '' ?>>Mobiliario</option>
                            </select>
                        </div>

                        <div class="col-xl-3 col-md-12 d-flex gap-3">
                            <button type="submit" id="apply-filters" class="btn btn-primary flex-grow-1">
                                <i class="fa-solid fa-rotate me-2"></i> Actualizar
                            </button>
                            <button type="button" class="btn btn-light" id="clear-filters">
                                <i class="fa-solid fa-broom me-2"></i> Limpiar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-5 g-xl-8 mt-1">
        <div class="col-xl-3 col-md-6">
            <div class="card card-flush border-0 shadow-sm">
                <div class="card-body">
                    <span class="fs-7 text-muted">Sell In</span>
                    <div class="d-flex align-items-baseline mt-2">
                        <span class="fs-2hx fw-bold text-dark me-2" data-dashboard="sellin-value"><?= $formatCurrency($summaryDefaults['sellin']) ?></span>
                        <span class="badge fs-8 <?= $growthClass($summaryDefaults['sellin_growth']) ?>" data-dashboard="sellin-growth">
                            <i class="fa-solid <?= $growthIcon($summaryDefaults['sellin_growth']) ?> me-1"></i>
                            <span data-dashboard="sellin-growth-value"><?= number_format((float)$summaryDefaults['sellin_growth'], 1) ?></span>%
                        </span>
                    </div>
                    <span class="text-muted fs-8">Último periodo seleccionado</span>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card card-flush border-0 shadow-sm">
                <div class="card-body">
                    <span class="fs-7 text-muted">Sell Out</span>
                    <div class="d-flex align-items-baseline mt-2">
                        <span class="fs-2hx fw-bold text-dark me-2" data-dashboard="sellout-value"><?= $formatCurrency($summaryDefaults['sellout']) ?></span>
                        <span class="badge fs-8 <?= $growthClass($summaryDefaults['sellout_growth']) ?>" data-dashboard="sellout-growth">
                            <i class="fa-solid <?= $growthIcon($summaryDefaults['sellout_growth']) ?> me-1"></i>
                            <span data-dashboard="sellout-growth-value"><?= number_format((float)$summaryDefaults['sellout_growth'], 1) ?></span>%
                        </span>
                    </div>
                    <span class="text-muted fs-8">Movimiento de salida</span>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card card-flush border-0 shadow-sm">
                <div class="card-body">
                    <span class="fs-7 text-muted">Órdenes pendientes</span>
                    <div class="d-flex align-items-baseline mt-2">
                        <span class="fs-2hx fw-bold text-dark me-2" data-dashboard="orders-pending"><?= $formatNumber($summaryDefaults['orders_pending']) ?></span>
                    </div>
                    <span class="text-muted fs-8">Órdenes con estatus por cerrar</span>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card card-flush border-0 shadow-sm">
                <div class="card-body">
                    <span class="fs-7 text-muted">Alertas</span>
                    <div class="d-flex align-items-baseline mt-2">
                        <span class="fs-2hx fw-bold text-dark me-2" data-dashboard="alerts"><?= $formatNumber($summaryDefaults['alerts']) ?></span>
                    </div>
                    <span class="text-muted fs-8">Eventos abiertos en logística y cobranzas</span>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-5 g-xl-8 mt-1">
        <div class="col-xl-8">
            <div class="card card-flush border-0 shadow-sm" data-permission="<?= !empty($permissions['sales']) ? 'allowed' : 'denied' ?>">
                <div class="card-header pt-5">
                    <h3 class="card-title fw-bold text-dark">Tendencia Sell In vs Sell Out</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($permissions['sales'])): ?>
                        <div id="chart_sales_trend" style="height: 320px"></div>
                    <?php else: ?>
                        <div class="alert alert-light border align-items-center mb-0">
                            <div class="d-flex flex-column">
                                <span class="fw-semibold text-dark mb-1">Sin permiso</span>
                                <span class="text-muted fs-8">Asigna el módulo de ventas para visualizar la información.</span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xl-4">
            <div class="card card-flush border-0 shadow-sm" data-permission="<?= !empty($permissions['orders']) ? 'allowed' : 'denied' ?>">
                <div class="card-header pt-5">
                    <h3 class="card-title fw-bold text-dark">Distribución de órdenes</h3>
                </div>
                <div class="card-body">
                    <?php if (!empty($permissions['orders'])): ?>
                        <div id="chart_orders_status" style="height: 320px"></div>
                    <?php else: ?>
                        <div class="alert alert-light border align-items-center mb-0">
                            <div class="d-flex flex-column">
                                <span class="fw-semibold text-dark mb-1">Sin permiso</span>
                                <span class="text-muted fs-8">Solicita acceso al módulo de órdenes.</span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-5 g-xl-8 mt-1">
        <div class="col-xl-6">
            <div class="card card-flush border-0 shadow-sm" data-permission="<?= !empty($permissions['sales']) ? 'allowed' : 'denied' ?>">
                <div class="card-header pt-5">
                    <h3 class="card-title fw-bold text-dark">Top productos Sell Out</h3>
                </div>
                <div class="card-body px-0">
                    <?php if (!empty($permissions['sales'])): ?>
                        <div class="table-responsive">
                            <table class="table align-middle table-row-dashed">
                                <thead>
                                    <tr class="text-muted fw-semibold text-uppercase fs-8">
                                        <th class="ps-9">SKU</th>
                                        <th>Descripción</th>
                                        <th class="text-end">Sell Out</th>
                                        <th class="text-end pe-9">Var %</th>
                                    </tr>
                                </thead>
                                <tbody data-dashboard="products-body">
                                    <?php if (!empty($topProducts)): ?>
                                        <?php foreach ($topProducts as $product): ?>
                                            <?php $growth = (float)($product['growth'] ?? 0); ?>
                                            <tr>
                                                <td class="ps-9 fw-bold text-dark"><?= htmlspecialchars($product['sku'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="text-muted"><?= htmlspecialchars($product['description'] ?? '-', ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="text-end fw-bold text-dark"><?= $formatCurrency($product['sellout'] ?? 0) ?></td>
                                                <td class="text-end pe-9">
                                                    <span class="badge <?= $growth >= 0 ? 'badge-light-success' : 'badge-light-danger' ?>">
                                                        <?= number_format($growth, 1) ?>%
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td class="ps-9 text-muted" colspan="4">Sin información disponible.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-light border mx-5 mt-4 mb-0">
                            <span class="fw-semibold">Agrega el permiso de ventas para consultar este bloque.</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-xl-6">
            <div class="card card-flush border-0 shadow-sm">
                <div class="card-header pt-5">
                    <h3 class="card-title fw-bold text-dark">Resumen operativo</h3>
                </div>
                <div class="card-body">
                    <div class="d-flex flex-column gap-5">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted d-block">Órdenes cumplidas</span>
                                <span class="fw-bold fs-3" data-dashboard="orders-fulfilled"><?= $formatNumber($summaryDefaults['orders_fulfilled']) ?></span>
                            </div>
                            <div class="progress h-8px w-150px">
                                <div class="progress-bar bg-success" role="progressbar" style="width: 72%" data-dashboard="orders-fulfilled-progress"></div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted d-block">Devoluciones</span>
                                <span class="fw-bold fs-3" data-dashboard="returns"><?= $formatNumber($summaryDefaults['returns']) ?></span>
                            </div>
                            <div class="progress h-8px w-150px">
                                <div class="progress-bar bg-warning" role="progressbar" style="width: 28%" data-dashboard="returns-progress"></div>
                            </div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted d-block">Incidencias abiertas</span>
                                <span class="fw-bold fs-3" data-dashboard="alerts-operational"><?= $formatNumber($summaryDefaults['alerts']) ?></span>
                            </div>
                            <div class="progress h-8px w-150px">
                                <div class="progress-bar bg-danger" role="progressbar" style="width: 18%" data-dashboard="alerts-progress"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
window.DASHBOARD_DATA = <?= json_encode($initialPayload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK) ?>;
</script>
<script src="<?= htmlspecialchars($jsBase . '/dashboard.js', ENT_QUOTES, 'UTF-8') ?>" defer></script>
