<?php
$title = $title ?? 'Compras';
$description = $description ?? 'Modulo de compras';
?>
<div class="card card-flush border-0 shadow-sm">
    <div class="card-header pt-5">
        <h3 class="card-title fw-bold text-dark mb-1"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h3>
        <span class="text-muted"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <div class="card-body">
        <div class="alert alert-light border mb-0">
            <div class="d-flex align-items-start">
                <i class="fa-solid fa-truck-ramp-box text-primary fs-3 me-3"></i>
                <div>
                    <div class="fw-semibold text-dark mb-1">Estamos preparando este tablero.</div>
                    <div class="text-muted fs-8">En esta pantalla se mostraran indicadores de entradas, sell in y recepciones por almacen.</div>
                </div>
            </div>
        </div>
    </div>
</div>
