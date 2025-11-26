<?php

class OrderViews
{
    protected \PDO $db;

    public function __construct()
    {
        $this->db = Database::pgsql();
    }

    public function markAsSeen(int $orderId, ?int $providerId, int $userId): ?array
    {
        $sql = "
            INSERT INTO proveedores.order_views (order_id, provider_id, user_id)
            VALUES (:order_id, :provider_id, :user_id)
            ON CONFLICT (order_id, user_id) DO UPDATE
            SET provider_id = EXCLUDED.provider_id,
                last_seen_at = NOW(),
                seen_count = proveedores.order_views.seen_count + 1,
                updated_at = NOW()
            RETURNING order_id, user_id, first_seen_at, last_seen_at, seen_count
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':order_id', $orderId, \PDO::PARAM_INT);
        if ($providerId !== null) {
            $stmt->bindValue(':provider_id', $providerId, \PDO::PARAM_INT);
        } else {
            $stmt->bindValue(':provider_id', null, \PDO::PARAM_NULL);
        }
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function summaries(array $orderIds, int $currentUserId): array
    {
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds), static fn($id) => $id > 0)));
        if (empty($orderIds)) {
            return [];
        }

        $list = implode(',', $orderIds);

        $sql = "
            WITH latest AS (
                SELECT DISTINCT ON (order_id)
                    order_id,
                    user_id AS last_user_id,
                    last_seen_at
                FROM proveedores.order_views
                WHERE order_id = ANY(string_to_array(:order_ids, ',')::bigint[])
                ORDER BY order_id, last_seen_at DESC
            )
            SELECT
                ov.order_id,
                COUNT(*) AS viewers,
                MAX(ov.last_seen_at) AS last_seen_at,
                MAX(CASE WHEN ov.user_id = :current_user THEN ov.last_seen_at END) AS seen_by_me_at,
                latest.last_user_id,
                latest.last_seen_at AS latest_seen_at,
                u.username AS last_username
            FROM proveedores.order_views ov
            LEFT JOIN latest ON latest.order_id = ov.order_id
            LEFT JOIN proveedores.users u ON u.id = latest.last_user_id
            WHERE ov.order_id = ANY(string_to_array(:order_ids, ',')::bigint[])
            GROUP BY
                ov.order_id,
                latest.last_user_id,
                latest.last_seen_at,
                u.username
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':order_ids', $list, \PDO::PARAM_STR);
        $stmt->bindValue(':current_user', $currentUserId, \PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        if (!$rows) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $orderId = (int)$row['order_id'];
            $map[$orderId] = [
                'order_id' => $orderId,
                'viewers' => (int)$row['viewers'],
                'last_seen_at' => $row['last_seen_at'],
                'seen_by_me_at' => $row['seen_by_me_at'] ?? null,
                'last_user_id' => $row['last_user_id'] !== null ? (int)$row['last_user_id'] : null,
                'last_username' => $row['last_username'] ?? null,
                'latest_seen_at' => $row['latest_seen_at'] ?? null,
            ];
        }

        return $map;
    }
    public function lastViewsActivity(array $providerIds = []): ?string
    {
        $conditions = [];
        $params     = [];

        if (!empty($providerIds)) {
            $conditions[] = 'ov.provider_id = ANY(:provider_ids::bigint[])';
            $params['provider_ids'] = '{' . implode(',', array_map('intval', $providerIds)) . '}';
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "
            SELECT MAX(ov.last_seen_at) AS last_views_ts
            FROM proveedores.order_views ov
            {$where}
        ";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            if ($key === 'provider_ids') {
                $stmt->bindValue(':' . $key, $value, \PDO::PARAM_STR);
            } else {
                $stmt->bindValue(':' . $key, $value);
            }
        }

        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;

        return ($row && $row['last_views_ts']) ? $row['last_views_ts'] : null;
    }
}
