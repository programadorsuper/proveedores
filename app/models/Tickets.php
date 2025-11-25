<?php

class Tickets
{
    protected \PDO $db;

    public function __construct()
    {
        $this->db = Database::pgsql();
    }

    public function search(int $providerId, array $filters = []): array
    {
        $query = strtolower(trim((string)($filters['query'] ?? '')));
        $limit = min(200, max(1, (int)($filters['limit'] ?? 100)));
        [$startDate, $endDate] = $this->resolveDateRange($filters);

        $sql = "
            SELECT
                t.ticket_id,
                t.ticket_date,
                t.series,
                t.folio,
                t.store_id,
                t.customer_id,
                t.net_sales,
                tr.status,
                tr.reviewed_at,
                COALESCE(tp.total_points, 0) AS total_points
            FROM proveedores.vw_tickets t
            LEFT JOIN proveedores.ticket_reviews tr
                ON tr.provider_id = t.provider_id
               AND tr.ticket_id = t.ticket_id
            LEFT JOIN LATERAL (
                SELECT SUM(points) AS total_points
                FROM proveedores.ticket_points
                WHERE provider_id = t.provider_id
                  AND ticket_id = t.ticket_id
            ) tp ON TRUE
            WHERE t.provider_id = :pid
              AND t.ticket_date BETWEEN :start AND :end
              AND (
                    :query = ''
                 OR  t.series ILIKE :like
                 OR CAST(t.folio AS TEXT) ILIKE :like
                 OR CAST(t.ticket_id AS TEXT) ILIKE :like
              )
            ORDER BY t.ticket_date DESC
            LIMIT :limit
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue('pid', $providerId, \PDO::PARAM_INT);
        $stmt->bindValue('start', $startDate->format('Y-m-d 00:00:00'));
        $stmt->bindValue('end', $endDate->format('Y-m-d 23:59:59'));
        $stmt->bindValue('query', $query);
        $stmt->bindValue('like', '%' . $query . '%');
        $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function detail(int $providerId, int $ticketId): ?array
    {
        $headerSql = "
            SELECT t.*, tr.status, tr.reviewed_at, tr.notes,
                   COALESCE(tp.total_points, 0) AS total_points
            FROM proveedores.vw_tickets t
            LEFT JOIN proveedores.ticket_reviews tr
              ON tr.provider_id = t.provider_id
             AND tr.ticket_id = t.ticket_id
            LEFT JOIN LATERAL (
                SELECT SUM(points) AS total_points
                FROM proveedores.ticket_points
                WHERE provider_id = t.provider_id
                  AND ticket_id = t.ticket_id
            ) tp ON TRUE
            WHERE t.provider_id = :pid
              AND t.ticket_id = :ticket
            LIMIT 1
        ";

        $stmt = $this->db->prepare($headerSql);
        $stmt->execute([
            'pid' => $providerId,
            'ticket' => $ticketId,
        ]);
        $header = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$header) {
            return null;
        }

        $itemsSql = "
            SELECT product_id, sku, name, qty, price, total, cost
            FROM proveedores.vw_ticket_items
            WHERE provider_id = :pid
              AND ticket_id = :ticket
            ORDER BY name ASC
        ";

        $stmt = $this->db->prepare($itemsSql);
        $stmt->execute([
            'pid' => $providerId,
            'ticket' => $ticketId,
        ]);
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $header['items'] = $items;
        return $header;
    }

    public function markReviewed(int $providerId, int $userId, int $ticketId, string $status, ?string $notes = null): void
    {
        $sql = "
            INSERT INTO proveedores.ticket_reviews (provider_id, user_id, ticket_id, status, notes, reviewed_at)
            VALUES (:pid, :uid, :ticket, :status, :notes, NOW())
            ON CONFLICT (provider_id, ticket_id)
            DO UPDATE SET
                user_id = EXCLUDED.user_id,
                status = EXCLUDED.status,
                notes = EXCLUDED.notes,
                reviewed_at = NOW()
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'pid' => $providerId,
            'uid' => $userId,
            'ticket' => $ticketId,
            'status' => $status,
            'notes' => $notes,
        ]);

        $this->logActivity($providerId, $userId, 'ticket_review', [
            'ticket_id' => $ticketId,
            'status' => $status,
        ]);
    }

    public function addPoints(int $providerId, int $userId, int $ticketId, float $points, ?string $reason = null): void
    {
        $sql = "
            INSERT INTO proveedores.ticket_points (provider_id, ticket_id, points, reason, created_at)
            VALUES (:pid, :ticket, :points, :reason, NOW())
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'pid' => $providerId,
            'ticket' => $ticketId,
            'points' => $points,
            'reason' => $reason,
        ]);

        $this->logActivity($providerId, $userId, 'ticket_points', [
            'ticket_id' => $ticketId,
            'points' => $points,
            'reason' => $reason,
        ]);
    }

    protected function resolveDateRange(array $filters): array
    {
        $end = isset($filters['end_date']) ? new \DateTimeImmutable((string)$filters['end_date']) : new \DateTimeImmutable('today');
        $start = isset($filters['start_date']) ? new \DateTimeImmutable((string)$filters['start_date']) : $end->modify('-90 days');

        $limit = $end->modify('-5 years');
        if ($start < $limit) {
            $start = $limit;
        }

        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        return [$start, $end];
    }

    protected function logActivity(int $providerId, int $userId, string $action, array $context = []): void
    {
        try {
            $sql = "
                INSERT INTO proveedores.user_activity_logs (provider_id, actor_user_id, action, context, created_at)
                VALUES (:pid, :uid, :action, :context, NOW())
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'pid' => $providerId,
                'uid' => $userId,
                'action' => $action,
                'context' => json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ]);
        } catch (\Throwable $exception) {
            error_log('[Tickets] Error registrando actividad: ' . $exception->getMessage());
        }
    }
}
