<?php

require_once __DIR__ . '/../core/ProtectedController.php';
require_once __DIR__ . '/../models/Orders.php';
require_once __DIR__ . '/../models/OrderViews.php';

class OrdersController extends ProtectedController
{
    protected Orders $orders;
    protected OrderViews $orderViews;
    protected string $basePath;

    public function __construct()
    {
        parent::__construct();
        $this->orders = new Orders();
        $this->orderViews = new OrderViews();
        $this->basePath = $this->config['base_path'] ?? ($this->config['base_url'] ?? '');
    }
    public function index(): void
    {
        $this->renderOrdersPage('Ordenes', 'index');
    }

    public function nuevas(): void
    {
        // $user = $GLOBALS['auth_user'];
        // echo json_encode($user);
        // exit;
        $assetBase = $this->assetBase();
        $detailUrl = $this->moduleUrl('/ordenes/{id}/detalle');

        $this->renderOrdersPage('Ordenes nuevas', 'nuevas', [
            'filters' => [
                'days' => 30,
                'per_page' => 25,
            ],
            'ordersConfig' => [
                'listEndpoint' => $this->moduleUrl('/ordenes/listar'),
                'markSeenEndpoint' => $this->moduleUrl('/ordenes/marcar-vista'),
                'exportEndpoint' => $this->moduleUrl('/ordenes/{id}/exportar'),
                'detailEndpoint' => $detailUrl,
                'checkNewEndpoint'  => $this->moduleUrl('/ordenes/nuevas/check'),
            ],
            'pageScripts' => ['/orders-nuevas.js'],
            'pageStyles' => [$assetBase . '/assets/css/orders.css'],
        ]);
    }

    public function listOrders(): void
    {
        try {
            $isSuperAdmin = !empty($this->user['is_super_admin']);
            $providerIds  = $isSuperAdmin ? [] : $this->context->providerIds();

            $page    = max(1, (int)($_GET['page'] ?? 1));
            $perPage = (int)($_GET['per_page'] ?? 25);
            $search  = trim((string)($_GET['search'] ?? ''));
            $days    = (int)($_GET['days'] ?? 30);

            $result = $this->orders->getNewPurchaseOrders($providerIds, [
                'page'     => $page,
                'per_page' => $perPage,
                'search'   => $search,
                'days'     => $days,
            ]);

            $orderIds = array_column($result['data'], 'ID_COMPRA');
            $views    = !empty($orderIds)
                ? $this->orderViews->summaries($orderIds, (int)$this->user['id'])
                : [];

            foreach ($result['data'] as &$order) {
                $orderId  = (int)$order['ID_COMPRA'];
                $viewInfo = $views[$orderId] ?? null;
                $viewers  = (int)($viewInfo['viewers'] ?? 0);
                $seenByMeAt = $viewInfo['seen_by_me_at'] ?? null;

                $order['seen'] = [
                    'seen_by_me'     => $seenByMeAt !== null,
                    'seen_by_me_at'  => $seenByMeAt,
                    'viewers'        => $viewers,
                    'last_view_user' => $viewInfo['last_username'] ?? null,
                    'last_view_at'   => $viewInfo['latest_seen_at'] ?? null,
                    'seen_by_others' => $viewers > ($seenByMeAt !== null ? 1 : 0),
                ];
            }
            unset($order);

            // 👇 NUEVO: último movimiento de vistas en Postgres
            $lastViewsTs = $this->orderViews->lastViewsActivity($providerIds);

            $payload = [
                'data' => $result['data'],
                'meta' => array_merge($result['meta'], [
                    'filters' => [
                        'page'     => $page,
                        'per_page' => $perPage,
                        'search'   => $search,
                        'days'     => $days,
                    ],
                    'can_view_all'  => $isSuperAdmin,
                    'last_views_ts' => $lastViewsTs, // 👈
                ]),
            ];

            $this->jsonResponse($payload);
        } catch (\Throwable $exception) {
            error_log('[OrdersController] listOrders error: ' . $exception->getMessage());
            $this->jsonResponse([
                'error'  => 'No fue posible obtener las ordenes. Intenta de nuevo.',
                'detail' => $exception,
            ], 500);
        }
    }

