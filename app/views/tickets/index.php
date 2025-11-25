<div class="card">
    <div class="card-header">
        <h3 class="card-title">Buscador de tickets</h3>
        <div class="card-toolbar">
            <span class="badge bg-light text-dark">
                Membresia: <?php echo htmlspecialchars($membershipPlan ?? 'free', ENT_QUOTES, 'UTF-8'); ?>
            </span>
        </div>
    </div>
    <div class="card-body">
        <form id="ticket-search-form" class="row g-3" method="get" action="">
            <div class="col-md-4">
                <label class="form-label">Serie / Folio / Ticket ID</label>
                <input type="text" name="query" value="<?php echo htmlspecialchars($filters['query'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="form-control" placeholder="Serie, folio o id">
            </div>
            <div class="col-md-3">
                <label class="form-label">Desde</label>
                <input type="date" name="start_date" value="<?php echo htmlspecialchars($filters['start_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label">Hasta</label>
                <input type="date" name="end_date" value="<?php echo htmlspecialchars($filters['end_date'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="form-control">
            </div>
            <div class="col-md-2 d-flex align-items-end justify-content-between">
                <button type="submit" class="btn btn-primary w-100 me-2">Buscar</button>
            </div>
        </form>
    </div>
</div>

<div class="card mt-5">
    <div class="card-body table-responsive">
        <table class="table table-striped align-middle">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Serie</th>
                    <th>Folio</th>
                    <th>Ticket ID</th>
                    <th>Tienda</th>
                    <th>Cliente</th>
                    <th>Total</th>
                    <th>Estatus</th>
                    <th>Puntos</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($tickets)): ?>
                <?php foreach ($tickets as $ticket): ?>
                    <tr>
                        <td><?php echo htmlspecialchars(date('Y-m-d', strtotime($ticket['ticket_date'] ?? 'now')), ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($ticket['series'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($ticket['folio'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo (int)($ticket['ticket_id'] ?? 0); ?></td>
                        <td><?php echo htmlspecialchars($ticket['store_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo htmlspecialchars($ticket['customer_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td>$<?php echo number_format((float)($ticket['net_sales'] ?? 0), 2); ?></td>
                        <td>
                            <?php
                                $status = $ticket['status'] ?? 'pendiente';
                                $badge = $status === 'reviewed' ? 'badge badge-light-success' : 'badge badge-light-warning';
                            ?>
                            <span class="<?php echo $badge; ?>"><?php echo htmlspecialchars($status, ENT_QUOTES, 'UTF-8'); ?></span>
                        </td>
                        <td><?php echo number_format((float)($ticket['total_points'] ?? 0), 2); ?></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-light" href="<?php echo htmlspecialchars(($basePath ?? '') . '/tickets/detalle?ticket_id=' . (int)$ticket['ticket_id'], ENT_QUOTES, 'UTF-8'); ?>">
                                Ver detalle
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="10" class="text-center text-muted py-10">Sin tickets para los filtros seleccionados.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
