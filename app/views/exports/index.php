<div class="card">
    <div class="card-header">
        <h3 class="card-title">Exportaciones</h3>
    </div>
    <div class="card-body">
        <p class="text-muted">Configura exportes de Sell-out, inventario y tickets. Esta seccion se conectara con los jobs de programacion.</p>
        <form method="post" action="<?php echo htmlspecialchars(($basePath ?? '') . '/exportaciones/generar', ENT_QUOTES, 'UTF-8'); ?>">
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Tipo</label>
                    <select name="type" class="form-select">
                        <option value="sellout">Sell-out</option>
                        <option value="inventory">Inventario</option>
                        <option value="tickets">Tickets</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Formato</label>
                    <select name="format" class="form-select">
                        <option value="csv">CSV</option>
                        <option value="xlsx">XLSX</option>
                        <option value="xml">XML</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary">Programar exporte</button>
                </div>
            </div>
        </form>
    </div>
</div>
