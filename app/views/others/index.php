<?php
$page = $page ?? 'index';
$copy = [
    'index' => 'Centraliza accesos a herramientas adicionales como catalogos, formularios y manuales.',
    'returns' => 'Registra devoluciones pendientes y consulta conciliaciones.',
    'inventory' => 'Consulta disponibilidad y existencias por centro de distribucion.',
];
$message = $copy[$page] ?? 'Modulo en preparacion.';
?>
<div class="card card-flush border-0 shadow-sm">
    <div class="card-body py-6">
        <div class="alert alert-light-primary border-0">
            <div class="d-flex">
                <i class="fa-solid fa-lightbulb text-primary fs-2 me-3"></i>
                <div>
                    <div class="fw-bold text-dark">Muy pronto</div>
                    <div class="text-muted fs-8"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
                </div>
            </div>
        </div>
    </div>
</div>
