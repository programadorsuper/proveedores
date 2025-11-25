<?php

if (!empty($_SESSION['appointments_status'])) {
    $flashStatus = $_SESSION['appointments_status'];
    unset($_SESSION['appointments_status']);
}
if (!empty($_SESSION['appointments_error'])) {
    $flashError = $_SESSION['appointments_error'];
    unset($_SESSION['appointments_error']);
}

$appointments        = $appointments ?? [];
$filters             = $filters ?? [];
$stores              = $stores ?? [];
$selectedStoreId     = $selectedStoreId ?? 0;
$selectedSeasonType  = $selectedSeasonType ?? '';
$availableOrders     = $availableOrders ?? [];

$statusOptions = [
    ''           => 'Todos',
    'in_process' => 'En proceso',
    'accepted'   => 'Aceptada',
    'rejected'   => 'Rechazada',
    'cancelled'  => 'Cancelada',
    'delivered'  => 'Entregada',
];

$formatStatus = static function (string $status): string {
    return match ($status) {
        'in_process' => '<span class="badge badge-light-warning">En proceso</span>',
        'accepted'   => '<span class="badge badge-light-success">Aceptada</span>',
        'rejected'   => '<span class="badge badge-light-danger">Rechazada</span>',
        'cancelled'  => '<span class="badge badge-light">Cancelada</span>',
        'delivered'  => '<span class="badge badge-light-primary">Entregada</span>',
        default      => '<span class="badge badge-light">' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</span>',
    };
};
?>

