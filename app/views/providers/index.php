<?php
$providers = $providers ?? [];
$accessDenied = !empty($accessDenied);
$isSuperAdmin = !empty($isSuperAdmin);

$total = count($providers);
?>
<div class="card card-flush border-0 shadow-sm">
    <div class="card-header pt-5 d-flex flex-column flex-lg-row justify-content-between align-items-lg-center">
        <div>
            <h3 class="card-title fw-bold text-dark mb-1">Directorio de proveedores</h3>
            <span class="text-muted">Listado de proveedores asignados al usuario actual.</span>
        </div>
        <div class="card-toolbar d-flex gap-2 mt-4 mt-lg-0">
            <button class="btn btn-sm btn-light" type="button">
                <i class="fa-solid fa-download me-2"></i>Exportar
            </button>
        </div>
    </div>

    <div class="card-body">
        <?php if ($accessDenied): ?>
            <div class="alert alert-warning d-flex align-items-center mb-0">
                <i class="fa-solid fa-lock fs-3 me-3"></i>
                <div>
                    <div class="fw-semibold text-dark">Sin permisos suficientes</div>
                    <div class="text-muted fs-8">Solicita acceso al módulo de proveedores para visualizar esta sección.</div>
                </div>
            </div>
        <?php elseif ($total === 0): ?>
            <div class="alert alert-light border mb-0">
                <span class="fw-semibold text-dark">No hay proveedores vinculados.</span>
                <span class="text-muted fs-8">Cuando se asignen proveedores a tu usuario, aparecerán en este listado.</span>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle text-nowrap">
                    <thead>
                        <tr class="text-muted fw-semibold text-uppercase fs-8">
                            <th>#</th>
                            <th>Proveedor</th>
                            <th>Estado</th>
                            <th>Alta</th>
                            <th class="text-end">Usuarios vinculados</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($providers as $index => $provider): ?>
                            <tr>
                                <td class="text-muted"><?= $index + 1 ?></td>
                                <td>
                                    <div class="fw-semibold text-dark">
                                        <?= htmlspecialchars($provider['name'] ?? 'Proveedor', ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                    <div class="text-muted fs-8">
                                        <?= htmlspecialchars($provider['external_id'] !== null ? (string)$provider['external_id'] : '-', ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                </td>
                                <td>
                                    <?php $status = strtolower((string)($provider['status'] ?? '')); ?>
                                    <?php if ($status === 'active'): ?>
                                        <span class="badge badge-light-success">Activo</span>
                                    <?php elseif ($status === 'inactive'): ?>
                                        <span class="badge badge-light-danger">Inactivo</span>
                                    <?php else: ?>
                                        <span class="badge badge-light"><?= htmlspecialchars($status ?: 'N/D', ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted">
                                    <?php
                                        $activation = $provider['activation_date'] ?? null;
                                        $formatted = $activation ? date('d/m/Y', strtotime($activation)) : '-';
                                    ?>
                                    <?= htmlspecialchars($formatted, ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="text-end">
                                    <span class="badge badge-light-primary"><?= (int)$provider['linked_users'] ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
