<?php

class Users
{
    protected \PDO $db;

    public function __construct()
    {
        $this->db = Database::pgsql();
    }

    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM proveedores.users WHERE id = :id LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $id]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $user !== false ? $user : [];
    }
    public function listFor(array $sessionUser): array
    {
        $isSuperAdmin = !empty($sessionUser['is_super_admin']);
        $currentUserId = (int)($sessionUser['id'] ?? 0);
        $providerIds = $this->resolveProviderScope($sessionUser);

        $conditions = [];
        $params = [];

        if (!$isSuperAdmin) {
            $scopeClauseParts = ['u.id = :current_user'];
            $params['current_user'] = $currentUserId;

            if (!empty($providerIds)) {
                $params['provider_scope'] = '{' . implode(',', $providerIds) . '}';
                $scopeClauseParts[] = 'u.provider_id = ANY(:provider_scope::int[])';
            }

            $scopeClauseParts[] = 'u.parent_user_id = :current_user';
            $conditions[] = '(' . implode(' OR ', $scopeClauseParts) . ')';
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "
            SELECT
                u.id,
                u.username,
                u.email,
                u.is_active,
                u.created_at,
                u.updated_at,
                u.provider_id,
                u.parent_user_id,
                parent.username AS parent_username,
                p.name AS provider_name,
                p.external_id AS provider_code,
                u.max_child_users,
                u.allowed_days,
                COALESCE(array_remove(array_agg(DISTINCT r.code), NULL), ARRAY[]::text[]) AS roles
            FROM proveedores.users u
            LEFT JOIN proveedores.providers p ON p.id = u.provider_id
            LEFT JOIN proveedores.users parent ON parent.id = u.parent_user_id
            LEFT JOIN proveedores.user_roles ur ON ur.user_id = u.id
            LEFT JOIN proveedores.roles r ON r.id = ur.role_id
            {$where}
            GROUP BY
                u.id,
                u.username,
                u.email,
                u.is_active,
                u.created_at,
                u.updated_at,
                u.provider_id,
                u.parent_user_id,
                parent.username,
                p.name,
                p.external_id,
                u.max_child_users,
                u.allowed_days
            ORDER BY u.created_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return array_map(function (array $row): array {
            $row['id'] = (int)$row['id'];
            $row['provider_id'] = $row['provider_id'] !== null ? (int)$row['provider_id'] : null;
            $row['parent_user_id'] = $row['parent_user_id'] !== null ? (int)$row['parent_user_id'] : null;
            $row['is_active'] = (bool)$row['is_active'];
            $row['max_child_users'] = (int)$row['max_child_users'];
            $row['roles'] = $this->parsePgArray($row['roles'] ?? []);
            $row['allowed_days'] = $this->parsePgArray($row['allowed_days'] ?? [], true);
            return $row;
        }, $rows);
    }

    protected function resolveProviderScope(array $sessionUser): array
    {
        $ids = [];

        if (isset($sessionUser['provider_id']) && $sessionUser['provider_id'] !== null) {
            $ids[] = (int)$sessionUser['provider_id'];
        }

        if (!empty($sessionUser['provider_ids']) && is_array($sessionUser['provider_ids'])) {
            foreach ($sessionUser['provider_ids'] as $providerId) {
                $ids[] = (int)$providerId;
            }
        }

        if (!empty($sessionUser['id'])) {
            $stmt = $this->db->prepare('SELECT provider_id FROM proveedores.user_providers WHERE user_id = :user');
            $stmt->execute(['user' => (int)$sessionUser['id']]);
            $linked = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($linked as $providerId) {
                $ids[] = (int)$providerId;
            }
        }

        return array_values(array_unique(array_filter($ids, static fn($id) => $id > 0)));
    }

    protected function parsePgArray($value, bool $castInt = false): array
    {
        if ($value === null) {
            return [];
        }

        if (is_array($value)) {
            return $castInt ? array_map('intval', $value) : $value;
        }

        $trimmed = trim((string)$value, '{}');
        if ($trimmed === '') {
            return [];
        }

        $items = array_map('trim', explode(',', $trimmed));

        return $castInt ? array_map('intval', $items) : $items;
    }
}