    public function checkNew(): void
    {
        try {
            $sinceId      = (int)($_GET['since_id'] ?? 0);
            $days         = (int)($_GET['days'] ?? 30);
            $sinceViewsTs = $_GET['since_views_ts'] ?? null;

            $isSuperAdmin = !empty($this->user['is_super_admin']);
            $providerIds  = $isSuperAdmin ? [] : $this->context->providerIds();

            // 1) Firebird: nuevas órdenes
            $ordersInfo = $this->orders->checkNewPurchaseOrders($providerIds, $sinceId, $days);

            // 2) Postgres: actividad de vistas
            $lastViewsTsDb = $this->orderViews->lastViewsActivity($providerIds);
            $hasViewUpdates = false;

            if ($lastViewsTsDb) {
                if ($sinceViewsTs) {
                    $hasViewUpdates = $lastViewsTsDb > $sinceViewsTs;
                } else {
                    // primera vez que preguntamos: considera que hay actividad
                    $hasViewUpdates = true;
                }
            }

            $this->jsonResponse([
                'has_new_orders'   => $ordersInfo['has_new'],
                'latest_id'        => $ordersInfo['latest_id'],
                'has_view_updates' => $hasViewUpdates,
                'last_views_ts'    => $lastViewsTsDb,
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'error'  => 'No fue posible verificar nuevas ordenes.',
                'detail' => $e->getMessage(),
            ], 500);
        }
    }

    protected function getCurrentProviderIds(): array
    {
        // si ya tienes en sesión algo como Session::get('provider_id')
        // return [ (int) Session::get('provider_id') ];

        // placeholder para que no truene si no lo has hecho aún:
        return [];
    }
    // OrdersController.php

    public function backorder(): void
    {
        $isSuperAdmin = !empty($this->user['is_super_admin']);
        $providerIds = $isSuperAdmin ? [] : $this->context->providerIds();

        // Solo para que la vista sepa si hay filtros por proveedor
        $hasProviderFilter = !$isSuperAdmin && !empty($providerIds);

        $this->renderModule('orders/backorders', [
            'title' => 'Backorders',
            'pageScripts' => ['/orders-backorders.js'],
            'backordersConfig' => [
                'endpoints' => [
                    'list' => $this->basePath . '/ordenes/backorder/api',
                    'detail' => $this->moduleUrl('/ordenes/backorder/{id}/detalle'),
                ],
                'hasProviderFilter' => $hasProviderFilter,
                'defaultPerPage'    => 25,
            ],
        ], 'orders');
    }

    public function backordersJson(): void
    {
        $isSuperAdmin = !empty($this->user['is_super_admin']);
        $providerIds = $isSuperAdmin ? [] : $this->context->providerIds();

        $page    = max(1, (int)($_GET['page'] ?? 1));
        $perPage = (int)($_GET['per_page'] ?? 25);
        if ($perPage <= 0) {
            $perPage = 25;
        }
        if ($perPage > 200) {
            $perPage = 200;
        }

        $filters = [
            // siempre 2 meses (ya no viene de la vista)
            'months' => 2,
            'search' => trim((string)($_GET['search'] ?? '')),
        ];

        $result = $this->orders->getBackordersPaginated($providerIds, $filters, $page, $perPage);
        $json = [
            'items'    => $result['items'],
            'total'    => $result['total'],
            'page'     => $result['page'],
            'per_page' => $result['per_page'],
        ];

        header('Content-Type: application/json');
        echo json_encode($json);
    }

    public function entradas(): void
    {
        $this->renderOrdersPage('Ordenes con entrada', 'entradas');
    }

