<?php

require_once __DIR__ . '/../core/ProtectedController.php';
require_once __DIR__ . '/../models/Tickets.php';

class TicketsController extends ProtectedController
{
    protected Tickets $tickets;

    public function __construct()
    {
        parent::__construct();
        $this->tickets = new Tickets();
    }

    public function index(): void
    {
        if (!$this->ensureModule('tickets')) {
            return;
        }

        $filters = $this->parseFilters($_GET ?? []);
        $providerId = $this->resolveProviderId($filters);
        $results = $providerId ? $this->tickets->search($providerId, $filters) : [];

        $this->renderModule('tickets/index', [
            'title' => 'Tickets',
            'filters' => $filters,
            'tickets' => $results,
            'selectedProviderId' => $providerId,
        ], 'tickets');
    }

    public function search(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            return;
        }

        if (!$this->providerContext()->canAccessModule('tickets')) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'message' => 'Modulo no disponible']);
            return;
        }

        $input = json_decode(file_get_contents('php://input') ?: '', true);
        if (!is_array($input)) {
            $input = $_POST;
        }

        $filters = $this->parseFilters($input);
        $providerId = $this->resolveProviderId($filters);
        if (!$providerId) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'message' => 'Proveedor no valido']);
            return;
        }

        $tickets = $this->tickets->search($providerId, $filters);
        header('Content-Type: application/json');
        echo json_encode([
            'ok' => true,
            'tickets' => $tickets,
        ]);
    }

    public function detail(): void
    {
        if (!$this->ensureModule('tickets')) {
            return;
        }

        $ticketId = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : 0;
        $providerId = $this->resolveProviderId($_GET ?? []);

        if ($ticketId <= 0 || !$providerId) {
            http_response_code(400);
            echo '<p>Ticket no encontrado.</p>';
            return;
        }

        $detail = $this->tickets->detail($providerId, $ticketId);
        if (!$detail) {
            http_response_code(404);
            echo '<p>Ticket no encontrado.</p>';
            return;
        }

        $this->renderModule('tickets/detail', [
            'title' => 'Detalle de ticket',
            'ticket' => $detail,
        ], 'tickets');
    }

    public function markReviewed(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            return;
        }

        if (!$this->providerContext()->canAccessModule('tickets')) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'message' => 'Modulo no disponible']);
            return;
        }

        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $status = isset($_POST['status']) ? trim((string)$_POST['status']) : 'reviewed';
        $notes = isset($_POST['notes']) ? trim((string)$_POST['notes']) : null;
        $providerId = $this->resolveProviderId($_POST);

        if ($ticketId <= 0 || !$providerId) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'message' => 'Datos incompletos']);
            return;
        }

        $this->tickets->markReviewed($providerId, (int)$this->user['id'], $ticketId, $status, $notes);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
    }

    public function addPoints(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            return;
        }

        if (!$this->providerContext()->canAccessModule('tickets')) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'message' => 'Modulo no disponible']);
            return;
        }

        $ticketId = (int)($_POST['ticket_id'] ?? 0);
        $points = (float)($_POST['points'] ?? 0);
        $reason = isset($_POST['reason']) ? trim((string)$_POST['reason']) : null;
        $providerId = $this->resolveProviderId($_POST);

        if ($ticketId <= 0 || $points === 0.0 || !$providerId) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'message' => 'Datos incompletos']);
            return;
        }

        $this->tickets->addPoints($providerId, (int)$this->user['id'], $ticketId, $points, $reason);
        header('Content-Type: application/json');
        echo json_encode(['ok' => true]);
    }

    public function download(): void
    {
        if (!$this->providerContext()->canAccessModule('tickets')) {
            http_response_code(403);
            echo 'Modulo no disponible';
            return;
        }

        http_response_code(501);
        echo 'Exportacion de tickets en construccion.';
    }

    protected function parseFilters(array $source): array
    {
        $filters = [
            'query' => trim((string)($source['query'] ?? '')),
            'start_date' => $source['start_date'] ?? null,
            'end_date' => $source['end_date'] ?? null,
            'provider_id' => isset($source['provider_id']) ? (int)$source['provider_id'] : null,
            'limit' => isset($source['limit']) ? (int)$source['limit'] : 100,
        ];

        if (!empty($filters['start_date'])) {
            $filters['start_date'] = (new \DateTimeImmutable($filters['start_date']))->format('Y-m-d');
        }
        if (!empty($filters['end_date'])) {
            $filters['end_date'] = (new \DateTimeImmutable($filters['end_date']))->format('Y-m-d');
        }

        return $filters;
    }

    protected function resolveProviderId(array $filters): ?int
    {
        $providerId = isset($filters['provider_id']) ? (int)$filters['provider_id'] : null;
        if ($providerId && in_array($providerId, $this->providerContext()->providerIds(), true)) {
            return $providerId;
        }
        return $this->providerContext()->primaryProviderId();
    }
}
