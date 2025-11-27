<?php
/** @var array $backordersConfig */
$config = $backordersConfig ?? [];
?>

<div class="card card-flush border-0 shadow-sm mb-6" id="backorders-app"
     data-config='<?= htmlspecialchars(json_encode($config, JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?>'>
    <div class="card-header flex-wrap gap-3 align-items-center">
        <div>
            <h3 class="card-title fw-bold mb-1">Backorders</h3>
            <span class="text-muted fs-8">
                Órdenes de compra con recepciones pendientes en los últimos 2 meses.
            </span>
        </div>

        <div class="d-flex flex-wrap gap-3 ms-auto">
            <div class="flex-grow-1">
                <label class="form-label fw-semibold mb-1">Buscar</label>
                <input type="search"
                       class="form-control"
                       placeholder="Folio, proveedor, número de proveedor, tienda, temporada..."
                       data-backorders-search>
            </div>

            <div>
                <label class="form-label fw-semibold mb-1">Por página</label>
                <select class="form-select" data-backorders-per-page>
                    <option value="10">10</option>
                    <option value="25" selected>25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
                </select>
            </div>

            <div class="align-self-end">
                <button type="button" class="btn btn-light" data-backorders-refresh>
                    <i class="fa-solid fa-rotate me-2"></i>Actualizar
                </button>
            </div>
        </div>
    </div>

    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div class="text-muted fs-8" data-backorders-summary>
                Cargando backorders...
            </div>
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-sm btn-light" data-backorders-prev disabled>
                    <i class="fa-solid fa-chevron-left me-1"></i> Anterior
                </button>
                <button type="button" class="btn btn-sm btn-light" data-backorders-next disabled>
                    Siguiente <i class="fa-solid fa-chevron-right ms-1"></i>
                </button>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-row-dashed align-middle mb-0">
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
                <tbody data-backorders-body>
                <tr>
                    <td colspan="7" class="text-center text-muted py-10">
                        Cargando backorders...
                    </td>
                </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
