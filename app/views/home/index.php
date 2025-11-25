<?php
$providers = $providers ?? [];
$selectedProviderId = $selectedProviderId ?? null;
$filters = $filters ?? [];
$periodOptions = $periodOptions ?? [];
$summary = $summary ?? [];
$trend = $trend ?? ['categories' => [], 'series' => []];
$ordersDistribution = $ordersDistribution ?? [];
$topProducts = $topProducts ?? [];
$topCustomers = $topCustomers ?? [];
$topStores = $topStores ?? [];
$topStoresChart = $topStoresChart ?? ['categories' => [], 'series' => [['name' => 'Sell-out', 'data' => []]]];
$productOptions = $productOptions ?? [];
$stores = $stores ?? [];

$formatCurrency = static function ($value): string {
    return '$' . number_format((float)$value, 2, '.', ',');
};

$formatNumber = static function ($value): string {
    return number_format((float)$value, 2, '.', ',');
};

$trendJson = json_encode($trend, JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
$ordersJson = json_encode($ordersDistribution, JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
$topStoresChartJson = json_encode($topStoresChart, JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
?>

<div class="card card-flush border-0 shadow-sm mb-6">
    <div class="card-header pt-5 pb-0">
        <h3 class="card-title fw-bold text-dark mb-1">Filtros generales</h3>
        <span class="text-muted fs-8">Ajusta proveedor, periodo y filtros para personalizar tu tablero.</span>
    </div>
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-xl-3 col-md-4">
                <label class="form-label text-muted fw-semibold">Proveedor</label>
                <select class="form-select" name="provider_id">
                    <?php foreach ($providers as $provider): ?>
                        <?php $providerId = (int)($provider['id'] ?? 0); ?>
                        <option value="<?= $providerId ?>" <?= $providerId === (int)$selectedProviderId ? 'selected' : '' ?>>
                            <?= htmlspecialchars(trim($provider['numero_proveedor'] ?? '') !== '' ? ($provider['numero_proveedor'] . ' - ' . ($provider['razon_social'] ?? $provider['name'] ?? 'Proveedor')) : ($provider['razon_social'] ?? $provider['name'] ?? 'Proveedor'), ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-xl-2 col-md-4">
                <label class="form-label text-muted fw-semibold">Periodo</label>
                <select class="form-select" name="period">
                    <?php foreach ($periodOptions as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= ($filters['period'] ?? 'ytd') === $value ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-xl-2 col-md-4">
                <label class="form-label text-muted fw-semibold">Inicio</label>
                <input type="date" class="form-control" name="start_date" value="<?= htmlspecialchars($filters['start_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="col-xl-2 col-md-4">
                <label class="form-label text-muted fw-semibold">Fin</label>
                <input type="date" class="form-control" name="end_date" value="<?= htmlspecialchars($filters['end_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            </div>

            <div class="col-xl-2 col-md-4">
                <label class="form-label text-muted fw-semibold">Agrupar por</label>
                <select class="form-select" name="group_by">
                    <?php foreach (['month' => 'Mes', 'week' => 'Semana', 'day' => 'Día'] as $value => $label): ?>
                        <option value="<?= $value ?>" <?= ($filters['group_by'] ?? 'month') === $value ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-xl-3 col-md-4">
                <label class="form-label text-muted fw-semibold">Tienda</label>
                <select class="form-select" name="store_id">
                    <option value="">Todas</option>
                    <?php foreach ($stores as $store): ?>
                        <option value="<?= (int)$store['id'] ?>" <?= isset($filters['store_id']) && (int)$filters['store_id'] === (int)$store['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($store['label'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-xl-4 col-md-6">
                <label class="form-label text-muted fw-semibold">Artículo, SKU o código</label>
                <input type="search"
                       name="query"
                       list="dashboard-products"
                       value="<?= htmlspecialchars($filters['query'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       class="form-control"
                       placeholder="Buscar artículo, SKU o código de barras">
                <datalist id="dashboard-products">
                    <?php foreach ($productOptions as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </datalist>
            </div>

            <div class="col-xl-2 col-md-4 form-check pt-4">
                <input class="form-check-input me-2" type="checkbox" value="1" id="include_inactive_dashboard" name="include_inactive" <?= !empty($filters['include_inactive']) ? 'checked' : '' ?>>
                <label class="form-check-label fw-semibold text-muted" for="include_inactive_dashboard">
                    Incluir descontinuados
                </label>
            </div>

            <div class="col-xl-2 col-md-4">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fa-solid fa-filter me-2"></i>Aplicar
                </button>
            </div>
            <div class="col-xl-2 col-md-4">
                <a href="?reset=1" class="btn btn-light w-100">
                    <i class="fa-solid fa-rotate-left me-2"></i>Restablecer
                </a>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($summary)): ?>
    <div class="row g-4 mb-6">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <span class="fw-semibold text-muted d-block mb-2">Sell-out</span>
                    <div class="d-flex align-items-baseline">
                        <span class="fs-3 fw-bold me-2"><?= $formatCurrency($summary['sellout'] ?? 0) ?></span>
                        <span class="badge <?= ($summary['sellout_growth'] ?? 0) >= 0 ? 'badge-light-success' : 'badge-light-danger' ?>">
                            <?= round((float)($summary['sellout_growth'] ?? 0), 1) ?>%
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <span class="fw-semibold text-muted d-block mb-2">Sell-in</span>
                    <div class="d-flex align-items-baseline">
                        <span class="fs-3 fw-bold me-2"><?= $formatCurrency($summary['sellin'] ?? 0) ?></span>
                        <span class="badge <?= ($summary['sellin_growth'] ?? 0) >= 0 ? 'badge-light-success' : 'badge-light-danger' ?>">
                            <?= round((float)($summary['sellin_growth'] ?? 0), 1) ?>%
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <span class="fw-semibold text-muted d-block mb-2">Devoluciones</span>
                    <span class="fs-3 fw-bold"><?= $formatCurrency($summary['returns'] ?? 0) ?></span>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <span class="fw-semibold text-muted d-block mb-2">Alertas</span>
                    <span class="fs-3 fw-bold"><?= (int)($summary['alerts'] ?? 0) ?></span>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4 mb-6">
    <div class="col-xxl-8">
        <div class="card card-flush border-0 shadow-sm h-100">
            <div class="card-header pt-5 pb-0">
                <h3 class="card-title fw-bold text-dark mb-1">Tendencia Sell-out vs Sell-in</h3>
                <span class="text-muted fs-8">Valores en moneda para el periodo seleccionado.</span>
            </div>
            <div class="card-body">
                <div id="chart_home_trend" style="height: 360px"></div>
                <?php if (empty($trend['categories'])): ?>
                    <div class="text-center text-muted py-10">Sin datos suficientes para graficar.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xxl-4">
        <div class="card card-flush border-0 shadow-sm h-100">
            <div class="card-header pt-5 pb-0">
                <h3 class="card-title fw-bold text-dark mb-1">Distribución del periodo</h3>
                <span class="text-muted fs-8">Sell-in, Sell-out y devoluciones.</span>
            </div>
            <div class="card-body">
                <div id="chart_home_orders" style="height: 360px"></div>
                <?php if (empty($ordersDistribution)): ?>
                    <div class="text-center text-muted py-10">Sin datos disponibles.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-6">
    <div class="col-xxl-6">
        <div class="card card-flush border-0 shadow-sm h-100">
            <div class="card-header pt-5 pb-0">
                <h3 class="card-title fw-bold text-dark mb-1">Top artículos (Sell-out)</h3>
                <span class="text-muted fs-8">Comparativo del periodo versus año anterior.</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle">
                        <thead class="text-muted fw-semibold">
                            <tr>
                                <th>Artículo</th>
                                <th class="text-end">Ventas</th>
                                <th class="text-end">Pzas</th>
                                <th class="text-end text-muted">Ventas año prev.</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($topProducts)): ?>
                            <?php foreach ($topProducts as $row): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($row['codigo'] ?? $row['sku'] ?? 'N/D', ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-muted fs-8"><?= htmlspecialchars($row['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                    </td>
                                    <td class="text-end fw-semibold"><?= $formatCurrency($row['value_current'] ?? 0) ?></td>
                                    <td class="text-end"><?= $formatNumber($row['units_current'] ?? 0) ?></td>
                                    <td class="text-end text-muted"><?= $formatCurrency($row['value_compare'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-10">Sin información para los filtros actuales.</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xxl-6">
        <div class="card card-flush border-0 shadow-sm h-100">
            <div class="card-header pt-5 pb-0">
                <h3 class="card-title fw-bold text-dark mb-1">Top clientes</h3>
                <span class="text-muted fs-8">Clientes con mayor sell-out en el periodo.</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle">
                        <thead class="text-muted fw-semibold">
                            <tr>
                                <th>Cliente</th>
                                <th class="text-end">Ventas</th>
                                <th class="text-end">Pzas</th>
                                <th class="text-end">Tickets</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($topCustomers)): ?>
                            <?php foreach ($topCustomers as $row): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($row['customer_name'] ?? ('Cliente #' . ($row['customer_id'] ?? 'N/D')), ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-muted fs-8">ID <?= (int)($row['customer_id'] ?? 0) ?></div>
                                    </td>
                                    <td class="text-end fw-semibold"><?= $formatCurrency($row['value_total'] ?? 0) ?></td>
                                    <td class="text-end"><?= $formatNumber($row['units_total'] ?? 0) ?></td>
                                    <td class="text-end"><?= (int)($row['tickets'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-10">Sin información para mostrar.</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card card-flush border-0 shadow-sm">
    <div class="card-header pt-5 pb-0">
        <h3 class="card-title fw-bold text-dark mb-1">Tiendas destacadas</h3>
        <span class="text-muted fs-8">Participación por tienda para el periodo seleccionado.</span>
    </div>
    <div class="card-body">
        <div class="row g-4">
            <div class="col-lg-6">
                <div id="chart_home_stores" style="height: 320px"></div>
                <?php if (empty($topStoresChart['categories'])): ?>
                    <div class="text-center text-muted py-6">Sin información de tiendas.</div>
                <?php endif; ?>
            </div>
            <div class="col-lg-6">
                <div class="table-responsive">
                    <table class="table table-row-dashed align-middle">
                        <thead class="text-muted fw-semibold">
                            <tr>
                                <th>Tienda</th>
                                <th class="text-end">Ventas</th>
                                <th class="text-end">Pzas</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($topStores)): ?>
                            <?php foreach ($topStores as $row): ?>
                                <tr>
                                    <td class="fw-semibold text-dark">Tienda #<?= (int)($row['id_tienda'] ?? $row['store_id'] ?? 0) ?></td>
                                    <td class="text-end fw-semibold"><?= $formatCurrency($row['value_total'] ?? 0) ?></td>
                                    <td class="text-end"><?= $formatNumber($row['units_total'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-10">Sin información disponible.</td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const trendHost = document.querySelector('#chart_home_trend');
    const trendData = <?= $trendJson ?>;
    if (trendHost && trendData && Array.isArray(trendData.series)) {
        new ApexCharts(trendHost, {
            series: trendData.series,
            chart: { type: 'line', height: 360, toolbar: { show: false } },
            stroke: { width: 3, curve: 'smooth' },
            markers: { size: 3 },
            xaxis: { categories: trendData.categories || [] },
            yaxis: { labels: { formatter: function (value) { return '$' + Number(value || 0).toLocaleString(); } } },
            tooltip: { y: { formatter: function (value) { return '$' + Number(value || 0).toLocaleString(); } } },
            legend: { position: 'top' }
        }).render();
    }

    const ordersHost = document.querySelector('#chart_home_orders');
    const ordersData = <?= $ordersJson ?>;
    if (ordersHost && Array.isArray(ordersData) && ordersData.length > 0) {
        new ApexCharts(ordersHost, {
            series: ordersData.map(item => Number(item.value || 0)),
            chart: { type: 'donut', height: 360 },
            labels: ordersData.map(item => item.status || ''),
            legend: { position: 'bottom' },
            dataLabels: { enabled: true, formatter: function (val) { return val.toFixed(1) + '%'; } }
        }).render();
    }

    const storesHost = document.querySelector('#chart_home_stores');
    const storesData = <?= $topStoresChartJson ?>;
    if (storesHost && storesData && Array.isArray(storesData.series)) {
        new ApexCharts(storesHost, {
            series: storesData.series,
            chart: { type: 'bar', height: 320, toolbar: { show: false } },
            plotOptions: { bar: { borderRadius: 4, dataLabels: { position: 'top' } } },
            dataLabels: { enabled: true, formatter: function (value) { return '$' + Number(value || 0).toLocaleString(); }, offsetY: -20 },
            xaxis: { categories: storesData.categories || [], labels: { rotate: -45 } },
            yaxis: { labels: { formatter: function (value) { return '$' + Number(value || 0).toLocaleString(); } } },
            tooltip: { y: { formatter: function (value) { return '$' + Number(value || 0).toLocaleString(); } } }
        }).render();
    }
})();
</script>