    public function detalleBackorder(int $orderId): void
    {
        if ($orderId <= 0) {
            http_response_code(404);
            echo 'Backorder no encontrado';
            return;
        }

        $order = $this->orders->getOrdenById($orderId);
        if (!$order) {
            http_response_code(404);
            echo 'Backorder no encontrado';
            return;
        }

        $providerId = isset($order['ID_PROVEEDOR']) ? (int)$order['ID_PROVEEDOR'] : null;
        if (!$this->canAccessProvider($providerId)) {
            http_response_code(403);
            echo 'No autorizado para ver este backorder.';
            return;
        }

        $items = $this->orders->detallesByOrden($orderId);
        $receivedMap = $this->orders->getReceivedQuantities($orderId);
        $entriesRaw = $this->orders->getEntradasAlmacen($orderId);
        $entries = [];
        foreach ($entriesRaw as $entry) {
            $entryId = (int)($entry['ID_ORDEN_ENTRADA'] ?? 0);
            $entries[] = [
                'header' => $entry,
                'details' => $entryId > 0 ? $this->orders->getEntradasAlmacenDetalles($entryId) : [],
            ];
        }

        $this->renderModule('orders/backorders_detail', [
            'title' => 'Backorder #' . ($order['FOLIO'] ?? $orderId),
            'order' => $order,
            'items' => $items,
            'entries' => $entries,
            'receivedMap' => $receivedMap,
        ], 'orders');
    }

    public function detalle(int $orderId): void
    {
        if ($orderId <= 0) {
            http_response_code(404);
            echo 'Orden no encontrada';
            return;
        }

        $order = $this->orders->getOrdenById($orderId);
        if (!$order) {
            http_response_code(404);
            echo 'Orden no encontrada';
            return;
        }

        $providerId = isset($order['ID_PROVEEDOR']) ? (int)$order['ID_PROVEEDOR'] : null;
        if (!$this->canAccessProvider($providerId)) {
            http_response_code(403);
            echo 'No autorizado para ver esta orden.';
            return;
        }

        $items = $this->orders->detallesByOrden($orderId);
        $this->trackOrderView($orderId, $providerId);

        $this->renderModule('orders/detail', [
            'title' => 'Orden #' . ($order['FOLIO'] ?? $orderId),
            'order' => $order,
            'items' => $items,
            'downloadBase' => $this->moduleUrl('/ordenes/{id}/exportar'),
        ], 'orders');
    }

    public function markViewed(): void
    {
        try {
            $input = $this->readJsonBody();
            $orderId = isset($_POST['order_id']) ? (int)$_POST['order_id'] : (int)($input['order_id'] ?? 0);
            if ($orderId <= 0) {
                $this->jsonResponse(['error' => 'order_id requerido'], 422);
            }
            $providerId = isset($_POST['provider_id']) ? (int)$_POST['provider_id'] : (int)($input['provider_id'] ?? 0);
            if ($providerId <= 0) {
                $providerId = null;
            }

            if (!$this->canAccessProvider($providerId)) {
                $this->jsonResponse(['error' => 'No autorizado'], 403);
            }

            $this->orderViews->markAsSeen($orderId, $providerId, (int)$this->user['id']);
            $summary = $this->orderViews->summaries([$orderId], (int)$this->user['id']);

            $viewInfo = $summary[$orderId] ?? [];
            $this->jsonResponse([
                'order_id' => $orderId,
                'seen' => [
                    'seen_by_me' => !empty($viewInfo['seen_by_me_at']),
                    'seen_by_me_at' => $viewInfo['seen_by_me_at'] ?? null,
                    'viewers' => (int)($viewInfo['viewers'] ?? 0),
                    'last_view_user' => $viewInfo['last_username'] ?? null,
                    'last_view_at' => $viewInfo['latest_seen_at'] ?? null,
                ],
            ]);
        } catch (\Throwable $exception) {
            error_log('[OrdersController] markViewed error: ' . $exception->getMessage());
            $this->jsonResponse([
                'error' => 'No se pudo registrar la vista.',
                'detail' => $this->isDebug() ? $exception->getMessage() : null,
            ], 500);
        }
    }

