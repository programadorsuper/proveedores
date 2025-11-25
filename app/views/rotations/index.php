<div class="card">
    <div class="card-header">
        <h3 class="card-title">Turnover mensual</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead>
                    <tr>
                        <th>Mes</th>
                        <th>Rotacion</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($series)): ?>
                    <?php foreach ($series as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['month'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo number_format((float)($row['turnover'] ?? 0), 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="2" class="text-center text-muted py-10">Sin informacion disponible.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