<?php if (!empty($flashStatus)): ?>
    <div class="alert alert-success d-flex align-items-center mb-6">
        <i class="fa-solid fa-circle-check me-2"></i>
        <span><?= htmlspecialchars($flashStatus, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
<?php endif; ?>

<?php if (!empty($flashError)): ?>
    <div class="alert alert-danger d-flex align-items-center mb-6">
        <i class="fa-solid fa-triangle-exclamation me-2"></i>
        <span><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
<?php endif; ?>

<!-- HEADER + BOTÓN NUEVA CITA -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-6">
    <div>
        <h1 class="fs-2hx fw-bold mb-1">Citas de proveedor</h1>
        <div class="text-muted fs-7">
            Genera citas a partir de órdenes nuevas sin entradas de almacén.
        </div>
    </div>
    <div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newAppointmentFiltersModal">
            <i class="fa-solid fa-plus me-2"></i>Nueva cita
        </button>
    </div>
</div>

<!-- MODAL: FILTROS PRINCIPALES (TIENDA + TIPO) -->
<div class="modal fade" id="newAppointmentFiltersModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Nueva cita – seleccionar órdenes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted fs-7 mb-4">
                    1) Elige la <strong>tienda de consignación</strong> y el <strong>tipo de temporada/orden</strong>.<br>
                    2) Da clic en <strong>Filtrar órdenes</strong> para ver las órdenes nuevas disponibles.<br>
                    3) En cada orden podrás ver el resumen y subir XML/PDF.
                </p>

                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Tienda de consignación</label>
                        <select id="filterStoreId" class="form-select">
                            <option value="">Selecciona una tienda…</option>
                            <?php foreach ($stores as $store): ?>
                                <option value="<?= (int)$store['ID_TIENDA'] ?>">
                                    <?= htmlspecialchars($store['NOMBRE_CORTO'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Tipo de temporada / orden</label>
                        <select id="filterSeasonType" class="form-select">
                            <option value="">Selecciona el tipo…</option>
                            <option value="special">Especial – temporada ID 3 (alias S)</option>
                            <option value="normal">General – todas excepto especial (ID 3)</option>
                        </select>
                    </div>

                    <div class="col-md-2 d-flex align-items-end">
                        <button type="button" id="btnFilterOrders" class="btn btn-primary w-100">
                            <i class="fa-solid fa-filter me-2"></i>Filtrar
                        </button>
                    </div>
                </div>

                <div id="ordersAjaxAlert" class="alert d-none mb-4"></div>

                <div class="border rounded p-3" style="max-height: 420px; overflow:auto;">
                    <table class="table table-row-dashed align-middle mb-0">
                        <thead class="text-muted fw-semibold">
                        <tr>
                            <th style="width:40px;"></th>
                            <th>Folio</th>
                            <th>Punto de entrega</th>
                            <th>Alias</th>
                            <th>Proveedor</th>
                            <th>Fecha</th>
                            <th class="text-end">Total</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                        </thead>
                        <tbody id="ordersAjaxTbody">
                        <tr>
                            <td colspan="8" class="text-center text-muted py-8">
                                Usa los filtros de arriba para cargar órdenes nuevas.
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3 text-muted fs-8">
                    <strong>Nota:</strong> sólo se listan órdenes sin entradas de almacén, autorizadas,
                    de los últimos 2 meses, y que no están reservadas en otra cita.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                    Continuar con la cita
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="orderFilesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <form id="orderFilesForm" method="post" enctype="multipart/form-data"
                  action="<?= htmlspecialchars(($basePath !== '' ? $basePath : '') . '/citas/orden-archivos', ENT_QUOTES, 'UTF-8') ?>">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">Resumen de orden y archivos</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="id_compra" id="orderFilesIdCompra">

                    <div id="orderSummaryContainer">
                        <!-- Aquí JS inyecta el resumen de la orden -->
                        <p class="text-muted mb-4">Cargando información de la orden…</p>
                    </div>

                    <div class="mt-4">
                        <label class="form-label fw-semibold">Archivos XML / PDF</label>
                        <input type="file" name="files[]" id="orderFilesInput" class="form-control" multiple
                               accept=".xml,.XML,.pdf,.PDF">
                        <div class="text-muted fs-8 mt-1">
                            Puedes subir uno o varios XML y PDFs relacionados con esta orden.
                        </div>
                    </div>

                    <div id="orderFilesUploadAlert" class="alert d-none mt-4"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-upload me-2"></i>Guardar archivos y marcar orden para la cita
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ÓRDENES DISPONIBLES PARA CITA -->
<div class="card card-flush border-0 shadow-sm mb-6">
    <div class="card-header flex-wrap gap-3 align-items-center">
        <div>
            <h3 class="card-title fw-bold mb-1">Órdenes disponibles para cita</h3>
            <span class="text-muted fs-8">
                Se muestran órdenes sin entradas de almacén, autorizadas y dentro de los últimos 2 meses,
                según la tienda y tipo de temporada seleccionados en el modal.
            </span>
        </div>
        <div class="ms-auto text-end">
            <?php if ($selectedStoreId > 0): ?>
                <div class="text-muted fs-8">
                    <strong>Tienda:</strong>
                    <?php
                    $storeName = 'N/D';
                    foreach ($stores as $store) {
                        if ((int)$store['ID_TIENDA'] === $selectedStoreId) {
                            $storeName = $store['NOMBRE_CORTO'];
                            break;
                        }
                    }
                    ?>
                    <?= htmlspecialchars($storeName, ENT_QUOTES, 'UTF-8') ?>
                    <span class="mx-2">|</span>
                    <strong>Tipo:</strong>
                    <?= $selectedSeasonType === 'special'
                        ? 'Especial (ID temporada 3)'
                        : ($selectedSeasonType === 'normal' ? 'General (sin especial)' : '—') ?>
                </div>
            <?php else: ?>
                <span class="text-muted fs-8">Abre el botón “Nueva cita” para elegir tienda y tipo de orden.</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body">
        <?php if ($selectedStoreId <= 0): ?>
            <p class="text-muted mb-0">
                Aún no has seleccionado tienda ni tipo de temporada. Haz clic en <strong>Nueva cita</strong> para comenzar.
            </p>
        <?php elseif (!empty($availableOrders)): ?>
            <div class="table-responsive">
                <table class="table table-row-dashed align-middle">
                    <thead class="text-muted fw-semibold">
                    <tr>
                        <th></th>
                        <th>Folio</th>
                        <th>Punto de entrega</th>
                        <th>Alias</th>
                        <th>Proveedor</th>
                        <th>Fecha</th>
                        <th class="text-end">Total</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($availableOrders as $order): ?>
                        <tr>
                            <td>
                                <input
                                    type="checkbox"
                                    class="form-check-input"
                                    name="order_ids[]"
                                    value="<?= (int)$order['ID_COMPRA'] ?>"
                                    form="newAppointmentForm"
                                >
                            </td>
                            <td>
                                <div class="fw-bold text-dark">#<?= htmlspecialchars($order['FOLIO'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="text-muted small">ID <?= (int)$order['ID_COMPRA'] ?></div>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($order['NOMBRE_CORTO'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($order['LUGAR_ENTREGA'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                            <td><?= htmlspecialchars('[' . ($order['ALIAS'] ?? '') . '] ' . ($order['TEMPORADA'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($order['RAZON_SOCIAL'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="text-muted small">Proveedor <?= htmlspecialchars($order['NUMERO_PROVEEDOR'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                            <td><?= htmlspecialchars(substr((string)($order['FECHA'] ?? ''), 0, 19), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="text-end fw-bold">$ <?= number_format((float)($order['TOTAL'] ?? 0), 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <div class="text-muted fs-8 mt-2">
                    Sólo se muestran órdenes nuevas sin entradas de almacén y dentro de los últimos 2 meses.
                </div>
            </div>
        <?php else: ?>
            <p class="text-muted mb-0">
                No encontramos órdenes nuevas para la tienda y tipo seleccionados, o ya están reservadas en otra cita.
            </p>
        <?php endif; ?>
    </div>
</div>

<!-- NUEVA CITA: FORM (USA LAS ÓRDENES SELECCIONADAS ARRIBA) -->
<div class="card card-flush border-0 shadow-sm mb-6">
    <div class="card-header flex-wrap gap-3 align-items-center">
        <div>
            <h3 class="card-title fw-bold mb-1">Datos de la nueva cita</h3>
            <span class="text-muted fs-8">
                Selecciona las órdenes en la tabla superior y captura fecha y horario para crear la cita.
            </span>
        </div>
    </div>
    <div class="card-body">
        <form id="newAppointmentForm" method="post"
              action="<?= htmlspecialchars(($basePath !== '' ? $basePath : '') . '/citas/crear', ENT_QUOTES, 'UTF-8') ?>"
              class="row g-3">
            <?php if (!empty($user['is_super_admin'])): ?>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Proveedor (ID)</label>
                    <input type="number" name="provider_id" class="form-control" placeholder="ID proveedor" min="1">
                    <small class="text-muted fs-8">Si lo dejas vacío se usará el proveedor asociado al usuario.</small>
                </div>
            <?php endif; ?>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Fecha</label>
                <input type="date" name="appointment_date" class="form-control" required min="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Hora inicio (08:00–15:00)</label>
                <input type="time" name="slot_start" class="form-control" required min="08:00" max="15:00" value="08:00">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Hora fin</label>
                <input type="time" name="slot_end" class="form-control" required min="08:00" max="15:00" value="09:00">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Punto de entrega (opcional)</label>
                <input type="text" name="delivery_point_name" class="form-control"
                       placeholder="Si se deja vacío se tomará del primer documento seleccionado.">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Código punto (opcional)</label>
                <input type="text" name="delivery_point_code" class="form-control"
                       placeholder="Si se deja vacío se tomará del primer documento.">
            </div>
            <div class="col-md-12">
                <label class="form-label fw-semibold">Dirección / nota</label>
                <textarea name="delivery_address" rows="2" class="form-control"
                          placeholder="Dirección, referencias..."></textarea>
            </div>
            <div class="col-12">
                <div class="alert alert-warning d-flex align-items-center">
                    <i class="fa-solid fa-circle-exclamation me-2"></i>
                    <div class="fs-8">
                        La cita sólo se puede crear si todas las órdenes seleccionadas son del mismo punto de entrega.
                        Si alguna orden es especial (alias S / temporada 3), todas las órdenes de la cita deben ser especiales.
                    </div>
                </div>
            </div>
            <div class="col-12">
                <button class="btn btn-primary" type="submit">
                    <i class="fa-solid fa-paper-plane me-2"></i>Guardar cita
                </button>
            </div>
        </form>
    </div>
</div>

<!-- LISTADO DE CITAS -->
<div class="card card-flush border-0 shadow-sm">
    <div class="card-header">
        <form method="get" class="d-flex flex-wrap gap-3 align-items-center w-100">
            <div class="flex-grow-1">
                <label class="form-label fw-semibold mb-1">Buscar</label>
                <input type="text" name="search"
                       value="<?= htmlspecialchars($filters['search'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       class="form-control" placeholder="Folio, punto de entrega...">
            </div>
            <div>
                <label class="form-label fw-semibold mb-1">Estatus</label>
                <select name="status" class="form-select">
                    <?php foreach ($statusOptions as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"
                            <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
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
                        <th>Punto de entrega</th>
                        <th>Fecha / horario</th>
                        <th>Documentos</th>
                        <th>Estatus</th>
                        <th>Creado por</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($appointments)): ?>
                    <?php foreach ($appointments as $appointment): ?>
                        <tr>
                            <td class="fw-bold text-dark">
                                <?= htmlspecialchars($appointment['folio'] ?? ('CITA-' . $appointment['id']), ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($appointment['provider_name'] ?? 'N/D', ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="text-muted small">ID <?= (int)$appointment['provider_id'] ?></div>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($appointment['delivery_point_name'] ?? 'Sin asignar', ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($appointment['delivery_point_code'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                            <td>
                                <div><?= htmlspecialchars($appointment['appointment_date'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="text-muted small">
                                    <?= htmlspecialchars(($appointment['slot_start'] ?? '') . ' - ' . ($appointment['slot_end'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </td>
                            <td>
                                <div class="fw-bold"><?= (int)($appointment['documents_count'] ?? 0) ?> docs</div>
                                <div class="text-muted small">
                                    Solicitado: $<?= number_format((float)($appointment['total_requested'] ?? 0), 2) ?>
                                </div>
                            </td>
                            <td><?= $formatStatus((string)($appointment['status'] ?? '')) ?></td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($appointment['created_by_username'] ?? 'N/D', ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($appointment['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                            <td class="text-end">
                                <?php if (($appointment['status'] ?? '') === 'in_process'): ?>
                                    <form method="post"
                                          action="<?= htmlspecialchars(($basePath !== '' ? $basePath : '') . '/citas/cancelar', ENT_QUOTES, 'UTF-8') ?>"
                                          class="d-inline">
                                        <input type="hidden" name="id" value="<?= (int)$appointment['id'] ?>">
                                        <button class="btn btn-sm btn-light-danger" type="submit">
                                            <i class="fa-solid fa-ban me-1"></i>Cancelar
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <span class="text-muted small">Sin acciones</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-10">Sin citas registradas.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
