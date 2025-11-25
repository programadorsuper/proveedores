<?php

require_once __DIR__ . '/../core/ProtectedController.php';
require_once __DIR__ . '/../models/Providers.php';
require_once __DIR__ . '/../models/Analytics.php';

class HomeController extends ProtectedController
{
    protected Providers $providers;
    protected Analytics $analytics;
    protected array $providerOptions = [];

    public function __construct()
    {
        parent::__construct();
        $this->providers = new Providers();
        $this->analytics = new Analytics();
        $this->providerOptions = $this->providers->listForUser($this->user);
    }

    public function index(): void
    {
        if (!$this->ensureModule('dashboard')) {
            return;
        }

        $filters = $this->extractFilters();
        $allowedProviderIds = $this->getAllowedProviderIds();
        if (empty($allowedProviderIds)) {
            throw new RuntimeException('No hay proveedores asociados al usuario actual.');
        }

        $selectedProviderId = (int)($filters['provider_id'] ?? 0);
        if ($selectedProviderId === 0 && isset($this->user['provider_id'])) {
            $selectedProviderId = (int)$this->user['provider_id'];
        }
        if (!in_array($selectedProviderId, $allowedProviderIds, true)) {
            $selectedProviderId = $allowedProviderIds[0];
        }

        $filters['provider_id'] = $selectedProviderId;
        $payload = $this->buildDashboardPayload($selectedProviderId, $filters);

        $filtersView = $this->formatFiltersForView($filters);
        $stores = $selectedProviderId > 0
            ? $this->analytics->listStores($selectedProviderId, $filters)
            : [];

        $this->renderModule('home/index', [
            'title' => 'Inicio',
            'providers' => $this->providerOptions,
            'selectedProviderId' => $selectedProviderId,
            'filters' => $filtersView,
            'periodOptions' => $this->periodOptions(),
            'summary' => $payload['summary'],
            'trend' => $payload['trend'],
            'ordersDistribution' => $payload['orders'],
            'topProducts' => $payload['topProducts'],
            'topCustomers' => $payload['topCustomers'],
            'topStores' => $payload['topStores'],
            'topStoresChart' => $payload['topStoresChart'],
            'stores' => $stores,
            'productOptions' => $payload['productOptions'],
        ], 'dashboard');
    }

