<?php
$backorders = $backorders ?? [];
$filters = $filters ?? ['months' => 2];
$monthsOptions = [1 => '1 mes', 2 => '2 meses', 3 => '3 meses', 6 => '6 meses'];
$baseUrl = $basePath ?? '';
?>

<div class="card card-flush border-0 shadow-sm mb-6">
    <div class="card-header flex-wrap gap-3 align-items-center">
        <div>
            <h3 class="card-title fw-bold mb-1">Backorders</h3>
            <span class="text-muted fs-8">Ordenes con recepciones pendientes en los &uacute;ltimos meses.</span>
        </div>
        <form method="get" class="d-flex flex-wrap gap-3 ms-auto">
            <div>
                <label class="form-label fw-semibold mb-1">Periodo</label>
                <select name="months" class="form-select">
                    <?php foreach ($monthsOptions as $value => $label): ?>
                        <option value="<?= (int)$value ?>" <?= ((int)($filters['months'] ?? 2)) === $value ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex-grow-1">
                <label class="form-label fw-semibold mb-1">Buscar</label>
                <input type="search" name="search" value="<?= htmlspecialchars($filters['search'] ?? '', ENT_QUOTES, 'UTF-8') ?>" class="form-control" placeholder="Folio, proveedor, tienda">
            </div>
            <div class="align-self-end">
                <button class="btn btn-light">
                    <i class="fa-solid fa-filter me-2"></i>Aplicar
                </button>
            </div>
        </form>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-row-dashed align-middle">
                <thead class="text-muted fw-semibold">
                    <tr>
                        <th>Folio</th>
                        <th>Proveedor</th>
                        <th>Punto entrega</th>
                        <th>Temporada</th>
                        <th>Fecha</th>
                        <th>Avance</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($backorders)): ?>
                    <?php foreach ($backorders as $row): ?>
                        <?php
                        $percent = (float)($row['PERCENT_RECEIVED'] ?? 0);
                        $alias = trim((string)($row['ALIAS'] ?? ''));
                        $temporada = trim((string)($row['TEMPORADA'] ?? ''));
                        $seasonLabel = $alias !== '' ? '[' . $alias . '] ' . $temporada : $temporada;
                        ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark">#<?= htmlspecialchars($row['FOLIO'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="text-muted small">ID <?= (int)$row['ID_COMPRA'] ?></div>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($row['RAZON_SOCIAL'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="text-muted small">Proveedor <?= htmlspecialchars($row['NUMERO_PROVEEDOR'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                            <td><?= htmlspecialchars($row['NOMBRE_CORTO'] ?? 'N/D', ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($seasonLabel, ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars(substr((string)($row['FECHA'] ?? ''), 0, 10), ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="progress w-150px">
                                        <div class="progress-bar <?= $percent >= 90 ? 'bg-success' : 'bg-warning' ?>" role="progressbar" style="width: <?= min(100, $percent) ?>%;"></div>
                                    </div>
                                    <span class="fw-semibold"><?= number_format($percent, 2) ?>%</span>
                                </div>
                                <div class="text-muted small">Pendiente: <?= number_format((float)($row['PENDING_TOTAL'] ?? 0), 2) ?></div>
                            </td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-light-primary" href="<?= htmlspecialchars(($baseUrl !== '' ? $baseUrl : '') . '/ordenes/backorder/detalle?id=' . (int)$row['ID_COMPRA'], ENT_QUOTES, 'UTF-8') ?>">
                                    <i class="fa-solid fa-eye me-1"></i>Ver detalle
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center text-muted py-10">Sin backorders para el periodo seleccionado.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
