<?php

require_once __DIR__ . '/../core/ProtectedController.php';
require_once __DIR__ . '/../models/Providers.php';

class HomeController extends ProtectedController
{
    protected Providers $providers;
    protected array $providerOptions = [];

    public function __construct()
    {
        parent::__construct();
        $this->providers = new Providers();
        $this->providerOptions = $this->providers->listForUser($this->user);
    }

    public function index(): void
    {
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

        $this->renderModule('home/index', [
            'title' => 'Inicio',
            'filters' => $filters,
            'providers' => $this->providerOptions,
            'selectedProviderId' => $selectedProviderId,
            'summary' => $payload['summary'],
            'trend' => $payload['trend'],
            'ordersDistribution' => $payload['orders'],
            'topProducts' => $payload['topProducts'],
            'dashboardEndpoint' => ($this->basePath !== '' ? $this->basePath : '') . '/home/stats',
        ]);
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

        header('Content-Type: application/json');
        echo json_encode([
            'ok' => true,
            'filters' => [
                'provider_id' => $selectedProviderId,
                'period' => $filters['period'],
                'channel' => $filters['channel'],
                'category' => $filters['category'],
            ],
            'data' => $payload,
        ]);
        exit;
    }

    protected function extractFilters(?array $source = null): array
    {
        $source ??= $_GET;

        $allowedPeriods = ['mtd', 'qtd', 'ytd', 'mtm'];
        $period = isset($source['period']) ? strtolower(trim((string)$source['period'])) : 'mtm';
        if (!in_array($period, $allowedPeriods, true)) {
            $period = 'mtm';
        }

        return [
            'period' => $period,
            'channel' => isset($source['channel']) ? trim((string)$source['channel']) : 'all',
            'category' => isset($source['category']) ? trim((string)$source['category']) : 'all',
            'provider_id' => isset($source['provider_id']) ? (int)$source['provider_id'] : null,
        ];
    }

    protected function buildDashboardPayload(int $providerId, array $filters): array
    {
        $dashboard = $this->dashboardService();

        return [
            'summary' => $dashboard->getSummary($providerId, $filters),
            'trend' => $dashboard->getSalesTrend($providerId, $filters),
            'orders' => $dashboard->getOrdersDistribution($providerId, $filters),
            'topProducts' => $dashboard->getTopProducts($providerId, $filters),
        ];
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
