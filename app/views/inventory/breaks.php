<div class="card">
    <div class="card-header">
        <h3 class="card-title">Quiebres y alertas</h3>
    </div>
    <div class="card-body table-responsive">
        <table class="table table-sm table-striped align-middle">
            <thead>
                <tr>
                    <th>Tienda</th>
                    <th>Producto</th>
                    <th>Existencia</th>
                    <th>En pedido</th>
                    <th>Dias de inventario</th>
                </tr>
            </thead>
            <tbody>
            <?php if (!empty($breaks)): ?>
                <?php foreach ($breaks as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['store_id'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                        <td><?php echo (int)($row['product_id'] ?? 0); ?></td>
                        <td><?php echo number_format((float)($row['on_hand'] ?? 0), 2); ?></td>
                        <td><?php echo number_format((float)($row['on_order'] ?? 0), 2); ?></td>
                        <td><?php echo $row['days_of_inventory'] !== null ? number_format((float)$row['days_of_inventory'], 1) : 'N/D'; ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="text-center text-muted py-10">Sin alertas de quiebres.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
