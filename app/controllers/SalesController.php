<?php

require_once __DIR__ . '/../core/ProtectedController.php';

class SalesController extends ProtectedController
{
    public function index(): void
    {
        $this->renderSalesPage('Resumen general', 'sales', 'overview');
    }

    public function periods(): void
    {
        $this->renderSalesPage('Periodos', 'sales', 'periods');
    }

    public function sellout(): void
    {
        $this->renderSalesPage('Sell Out', 'sales', 'sellout');
    }

    public function sellInOut(): void
    {
        $this->renderSalesPage('Sell In vs Sell Out', 'sales', 'sellinout');
    }

    protected function renderSalesPage(string $title, string $module, string $page): void
    {
        $dashboard = $this->dashboardService();
        $providerId = (int)($this->user['provider_id'] ?? 0);
        $trend = $dashboard->getSalesTrend($providerId, ['period' => 'mtm']);

        $this->renderModule('sales/index', [
            'title' => $title,
            'module' => $module,
            'page' => $page,
            'trend' => $trend,
        ]);
    }
}