    public function stats(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'message' => 'Método no permitido']);
            exit;
        }

        if (!$this->isAjaxRequest()) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'message' => 'Solicitud no válida']);
            exit;
        }

        if (!$this->providerContext()->canAccessModule('dashboard')) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'message' => 'Modulo no disponible para este proveedor']);
            exit;
        }

        $input = json_decode(file_get_contents('php://input') ?: '', true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $filters = $this->extractFilters($input);
        $allowedProviderIds = $this->getAllowedProviderIds();
        if (empty($allowedProviderIds)) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'message' => 'No hay proveedores disponibles']);
            exit;
        }

        $selectedProviderId = (int)($filters['provider_id'] ?? 0);
        if (!in_array($selectedProviderId, $allowedProviderIds, true)) {
            $selectedProviderId = $allowedProviderIds[0];
        }
        $filters['provider_id'] = $selectedProviderId;

        $payload = $this->buildDashboardPayload($selectedProviderId, $filters);
        $filtersView = $this->formatFiltersForView($filters);

        header('Content-Type: application/json');
        echo json_encode([
            'ok' => true,
            'filters' => $filtersView,
            'data' => $payload,
        ]);
        exit;
    }

    protected function extractFilters(?array $source = null): array
    {
        $source ??= $_GET;
        if (isset($source['reset'])) {
            $source = [];
        }

        $period = strtolower(trim((string)($source['period'] ?? 'ytd')));
        $allowedPeriods = array_keys($this->periodOptions());
        if (!in_array($period, $allowedPeriods, true)) {
            $period = 'ytd';
        }

        $groupBy = strtolower(trim((string)($source['group_by'] ?? 'month')));
        if (!in_array($groupBy, ['day', 'week', 'month'], true)) {
            $groupBy = 'month';
        }

        $storeId = isset($source['store_id']) && $source['store_id'] !== '' ? (int)$source['store_id'] : null;
        $query = isset($source['query']) ? trim((string)$source['query']) : null;
        $includeInactive = !empty($source['include_inactive']);

        [$start, $end] = $this->determinePeriodRange($period, $source);
        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        $compareStart = $start->modify('-1 year');
        $compareEnd = $end->modify('-1 year');
        $yearStart = new \DateTimeImmutable($end->format('Y') . '-01-01 00:00:00');
        $yearCompareStart = $yearStart->modify('-1 year');

        return [
            'provider_id' => isset($source['provider_id']) ? (int)$source['provider_id'] : null,
            'period' => $period,
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
        ];
    }

    protected function determinePeriodRange(string $period, array $source): array
    {
        $today = new \DateTimeImmutable('today 23:59:59');
        $startOfMonth = $today->modify('first day of this month 00:00:00');
        $startOfQuarter = (new \DateTimeImmutable($today->format('Y') . '-' . (((int)($today->format('n') - 1) / 3) * 3 + 1) . '-01 00:00:00'));
        $startOfYear = new \DateTimeImmutable($today->format('Y') . '-01-01 00:00:00');

        return match ($period) {
            'mtd' => [$startOfMonth, $today],
            'qtd' => [$startOfQuarter, $today],
            'ytd' => [$startOfYear, $today],
            'last6' => [$today->modify('-5 months')->modify('first day of this month 00:00:00'), $today],
            'last12' => [$today->modify('-11 months')->modify('first day of this month 00:00:00'), $today],
            default => [
                $this->parseDate($source['start_date'] ?? $startOfMonth),
                $this->parseDate($source['end_date'] ?? $today, '23:59:59'),
            ],
        };
    }

    protected function buildDashboardPayload(int $providerId, array $filters): array
    {
        $dashboard = $this->dashboardService();

        $summary = $dashboard->getSummary($providerId, $filters);
        $trend = $dashboard->getSalesTrend($providerId, $filters);
        $orders = $dashboard->getOrdersDistribution($providerId, $filters);
        $topProducts = $this->analytics->selloutProducts($providerId, $filters, 50);
        $topCustomers = $this->analytics->topCustomersSummary($providerId, $filters, 10);
        $topStores = $this->analytics->topStoresSummary($providerId, $filters, 10);
        $topStoresChart = $this->prepareTopStoresChart($topStores);
        $productOptions = $this->buildProductOptions(
            $this->analytics->productSuggestions($providerId, $filters['query'] ?? null, 200, $filters),
            $topProducts
        );

        return [
            'summary' => $summary,
            'trend' => $trend,
            'orders' => $orders,
            'topProducts' => $topProducts,
            'topCustomers' => $topCustomers,
            'topStores' => $topStores,
            'topStoresChart' => $topStoresChart,
            'productOptions' => $productOptions,
        ];
    }

    protected function parseDate($value, string $timeSuffix = '00:00:00'): \DateTimeImmutable
    {
        if ($value instanceof \DateTimeImmutable) {
            return $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return \DateTimeImmutable::createFromInterface($value);
        }

        $string = trim((string)$value);
        if ($string === '') {
            return new \DateTimeImmutable('today ' . $timeSuffix);
        }
        if (strlen($string) <= 10) {
            $string .= ' ' . $timeSuffix;
        }
        return new \DateTimeImmutable($string);
    }

    protected function formatFiltersForView(array $filters): array
    {
        return [
            'provider_id' => $filters['provider_id'],
            'period' => $filters['period'],
            'start_date' => $filters['start_date']->format('Y-m-d'),
            'end_date' => $filters['end_date']->format('Y-m-d'),
            'group_by' => $filters['group_by'],
            'store_id' => $filters['store_id'],
            'query' => $filters['query'] ?? '',
            'include_inactive' => (bool)$filters['include_inactive'],
        ];
    }

    protected function periodOptions(): array
    {
        return [
            'mtd' => 'Mes a la fecha',
            'qtd' => 'Trimestre a la fecha',
            'ytd' => 'Año a la fecha',
            'last6' => 'Últimos 6 meses',
            'last12' => 'Últimos 12 meses',
            'custom' => 'Personalizado',
        ];
    }

    protected function prepareTopStoresChart(array $topStores): array
    {
        $topStores = array_slice($topStores, 0, 10);
        $categories = [];
        $values = [];

        foreach ($topStores as $row) {
            $categories[] = 'Tienda #' . ($row['id_tienda'] ?? $row['store_id'] ?? $row['id_tienda'] ?? 'N/D');
            $values[] = round((float)($row['value_total'] ?? 0), 2);
        }

        return [
            'categories' => $categories,
            'series' => [
                [
                    'name' => 'Sell-out',
                    'data' => $values,
                ],
            ],
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

    protected function getAllowedProviderIds(): array
    {
        $ids = array_map(
            static function (array $provider): int {
                return (int)($provider['id'] ?? 0);
            },
            $this->providerOptions
        );

        return array_values(array_unique(array_filter($ids, static fn($id) => $id > 0)));
    }
}
