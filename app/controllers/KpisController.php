<?php

require_once __DIR__ . '/../core/ProtectedController.php';

class KpisController extends ProtectedController
{
    public function index(): void
    {
        if (!$this->ensureModule('kpis')) {
            return;
        }

        $providerId = $this->providerContext()->primaryProviderId() ?? (int)($this->user['provider_id'] ?? 0);
        $summary = $providerId > 0 ? $this->dashboardService()->getSummary($providerId, ['period' => 'mtd']) : [];

        $this->renderModule('kpis/index', [
            'title' => 'KPIs',
            'summary' => $summary,
        ], 'kpis');
    }
}
