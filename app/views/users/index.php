<?php
$users = $users ?? [];
$isSuperAdmin = !empty($isSuperAdmin);
$accessDenied = !empty($accessDenied);

$totalUsers = count($users);
$activeUsers = array_reduce($users, function ($carry, $row) {
    return $carry + (!empty($row['is_active']) ? 1 : 0);
}, 0);
$inactiveUsers = $totalUsers - $activeUsers;

$formatDate = static function (?string $value): string {
    if (empty($value)) {
        return '-';
    }
    try {
        $dt = new DateTimeImmutable($value);
        return $dt->format('d/m/Y H:i');
    } catch (Exception $exception) {
        return (string)$value;
    }
};
?>
<div class="card card-flush border-0 shadow-sm">
    <div class="card-header pt-5 d-flex flex-column flex-lg-row justify-content-between align-items-lg-center">
        <div>
            <h3 class="card-title fw-bold text-dark mb-1">Administracion de usuarios</h3>
            <span class="text-muted">Gestiona accesos por proveedor, rol y responsable.</span>
        </div>
        <div class="card-toolbar d-flex gap-2 mt-4 mt-lg-0">
            <button class="btn btn-sm btn-light-primary" type="button">
                <i class="fa-solid fa-user-plus me-2"></i>Nuevo usuario
            </button>
            <button class="btn btn-sm btn-light" type="button">
                <i class="fa-solid fa-download me-2"></i>Exportar
            </button>
        </div>
    </div>

    <div class="card-body">
        <?php if ($accessDenied): ?>
            <div class="alert alert-warning d-flex align-items-center">
                <i class="fa-solid fa-lock fs-3 me-3"></i>
                <div>
                    <div class="fw-semibold text-dark">Sin permisos suficientes</div>
                    <div class="text-muted fs-8">Solicita al super administrador que te habilite el acceso al modulo de usuarios.</div>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-5 mb-6">
                <div class="col-sm-4">
                    <div class="border rounded px-4 py-3">
                        <div class="text-muted fs-8">Usuarios registrados</div>
                        <div class="fw-bold fs-3 text-dark"><?= number_format($totalUsers) ?></div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="border rounded px-4 py-3">
                        <div class="text-muted fs-8">Activos</div>
                        <div class="fw-bold fs-3 text-success"><?= number_format($activeUsers) ?></div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="border rounded px-4 py-3">
                        <div class="text-muted fs-8">Inactivos</div>
                        <div class="fw-bold fs-3 text-danger"><?= number_format($inactiveUsers) ?></div>
                    </div>
                </div>
            </div>

            <?php if ($totalUsers === 0): ?>
                <div class="alert alert-light border mb-0">
                    <span class="fw-semibold text-dark">Aun no hay usuarios capturados.</span>
                    <span class="text-muted fs-8">Cuando registres usuarios se mostraran aqui de forma automatica.</span>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle text-nowrap">
                        <thead>
                            <tr class="text-muted fw-semibold text-uppercase fs-8">
                                <th>#</th>
                                <th>Usuario</th>
                                <th>Roles</th>
                                <?php if ($isSuperAdmin): ?>
                                    <th>Proveedor</th>
                                <?php endif; ?>
                                <th>Alta por</th>
                                <th>Fecha alta</th>
                                <th class="text-center">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $index => $row): ?>
                                <?php
                                    $roles = $row['roles'] ?? [];
                                    $rolesLabel = !empty($roles) ? implode(', ', $roles) : 'Sin rol';
                                ?>
                                <tr>
                                    <td class="text-muted"><?= $index + 1 ?></td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($row['username'] ?? '-', ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-muted fs-8">ID <?= (int)($row['id'] ?? 0) ?></div>
                                    </td>
                                    <td>
                                        <span class="badge badge-light-primary fw-semibold"><?= htmlspecialchars($rolesLabel, ENT_QUOTES, 'UTF-8') ?></span>
                                    </td>
                                    <?php if ($isSuperAdmin): ?>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= htmlspecialchars($row['provider_name'] ?? 'Sin asignar', ENT_QUOTES, 'UTF-8') ?></div>
                                            <div class="text-muted fs-8"><?= htmlspecialchars((string)($row['provider_code'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                        </td>
                                    <?php endif; ?>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($row['parent_username'] ?? '—', ENT_QUOTES, 'UTF-8') ?></div>
                                    </td>
                                    <td class="text-muted"><?= htmlspecialchars($formatDate($row['created_at'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-center">
                                        <?php if (!empty($row['is_active'])): ?>
                                            <span class="badge badge-light-success">Activo</span>
                                        <?php else: ?>
                                            <span class="badge badge-light-danger">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