    public function export(int $orderId): void
    {
        try {
            $format = strtolower((string)($_GET['format'] ?? 'pdf'));
            if ($orderId <= 0) {
                $this->jsonResponse(['error' => 'ID de orden requerido'], 422);
            }

            $order = $this->orders->getOrdenById($orderId);
            if (!$order) {
                $this->jsonResponse(['error' => 'Orden no encontrada'], 404);
            }

            $providerId = isset($order['ID_PROVEEDOR']) ? (int)$order['ID_PROVEEDOR'] : null;
            if (!$this->canAccessProvider($providerId)) {
                $this->jsonResponse(['error' => 'No autorizado'], 403);
            }

            $details = $this->orders->detallesByOrden($orderId);
            $this->trackOrderView($orderId, $providerId);

            $exporter = $this->ordersExporter();
            $fileNameBase = sprintf('orden_%d', $orderId);

            switch ($format) {
                case 'pdf':
                    $content = $exporter->asPdf($order, $details);
                    $this->streamDownload($content, $fileNameBase . '.pdf', 'application/pdf');
                    break;
                case 'xml':
                    $content = $exporter->asXml($order, $details);
                    $this->streamDownload($content, $fileNameBase . '.xml', 'application/xml');
                    break;
                case 'csv':
                    $content = $exporter->asCsv($order, $details);
                    $this->streamDownload($content, $fileNameBase . '.csv', 'text/csv');
                    break;
                case 'xlsx':
                    $content = $exporter->asXlsx($order, $details);
                    $this->streamDownload($content, $fileNameBase . '.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
                    break;
                default:
                    $this->jsonResponse(['error' => 'Formato no soportado'], 422);
            }
        } catch (\Throwable $exception) {
            error_log('[OrdersController] export error: ' . $exception->getMessage());
            $this->jsonResponse([
                'error' => 'No fue posible generar la descarga.',
                'detail' => $this->isDebug() ? $exception->getMessage() : null,
            ], 500);
        }
    }

    protected function streamDownload(string $content, string $filename, string $contentType): void
    {
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        echo $content;
        exit;
    }

    protected function ordersExporter()
    {
        static $exporter = null;
        if ($exporter === null) {
            require_once __DIR__ . '/../services/OrdersExporter.php';
            $exporter = new OrdersExporter($this->config);
        }
        return $exporter;
    }

    protected function renderOrdersPage(string $title, string $page, array $data = []): void
    {
        $payload = array_merge([
            'title' => $title,
            'page' => $page,
        ], $data);

        $this->renderModule('orders/index', $payload, 'orders');
    }

    protected function jsonResponse(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }

    protected function isDebug(): bool
    {
        return !empty($this->config['app_debug']);
    }

    protected function readJsonBody(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    protected function moduleUrl(string $path): string
    {
        return $this->basePath !== '' ? $this->basePath . $path : $path;
    }

    protected function assetBase(): string
    {
        return $this->basePath !== '' ? $this->basePath : '';
    }

    protected function trackOrderView(int $orderId, ?int $providerId = null): void
    {
        try {
            if ($providerId !== null && $providerId <= 0) {
                $providerId = null;
            }
            $this->orderViews->markAsSeen($orderId, $providerId, (int)$this->user['id']);
        } catch (\Throwable $exception) {
            error_log('[OrdersController] trackOrderView error: ' . $exception->getMessage());
        }
    }

    protected function canAccessProvider(?int $providerId): bool
    {
        if (!empty($this->user['is_super_admin'])) {
            return true;
        }

        if ($providerId === null) {
            return true;
        }

        $allowed = $this->context->providerIds();
        return in_array($providerId, $allowed, true);
    }
}
