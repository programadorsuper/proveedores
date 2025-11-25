<?php
$page = $page ?? 'index';
$ordersConfig = $ordersConfig ?? [];
$filters = $filters ?? ['days' => 30, 'per_page' => 25];
$baseIconMap = [
    'index' => 'fa-list-check',
    'nuevas' => 'fa-bolt',
    'backorder' => 'fa-circle-exclamation',
    'entradas' => 'fa-people-carry-box'
];
$messages = [
    'index' => 'Visualiza el pipeline completo por estatus y prioridad.',
    'nuevas' => 'Gestiona ordenes recibidas recientemente, revisa si ya fueron vistas y descarga la documentacion.',
    'backorder' => 'Dale seguimiento a faltantes y promesas de surtido.',
    'entradas' => 'Confirma recepciones y comprobantes en almacen.',
];
$icon = $baseIconMap[$page] ?? 'fa-list-check';
$description = $messages[$page] ?? 'Seguimiento operativo de ordenes.';
$isNuevas = $page === 'nuevas';

if ($isNuevas) {
    $configPayload = [
        'endpoints' => [
            'list' => $ordersConfig['listEndpoint'] ?? '',
            'markSeen' => $ordersConfig['markSeenEndpoint'] ?? '',
            'export' => $ordersConfig['exportEndpoint'] ?? '',
            'detail' => $ordersConfig['detailEndpoint'] ?? '',
        ],
        'filters' => [
            'page' => 1,
            'perPage' => (int)($filters['per_page'] ?? 25),
            'days' => (int)($filters['days'] ?? 30),
            'search' => '',
        ],
    ];
    $configJson = json_encode($configPayload, JSON_UNESCAPED_SLASHES);
}
?>

<?php if ($isNuevas): ?>
    <div class="card card-flush border-0 shadow-sm" id="orders-nuevas-app"
         data-config='<?= htmlspecialchars($configJson ?: "{}", ENT_QUOTES, 'UTF-8') ?>'>
        <div class="card-header flex-wrap gap-3 align-items-center">
            <div>
                <h3 class="card-title fw-bold mb-1">Ordenes nuevas</h3>
                <span class="text-muted fs-8">Filtra por rango de dias y busca por folio, proveedor o tienda.</span>
            </div>
            <div class="ms-auto d-flex flex-wrap gap-3">
                <div class="position-relative">
                    <i class="fa-solid fa-magnifying-glass text-muted position-absolute top-50 start-0 translate-middle-y ms-3"></i>
                    <input type="search" class="form-control ps-10" placeholder="Buscar folio, proveedor, tienda" data-orders-search>
                </div>
                <select class="form-select w-auto" data-orders-days>
                    <?php foreach ([7 => '7 dias', 15 => '15 dias', 30 => '30 dias', 60 => '60 dias'] as $value => $label): ?>
                        <option value="<?= (int)$value ?>" <?= (int)$filters['days'] === (int)$value ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select class="form-select w-auto" data-orders-per-page>
                    <?php foreach ([10, 25, 50] as $per): ?>
                        <option value="<?= $per ?>" <?= (int)$filters['per_page'] === $per ? 'selected' : '' ?>>
                            <?= $per ?> por pagina
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="button" class="btn btn-light" data-orders-refresh>
                    <i class="fa-solid fa-rotate-right me-2"></i>Actualizar
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-row-dashed align-middle" data-orders-table>
                    <thead class="text-muted fw-semibold">
                        <tr>
                            <th>Folio</th>
                            <th>Proveedor</th>
                            <th>Tienda</th>
                            <th>Fecha</th>
                            <th class="text-end">Importe</th>
                            <th class="text-center">Credito</th>
                            <th>Lugar de entrega</th>
                            <th>Visto</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-10">
                                Cargando ordenes...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="d-flex flex-wrap justify-content-between align-items-center mt-4 gap-3">
                <div class="text-muted small" data-orders-summary>Mostrando 0 registros</div>
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-light" data-orders-prev>
                        <i class="fa-solid fa-angle-left me-2"></i>Anterior
                    </button>
                    <button type="button" class="btn btn-light" data-orders-next>
                        Siguiente<i class="fa-solid fa-angle-right ms-2"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="card card-flush border-0 shadow-sm">
        <div class="card-body py-10 d-flex flex-column align-items-center">
            <i class="fa-solid <?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?> text-primary fs-1 mb-4"></i>
            <h2 class="fw-bold text-dark mb-2">Seccion en construccion</h2>
            <p class="text-muted text-center mb-0" style="max-width: 420px;">
                <?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?>
            </p>
        </div>
    </div>
<?php endif; ?>
