<?php

require_once __DIR__ . '/../core/ProtectedController.php';
require_once __DIR__ . '/../models/Analytics.php';

class SalesController extends ProtectedController
{
    protected Analytics $analytics;

    public function __construct()
    {
        parent::__construct();
        $this->analytics = new Analytics();
    }

    public function index(): void
    {
        $this->renderSalesPage('Resumen general', 'sales', 'overview');
    }

    public function periods(): void
    {
        $this->renderSalesPage('Ventas por periodo', 'sales', 'periods');
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
        if (!$this->ensureModule($module)) {
            return;
        }

        $filters = $this->parseSalesFilters();
        $providerId = $this->providerContext()->primaryProviderId() ?? 0;

        $summary = [];
        $trend = ['categories' => [], 'series' => [['name' => 'Sell-out', 'data' => []], ['name' => 'Sell-in', 'data' => []]]];
        $products = [];
        $stores = [];
        $suggestions = [];

        if ($providerId > 0) {
            $dashboard = $this->dashboardService();
            $summary = $dashboard->getSummary($providerId, $filters);
            $trend = $dashboard->getSalesTrend($providerId, $filters);
            $products = $this->analytics->selloutProducts($providerId, $filters);
            $stores = $this->analytics->listStores($providerId, $filters);
            $suggestions = $this->analytics->productSuggestions($providerId, $filters['query'] ?? null, 100, $filters);
        }

        $this->renderModule('sales/index', [
            'title' => $title,
            'module' => $module,
            'page' => $page,
            'trend' => $trend,
            'summary' => $summary,
            'products' => $products,
            'filters' => $this->formatFiltersForView($filters),
            'stores' => $stores,
            'productOptions' => $this->buildProductOptions($suggestions, $products),
        ], $module);
    }

    protected function parseSalesFilters(): array
    {
        $request = $_GET ?? [];
        if (isset($request['reset'])) {
            $request = [];
        }

        $end = $this->parseDate($request['end_date'] ?? null, 'today');
        $start = $this->parseDate($request['start_date'] ?? null, 'first day of this month');

        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        $compareStart = $start->modify('-1 year');
        $compareEnd = $end->modify('-1 year');

        $yearStart = (new \DateTimeImmutable($end->format('Y') . '-01-01 00:00:00'));
        $yearCompareStart = $yearStart->modify('-1 year');

        $groupBy = strtolower((string)($request['group_by'] ?? 'month'));
        if (!in_array($groupBy, ['day', 'week', 'month'], true)) {
            $groupBy = 'month';
        }

        $storeId = isset($request['store_id']) && $request['store_id'] !== '' ? (int)$request['store_id'] : null;
        $query = isset($request['query']) ? trim((string)$request['query']) : null;
        $includeInactive = isset($request['include_inactive']) && (bool)$request['include_inactive'];

        return [
            'start_date' => $start,
            'end_date' => $end,
            'compare_start' => $compareStart,
            'compare_end' => $compareEnd,
            'year_start' => $yearStart,
            'year_compare_start' => $yearCompareStart,
            'group_by' => $groupBy,
            'store_id' => $storeId,
            'query' => $query !== '' ? $query : null,
            'include_inactive' => $includeInactive,
            'period' => 'custom',
        ];
    }

    protected function parseDate($value, string $fallback = 'today'): \DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }
        $value = trim((string)$value);
        if ($value === '') {
            return new \DateTimeImmutable($fallback);
        }
        if (strlen($value) <= 10) {
            $value .= ' 00:00:00';
        }
        return new \DateTimeImmutable($value);
    }

    protected function formatFiltersForView(array $filters): array
    {
        return [
            'start_date' => $filters['start_date']->format('Y-m-d'),
            'end_date' => $filters['end_date']->format('Y-m-d'),
            'compare_start' => $filters['compare_start']->format('Y-m-d'),
            'compare_end' => $filters['compare_end']->format('Y-m-d'),
            'group_by' => $filters['group_by'],
            'store_id' => $filters['store_id'],
            'query' => $filters['query'] ?? '',
            'include_inactive' => (bool)$filters['include_inactive'],
        ];
    }

    protected function buildProductOptions(array $suggestions, array $products): array
    {
        $options = [];

        foreach ($suggestions as $row) {
            $value = $row['codigo'] ?? $row['sku'] ?? $row['codigo_barras'] ?? '';
            if ($value === '') {
                continue;
            }
            $labelParts = array_filter([
                $row['codigo'] ?? null,
                $row['sku'] ?? null,
                $row['descripcion'] ?? null,
            ]);
            $options[$value] = implode(' | ', $labelParts);
        }

        foreach ($products as $row) {
            foreach (['codigo', 'sku', 'codigo_barras'] as $field) {
                if (!empty($row[$field])) {
                    $value = $row[$field];
                    if (!isset($options[$value])) {
                        $labelParts = array_filter([
                            $row['codigo'] ?? null,
                            $row['sku'] ?? null,
                            $row['descripcion'] ?? null,
                        ]);
                        $options[$value] = implode(' | ', $labelParts);
                    }
                }
            }
        }

        return array_slice($options, 0, 200, true);
    }
}
