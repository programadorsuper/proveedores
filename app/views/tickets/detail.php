<div class="card">
    <div class="card-header">
        <h3 class="card-title">Ticket #<?php echo (int)($ticket['ticket_id'] ?? 0); ?></h3>
        <div class="card-toolbar">
            <span class="badge bg-light text-dark me-2">
                Serie: <?php echo htmlspecialchars($ticket['series'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <span class="badge bg-light text-dark">
                Folio: <?php echo htmlspecialchars($ticket['folio'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
            </span>
        </div>
    </div>
    <div class="card-body">
        <div class="row mb-5">
            <div class="col-md-3">
                <label class="form-label text-muted">Fecha</label>
                <div><?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($ticket['ticket_date'] ?? 'now')), ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted">Tienda</label>
                <div><?php echo htmlspecialchars($ticket['store_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted">Cliente</label>
                <div><?php echo htmlspecialchars($ticket['customer_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="col-md-3">
                <label class="form-label text-muted">Total</label>
                <div>$<?php echo number_format((float)($ticket['net_sales'] ?? 0), 2); ?></div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Descripcion</th>
                        <th>Cantidad</th>
                        <th>Precio</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($ticket['items'])): ?>
                    <?php foreach ($ticket['items'] as $item): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['sku'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo htmlspecialchars($item['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo number_format((float)($item['qty'] ?? 0), 2); ?></td>
                            <td>$<?php echo number_format((float)($item['price'] ?? 0), 2); ?></td>
                            <td>$<?php echo number_format((float)($item['total'] ?? 0), 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-10">Sin partidas para este ticket.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
