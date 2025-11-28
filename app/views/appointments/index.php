<?php
$filters        = $filters ?? [];
$appointments   = $appointments ?? [];
$stores         = $stores ?? [];
$flashStatus    = $_SESSION['appointments_status'] ?? null;
$flashError     = $_SESSION['appointments_error'] ?? null;
unset($_SESSION['appointments_status'], $_SESSION['appointments_error']);
?>
<div id="appointments-index-app"
     data-config='<?= json_encode([
         "createUrl" => ($basePath !== '' ? $basePath : '') . "/citas/crear-borrador-cita",
     ], JSON_UNESCAPED_SLASHES) ?>'>

    <?php if ($flashStatus): ?>
        <div class="alert alert-success d-flex align-items-center mb-6">
            <i class="fa-solid fa-circle-check me-2"></i>
            <span><?= htmlspecialchars($flashStatus, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    <?php endif; ?>

    <?php if ($flashError): ?>
        <div class="alert alert-danger d-flex align-items-center mb-6">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>
            <span><?= htmlspecialchars($flashError, ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    <?php endif; ?>

    <div class="d-flex flex-wrap align-items-center justify-content-between mb-6">
        <div>
            <h1 class="fs-2hx fw-bold mb-1">Citas de proveedor</h1>
            <div class="text-muted fs-7">
                Consulta y genera citas de entrega por punto de entrega.
            </div>
        </div>
        <div>
            <button type="button"
                    class="btn btn-primary"
                    data-new-appointment>
                <i class="fa-solid fa-plus me-2"></i>Nueva cita
            </button>
        </div>
    </div>

    <div class="card card-flush border-0 shadow-sm py-3">
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
                        <option value="">Todos</option>
                        <?php
                        $statusOptions = [
                            'draft'      => 'Borrador',
                            'in_process' => 'En proceso',
                            'accepted'   => 'Aceptada',
                            'rejected'   => 'Rechazada',
                            'cancelled'  => 'Cancelada',
                            'delivered'  => 'Entregada',
                        ];
                        foreach ($statusOptions as $value => $label):
                        ?>
                            <option value="<?= $value ?>"
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
                            <?php foreach ($appointments as $a): ?>
                                <tr>
                                    <td class="fw-bold text-dark">
                                        <a href="<?= ($basePath !== '' ? $basePath : '') . '/citas/' . (int)$a['id'] . '/editar' ?>">
                                            <?= htmlspecialchars($a['folio'] ?? ('CITA-' . $a['id']), ENT_QUOTES, 'UTF-8') ?>
                                        </a>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($a['provider_name'] ?? 'N/D', ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-muted small">ID <?= (int)$a['provider_id'] ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($a['delivery_point_name'] ?? 'Sin asignar', ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-muted small"><?= htmlspecialchars($a['delivery_point_code'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                    </td>
                                    <td>
                                        <div><?= htmlspecialchars($a['appointment_date'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-muted small">
                                            <?= htmlspecialchars(($a['slot_start'] ?? '') . ' - ' . ($a['slot_end'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?= (int)($a['documents_count'] ?? 0) ?> docs</div>
                                        <div class="text-muted small">
                                            Solicitado: $<?= number_format((float)($a['total_requested'] ?? 0), 2) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-light">
                                            <?= htmlspecialchars($a['status'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($a['created_by_username'] ?? 'N/D', ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-muted small"><?= htmlspecialchars($a['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= ($basePath !== '' ? $basePath : '') . '/citas/' . (int)$a['id'] . '/editar' ?>"
                                           class="btn btn-sm btn-light-primary">
                                            <i class="fa-solid fa-pen-to-square me-1"></i>Ver / editar
                                        </a>
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
</div>

<!-- MODAL: NUEVA CITA (BORRADOR) -->
<div class="modal fade" id="newAppointmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form id="newAppointmentForm" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Nueva cita</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Punto de entrega</label>
                    <select name="delivery_point_code" class="form-select" required>
                        <option value="">Selecciona una tienda…</option>
                        <?php foreach ($stores as $s): ?>
                            <option value="<?= (int)$s['ID_TIENDA'] ?>">
                                <?= htmlspecialchars($s['NOMBRE_CORTO'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    Crear cita y continuar
                </button>
            </div>
        </form>
    </div>
</div>
