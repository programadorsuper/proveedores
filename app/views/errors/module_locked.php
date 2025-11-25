<div class="card">
    <div class="card-body text-center py-20">
        <div class="mb-5">
            <i class="fa-solid fa-lock fa-3x text-warning"></i>
        </div>
        <h2 class="fw-bold mb-3">Modulo bloqueado</h2>
        <p class="text-muted mb-6">
            El modulo <strong><?php echo htmlspecialchars(strtoupper($lockedModule ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong>
            no esta disponible con la membresia actual (<strong><?php echo htmlspecialchars($membershipPlan ?? 'free', ENT_QUOTES, 'UTF-8'); ?></strong>).
        </p>
        <p class="text-muted">
            Ponte en contacto con el super admin para activar la licencia, o revisa las opciones de upgrade en facturacion.
        </p>
    </div>
</div>
