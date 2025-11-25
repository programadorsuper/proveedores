<div class="row g-5">
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <div class="fw-bold text-muted mb-2">Sell-out</div>
                <div class="fs-2 fw-bold">$<?php echo number_format((float)($summary['sellout'] ?? 0), 2); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <div class="fw-bold text-muted mb-2">Sell-in</div>
                <div class="fs-2 fw-bold">$<?php echo number_format((float)($summary['sellin'] ?? 0), 2); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <div class="fw-bold text-muted mb-2">Ordenes pendientes</div>
                <div class="fs-2 fw-bold"><?php echo (int)($summary['orders_pending'] ?? 0); ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-center">
            <div class="card-body">
                <div class="fw-bold text-muted mb-2">Alertas</div>
                <div class="fs-2 fw-bold"><?php echo (int)($summary['alerts'] ?? 0); ?></div>
            </div>
        </div>
    </div>
</div>
