<?php
$order = $order ?? [];
$items = $items ?? [];
$downloadBase = $downloadBase ?? '#';

$formatMoney = static function ($value): string {
    return '$ ' . number_format((float)$value, 2, '.', ',');
};

$formatDate = static function ($value): string {
    if (empty($value)) {
        return '';
    }
    $timestamp = strtotime((string)$value);
    if ($timestamp === false) {
        return (string)$value;
    }
    return date('d/m/Y H:i', $timestamp);
};

$downloadUrl = static function (string $format) use ($downloadBase, $order): string {
    $id = (int)($order['ID_COMPRA'] ?? 0);
    if ($id <= 0 || $downloadBase === '#') {
        return '#';
    }
    $base = $downloadBase . '?id=' . urlencode($id) . '&format=' . urlencode($format);
    return $base;
};
?>

<div class="card card-flush border-0 shadow-sm mb-6">
    <div class="card-header flex-wrap gap-3 align-items-center">
        <div>
            <h3 class="card-title fw-bold mb-1">Orden #<?= htmlspecialchars($order['FOLIO'] ?? $order['ID_COMPRA'] ?? '', ENT_QUOTES, 'UTF-8') ?></h3>
            <span class="text-muted fs-8">Serie <?= htmlspecialchars($order['SERIE'] ?? '-', ENT_QUOTES, 'UTF-8') ?> &bull; Fecha <?= htmlspecialchars($formatDate($order['FECHA'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="ms-auto d-flex flex-wrap gap-2">
            <a class="btn btn-sm btn-light" href="<?= htmlspecialchars($downloadUrl('pdf'), ENT_QUOTES, 'UTF-8') ?>" target="_blank">
                <i class="fa-solid fa-file-pdf me-2"></i>PDF
            </a>
            <a class="btn btn-sm btn-light" href="<?= htmlspecialchars($downloadUrl('xml'), ENT_QUOTES, 'UTF-8') ?>" target="_blank">
                <i class="fa-solid fa-code me-2"></i>XML
            </a>
            <a class="btn btn-sm btn-light" href="<?= htmlspecialchars($downloadUrl('csv'), ENT_QUOTES, 'UTF-8') ?>" target="_blank">
                <i class="fa-solid fa-file-csv me-2"></i>CSV
            </a>
            <a class="btn btn-sm btn-light" href="<?= htmlspecialchars($downloadUrl('xlsx'), ENT_QUOTES, 'UTF-8') ?>" target="_blank">
                <i class="fa-solid fa-file-excel me-2"></i>XLSX
            </a>
            <a class="btn btn-sm btn-primary" href="javascript:history.back()">
                <i class="fa-solid fa-arrow-left me-2"></i>Regresar
            </a>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-4">
            <div class="col-md-6">
                <div class="border rounded p-4 h-100">
                    <h5 class="fw-semibold mb-3">Proveedor</h5>
                    <p class="mb-1"><strong><?= htmlspecialchars($order['RAZON_SOCIAL'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong></p>
                    <p class="text-muted mb-1">No. proveedor: <?= htmlspecialchars($order['NUMERO_PROVEEDOR'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="text-muted mb-1"><?= htmlspecialchars(trim(($order['CALLE'] ?? '') . ' ' . ($order['NUMERO_EXTERIOR'] ?? '') . ' ' . ($order['NUMERO_INTERIOR'] ?? '')), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="text-muted mb-1"><?= htmlspecialchars(trim(($order['COLONIA'] ?? '') . ', ' . ($order['CIUDAD'] ?? '') . ', ' . ($order['CODIGO_POSTAL'] ?? '')), ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="text-muted mb-0">Atenci&oacute;n: <?= htmlspecialchars($order['ATENCION'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
            <div class="col-md-6">
                <div class="border rounded p-4 h-100">
                    <h5 class="fw-semibold mb-3">Orden</h5>
                    <p class="mb-1">Tienda solicitante: <strong><?= htmlspecialchars($order['NOMBRE_CORTO'] ?? 'N/D', ENT_QUOTES, 'UTF-8') ?></strong></p>
                    <p class="mb-1">Entrega: <strong><?= htmlspecialchars($order['LUGAR_ENTREGA'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong></p>
                    <p class="mb-1">Cr&eacute;dito: <strong><?= (int)($order['DIAS_CREDITO'] ?? 0) ?> d&iacute;as</strong></p>
                    <p class="mb-1">Captur&oacute;: <?= htmlspecialchars($order['CAPTURA'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                    <p class="mb-0">Autoriz&oacute;: <?= htmlspecialchars($order['AUTORIZA'] ?? '-', ENT_QUOTES, 'UTF-8') ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card card-flush border-0 shadow-sm">
    <div class="card-header">
        <h3 class="card-title fw-bold mb-1">Partidas</h3>
        <span class="text-muted fs-8">Detalle completo de los art&iacute;culos solicitados.</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead class="text-muted fw-semibold">
                    <tr>
                        <th>Codigo</th>
                        <th>SKU</th>
                        <th>Descripcion</th>
                        <th class="text-end">Cantidad</th>
                        <th class="text-center">Unidad</th>
                        <th class="text-end">Costo</th>
                        <th class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($items)): ?>
                    <?php foreach ($items as $item): ?>
                        <?php
                        $cantidad = (float)($item['CANTIDAD_SOLICITADA'] ?? 0);
                        $costo = (float)($item['COSTO'] ?? 0);
                        ?>
                        <tr>
                            <td class="fw-semibold text-dark"><?= htmlspecialchars($item['CODIGO'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($item['SKU'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($item['DESCRIPCION'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-end"><?= number_format($cantidad, 2, '.', ',') ?></td>
                            <td class="text-center"><?= htmlspecialchars($item['UNIDAD_CORTA'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-end"><?= $formatMoney($costo) ?></td>
                            <td class="text-end"><?= $formatMoney($cantidad * $costo) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-10">Sin partidas registradas.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="mt-6 d-flex flex-column flex-md-row gap-4 justify-content-between">
            <div class="flex-grow-1">
                <h6 class="fw-semibold mb-2">Observaciones</h6>
                <div class="border rounded p-3 bg-light">
                    <?= nl2br(htmlspecialchars(trim((string)($order['COMENTARIO'] ?? 'Sin observaciones.')), ENT_QUOTES, 'UTF-8')) ?>
                </div>
            </div>
            <div class="border rounded p-3" style="min-width: 220px;">
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">Subtotal</span>
                    <span class="fw-semibold"><?= $formatMoney($order['IMPORTE'] ?? 0) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">Descuentos</span>
                    <span class="fw-semibold">
                        <?= $formatMoney(($order['DESCUENTO_1'] ?? 0) + ($order['DESCUENTO_2'] ?? 0) + ($order['DESCUENTO_3'] ?? 0)) ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">Impuestos</span>
                    <span class="fw-semibold">
                        <?= $formatMoney(($order['IMPUESTOS_TRASLADADOS'] ?? 0) + ($order['IMPUESTOS_TRASLADADOS_2'] ?? 0)) ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between border-top pt-2 mt-2">
                    <span class="fw-semibold">Total</span>
                    <span class="fw-bold text-primary"><?= $formatMoney($order['TOTAL'] ?? 0) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>
