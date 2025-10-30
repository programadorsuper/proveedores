<?php
$page = $page ?? 'overview';
$trend = $trend ?? ['categories' => [], 'series' => []];
$title = $title ?? 'Ventas';
$trendJson = json_encode($trend, JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK);
$descriptionMap = [
    'overview' => 'Indicadores clave de ventas por canal y familia.',
    'periods' => 'Comparativo por periodo con metas contra historicos.',
    'sellout' => 'Detalle de Sell Out por cliente y categoria.',
    'sellinout' => 'Cruce Sell In vs Sell Out para detectar quiebres.',
];
$description = $descriptionMap[$page] ?? 'Resumen de ventas.';
?>
<div class="card card-flush border-0 shadow-sm">
    <div class="card-header pt-5 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h3 class="card-title fw-bold text-dark mb-1"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h3>
            <span class="text-muted"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="d-flex gap-2 mt-3 mt-md-0">
            <button type="button" class="btn btn-sm btn-light-primary">
                <i class="fa-solid fa-arrow-rotate-right me-2"></i> Actualizar
            </button>
            <button type="button" class="btn btn-sm btn-light">
                <i class="fa-solid fa-file-export me-2"></i> Exportar
            </button>
        </div>
    </div>
    <div class="card-body">
        <div id="chart_sales_generic" style="height: 360px"></div>
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
        chart: { type: 'bar', height: 360, stacked: true, toolbar: { show: false } },
        plotOptions: { bar: { horizontal: false, columnWidth: '45%', borderRadius: 4 } },
        stroke: { show: true, width: 2, colors: ['transparent'] },
        xaxis: { categories: chartData.categories || [] },
        yaxis: { labels: { formatter: function (value) { return '$' + value.toLocaleString(); } } },
        tooltip: { y: { formatter: function (value) { return '$' + value.toLocaleString(); } } },
        fill: { opacity: 0.85 },
        legend: { position: 'top' }
    });
    chart.render();
})();
</script>
