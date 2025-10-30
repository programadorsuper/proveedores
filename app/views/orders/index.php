<?php
$page = $page ?? 'index';
$iconMap = [
    'index' => 'fa-list-check',
    'nuevas' => 'fa-bolt',
    'backorder' => 'fa-circle-exclamation',
    'entradas' => 'fa-people-carry-box'
];
$messages = [
    'index' => 'Visualiza el pipeline completo por estatus y prioridad.',
    'nuevas' => 'Gestiona ordenes recibidas en las ultimas 24 horas.',
    'backorder' => 'Dale seguimiento a faltantes y promesas de surtido.',
    'entradas' => 'Confirma recepciones y comprobantes en almacen.',
];
$icon = $iconMap[$page] ?? 'fa-list-check';
$description = $messages[$page] ?? 'Seguimiento operativo de ordenes.';
?>
<div class="card card-flush border-0 shadow-sm">
    <div class="card-body py-10 d-flex flex-column align-items-center">
        <i class="fa-solid <?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?> text-primary fs-1 mb-4"></i>
        <h2 class="fw-bold text-dark mb-2">Seccion en construccion</h2>
        <p class="text-muted text-center mb-0" style="max-width: 420px;">
            <?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?>
        </p>
    </div>
</div>
