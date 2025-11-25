<?php
$page = $page ?? 'overview';
$title = $title ?? 'Ventas';
$trend = $trend ?? ['categories' => [], 'series' => []];
$summary = $summary ?? [];
$filters = $filters ?? [];
$products = $products ?? [];
$stores = $stores ?? [];
$productOptions = $productOptions ?? [];

$trendJson = json_encode($trend, JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);

$descriptionMap = [
    'overview' => 'Indicadores clave de sell-out por articulo y tienda.',
    'periods' => 'Comparativo por periodos y acumulados.',
    'sellout' => 'Detalle de Sell-out con acumulados anualizados.',
    'sellinout' => 'Cruce Sell-in vs Sell-out para detectar brechas.',
];
$description = $descriptionMap[$page] ?? 'Resumen de ventas.';

$formatCurrency = static function ($value): string {
    return '$' . number_format((float)$value, 2, '.', ',');
};
$formatNumber = static function ($value): string {
    return number_format((float)$value, 2, '.', ',');
};
?>

<div class="card card-flush border-0 shadow-sm mb-6">
    <div class="card-header pt-5 pb-0">
        <h3 class="card-title fw-bold text-dark mb-1">Filtros</h3>
        <span class="text-muted fs-8">Ajusta el rango de fechas, tienda y articulo para analizar el comportamiento.</span>
    </div>
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label text-muted fw-semibold">Inicio</label>
                <input type="date" name="start_date" value="<?= htmlspecialchars($filters['start_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted fw-semibold">Fin</label>
                <input type="date" name="end_date" value="<?= htmlspecialchars($filters['end_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted fw-semibold">Agrupar por</label>
                <select name="group_by" class="form-select">
                    <?php foreach (['month' => 'Mes', 'week' => 'Semana', 'day' => 'Día'] as $value => $label): ?>
                        <option value="<?= $value ?>" <?= ($filters['group_by'] ?? 'month') === $value ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted fw-semibold">Tienda</label>
                <select name="store_id" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach ($stores as $store): ?>
                        <option value="<?= (int)$store['id'] ?>" <?= (isset($filters['store_id']) && (int)$filters['store_id'] === (int)$store['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($store['label'], ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label text-muted fw-semibold">Articulo / SKU / Código de barras</label>
                <input type="search"
                       name="query"
                       list="sales_products_datalist"
                       value="<?= htmlspecialchars($filters['query'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       class="form-control"
                       placeholder="Buscar artículo, SKU o código de barras">
                <datalist id="sales_products_datalist">
                    <?php foreach ($productOptions as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="col-md-2 form-check pt-4">
                <input class="form-check-input me-2" type="checkbox" value="1" id="include_inactive" name="include_inactive" <?= !empty($filters['include_inactive']) ? 'checked' : '' ?>>
                <label class="form-check-label fw-semibold text-muted" for="include_inactive">
                    Incluir descontinuados
                </label>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fa-solid fa-filter me-2"></i>Aplicar filtros
                </button>
            </div>
            <div class="col-md-3">
                <a href="?reset=1" class="btn btn-light w-100">
                    <i class="fa-solid fa-rotate-left me-2"></i>Restablecer
                </a>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($summary)): ?>
    <div class="row g-4 mb-6">
        <div class="col-md-3">
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
        <div class="col-md-3">
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
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <span class="fw-semibold text-muted d-block mb-2">Devoluciones</span>
                    <span class="fs-3 fw-bold"><?= $formatCurrency($summary['returns'] ?? 0) ?></span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <span class="fw-semibold text-muted d-block mb-2">Alertas</span>
                    <span class="fs-3 fw-bold"><?= (int)($summary['alerts'] ?? 0) ?></span>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="card card-flush border-0 shadow-sm mb-6">
    <div class="card-header pt-5 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h3 class="card-title fw-bold text-dark mb-1"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h3>
            <span class="text-muted"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </div>
    <div class="card-body">
        <div id="chart_sales_generic" style="height: 360px"></div>
    </div>
</div>

<div class="card card-flush border-0 shadow-sm">
    <div class="card-header pt-5 pb-0">
        <h3 class="card-title fw-bold text-dark mb-1">Detalle por artículo</h3>
        <span class="text-muted fs-8">
            Comparativo de piezas, importe y costo entre el periodo actual, acumulado y periodo comparable del año anterior.
        </span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead class="text-muted fw-semibold">
                    <tr>
                        <th>Artículo</th>
                        <th>Descripción</th>
                        <th>Estatus</th>
                        <th class="text-end">Pzas mes</th>
                        <th class="text-end">Importe mes</th>
                        <th class="text-end">Costo mes</th>
                        <th class="text-end">Pzas YTD</th>
                        <th class="text-end">Importe YTD</th>
                        <th class="text-end">Pzas año anterior</th>
                        <th class="text-end">Importe año anterior</th>
                        <th class="text-end">Pzas YTD año anterior</th>
                        <th class="text-end">Importe YTD año anterior</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($products)): ?>
                    <?php foreach ($products as $row): ?>
                        <tr>
                            <td class="fw-semibold text-dark"><?= htmlspecialchars($row['codigo'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-muted"><?= htmlspecialchars($row['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <span class="badge badge-light"><?= htmlspecialchars($row['status'] ?? 'Vigente', ENT_QUOTES, 'UTF-8') ?></span>
                            </td>
                            <td class="text-end fw-semibold"><?= $formatNumber($row['units_current'] ?? 0) ?></td>
                            <td class="text-end fw-semibold"><?= $formatCurrency($row['value_current'] ?? 0) ?></td>
                            <td class="text-end fw-semibold"><?= $formatCurrency($row['cost_current'] ?? 0) ?></td>

                            <td class="text-end"><?= $formatNumber($row['units_ytd'] ?? 0) ?></td>
                            <td class="text-end"><?= $formatCurrency($row['value_ytd'] ?? 0) ?></td>

                            <td class="text-end text-muted"><?= $formatNumber($row['units_compare'] ?? 0) ?></td>
                            <td class="text-end text-muted"><?= $formatCurrency($row['value_compare'] ?? 0) ?></td>

                            <td class="text-end text-muted"><?= $formatNumber($row['units_ytd_compare'] ?? 0) ?></td>
                            <td class="text-end text-muted"><?= $formatCurrency($row['value_ytd_compare'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="12" class="text-center text-muted py-10">
                            No se encontraron artículos para los filtros seleccionados.
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
(function () {
    const chartHost = document.querySelector('#chart_sales_generic');
    const chartData = <?= $trendJson ?>;
    if (!chartHost || !chartData || !Array.isArray(chartData.series)) {
        return;
    }
    const chart = new ApexCharts(chartHost, {
        series: chartData.series,
        chart: { type: 'line', height: 360, toolbar: { show: false } },
        stroke: { width: [3, 3], curve: 'smooth' },
        markers: { size: 3 },
        xaxis: { categories: chartData.categories || [] },
        yaxis: { labels: { formatter: function (value) { return '$' + Number(value || 0).toLocaleString(); } } },
        tooltip: { y: { formatter: function (value) { return '$' + Number(value || 0).toLocaleString(); } } },
        legend: { position: 'top' }
    });
    chart.render();
})();
</script>
