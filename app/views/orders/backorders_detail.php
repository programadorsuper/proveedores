<?php
$order = $order ?? [];
$items = $items ?? [];
$entries = $entries ?? [];
$receivedMap = $receivedMap ?? [];
$baseUrl = $basePath ?? '';

$seasonLabel = '';
if (!empty($order['ALIAS']) || !empty($order['TEMPORADA'])) {
    $seasonLabel = '[' . ($order['ALIAS'] ?? '') . '] ' . ($order['TEMPORADA'] ?? '');
}

$formatDate = static function ($value): string {
    if (empty($value)) {
        return '';
    }
    $ts = strtotime((string)$value);
    return $ts ? date('d/m/Y H:i', $ts) : (string)$value;
};
?>

<div class="d-flex justify-content-between align-items-center mb-6">
    <h2 class="fw-bold text-dark mb-0">Backorder #<?= htmlspecialchars($order['FOLIO'] ?? $order['ID_COMPRA'], ENT_QUOTES, 'UTF-8') ?></h2>
    <a class="btn btn-sm btn-primary" href="<?= htmlspecialchars(($baseUrl !== '' ? $baseUrl : '') . '/ordenes/backorder', ENT_QUOTES, 'UTF-8') ?>">
        <i class="fa-solid fa-arrow-left me-2"></i>Regresar
    </a>
</div>

<div class="card card-flush border-0 shadow-sm mb-6">
    <div class="card-header">
        <h3 class="card-title fw-bold">Datos generales</h3>
    </div>
    <div class="card-body">
        <div class="row g-4">
            <div class="col-md-7">
                <table class="table table-bordered align-middle mb-0">
                    <tbody>
                        <tr>
                            <th class="w-150px">Proveedor</th>
                            <td>[<?= htmlspecialchars($order['NUMERO_PROVEEDOR'] ?? '', ENT_QUOTES, 'UTF-8') ?>] <?= htmlspecialchars($order['RAZON_SOCIAL'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <tr>
                            <th>Direcci&oacute;n</th>
                            <td><?= htmlspecialchars(trim(($order['CALLE'] ?? '') . ', ' . ($order['COLONIA'] ?? '') . ', ' . ($order['CIUDAD'] ?? '') . ', ' . ($order['CODIGO_POSTAL'] ?? '')), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <tr>
                            <th>Tel&eacute;fono</th>
                            <td><?= htmlspecialchars($order['TELEFONO_OFICINA'] ?? 'N/D', ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <tr>
                            <th>Atenci&oacute;n</th>
                            <td><?= htmlspecialchars($order['ATENCION'] ?? 'N/D', ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <tr>
                            <th>Agentes</th>
                            <td>
                                <div><strong>Captura:</strong> <?= htmlspecialchars($order['CAPTURA'] ?? 'N/D', ENT_QUOTES, 'UTF-8') ?></div>
                                <div><strong>Autoriza:</strong> <?= htmlspecialchars($order['AUTORIZA'] ?? 'N/D', ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="col-md-5">
                <table class="table table-bordered align-middle mb-0">
                    <tbody>
                        <tr>
                            <th>Documento</th>
                            <td class="fw-bold">Serie <?= htmlspecialchars($order['SERIE'] ?? '', ENT_QUOTES, 'UTF-8') ?> - <?= str_pad((int)($order['FOLIO'] ?? 0), 6, '0', STR_PAD_LEFT) ?></td>
                        </tr>
                        <tr>
                            <th>Fecha</th>
                            <td><?= htmlspecialchars($formatDate($order['FECHA'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <tr>
                            <th>Tienda</th>
                            <td><?= htmlspecialchars($order['NOMBRE_CORTO'] ?? 'N/D', ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <tr>
                            <th>Entrega</th>
                            <td><?= htmlspecialchars($order['LUGAR_ENTREGA'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                        <tr>
                            <th>Temporada</th>
                            <td><?= htmlspecialchars($seasonLabel, ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($entries)): ?>
    <div class="card card-flush border-0 shadow-sm mb-6">
        <div class="card-header">
            <h3 class="card-title fw-bold">Entradas registradas</h3>
        </div>
        <?php foreach ($entries as $entry): ?>
            <?php $header = $entry['header']; $details = $entry['details'] ?? []; ?>
            <div class="card-body border-top">
                <h4 class="fw-semibold mb-1">Entrada #<?= htmlspecialchars($header['ID_ORDEN_ENTRADA'] ?? '', ENT_QUOTES, 'UTF-8') ?></h4>
                <div class="text-muted mb-3"><?= htmlspecialchars($formatDate($header['FECHA'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                <?php if (!empty($details)): ?>
                    <div class="table-responsive">
                        <table class="table table-row-dashed align-middle">
                            <thead class="text-muted fw-semibold">
                                <tr>
                                    <th>SKU</th>
                                    <th>Descripci&oacute;n</th>
                                    <th class="text-end">Cantidad recibida</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($details as $detail): ?>
                                    <tr>
                                        <td class="fw-semibold text-dark"><?= htmlspecialchars($detail['SKU'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($detail['DESCRIPCION'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td class="text-end"><?= number_format((float)($detail['CANTIDAD_RECIBIDA'] ?? 0), 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0">Sin partidas registradas para esta entrada.</p>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card card-flush border-0 shadow-sm">
    <div class="card-header">
        <h3 class="card-title fw-bold">Detalle vs recepci&oacute;n</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead class="text-muted fw-semibold">
                    <tr>
                        <th>SKU</th>
                        <th>Descripci&oacute;n</th>
                        <th class="text-end">Solicitado</th>
                        <th class="text-end">Recibido</th>
                        <th class="text-end">% Recibido</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                        <?php
                        $articleId = (int)($item['ID_ARTICULO'] ?? 0);
                        $requested = (float)($item['CANTIDAD_SOLICITADA'] ?? 0);
                        $received = (float)($receivedMap[$articleId] ?? 0);
                        $percent = $requested > 0 ? round(($received / $requested) * 100, 2) : 0;
                        $rowClass = '';
                        if ($percent >= 100) {
                            $rowClass = 'table-success';
                        } elseif ($percent >= 50) {
                            $rowClass = 'table-warning';
                        } else {
                            $rowClass = 'table-danger';
                        }
                        ?>
                        <tr class="<?= $rowClass ?>">
                            <td class="fw-semibold text-dark"><?= htmlspecialchars($item['SKU'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($item['DESCRIPCION'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-end"><?= number_format($requested, 2) ?></td>
                            <td class="text-end"><?= number_format($received, 2) ?></td>
                            <td class="text-end"><?= number_format($percent, 2) ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
