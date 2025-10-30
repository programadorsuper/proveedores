<?php

class Proveedor
{
    protected \PDO $db;

    public function __construct()
    {
        $this->db = Database::pgsql();
    }

    public function login(string $username, string $password): ?array
    {
        $record = $this->findByUsername($username);

        if (!$record || !(bool)$record['is_active']) {
            return null;
        }

        $plain = (string)($record['password_plain'] ?? '');
        $hash = $record['password_hash'] ?? null;

        $plainMatches = $plain !== '' && hash_equals($plain, $password);
        $hashMatches = is_string($hash) && $hash !== '' && password_verify($password, $hash);

        if (!($plainMatches || $hashMatches)) {
            return null;
        }

        $hydrated = [
            'id' => (int)$record['id'],
            'username' => $record['username'],
            'provider_id' => $record['provider_id'] !== null ? (int)$record['provider_id'] : null,
            'roles' => $record['roles'] ?? [],
            'allowed_days' => $record['allowed_days'] ?? [],
            'is_active' => (bool)$record['is_active'],
            'is_super_admin' => in_array('super_admin', $record['roles'] ?? [], true),
            'is_provider_admin' => in_array('provider_admin', $record['roles'] ?? [], true),
        ];

        $hydrated['provider_ids'] = $this->fetchUserProviderIds($hydrated['id']);

        return $hydrated;
    }

    public function findByUsername(string $username): ?array
    {
        $sql = "
            SELECT u.*,
                   array_remove(array_agg(DISTINCT r.code), NULL) AS roles
            FROM proveedores.users u
            LEFT JOIN proveedores.user_roles ur ON ur.user_id = u.id
            LEFT JOIN proveedores.roles r ON r.id = ur.role_id
            WHERE u.username = :username
            GROUP BY u.id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['username' => $username]);

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        if (isset($row['roles'])) {
            $row['roles'] = $this->parsePgArray($row['roles']);
        } else {
            $row['roles'] = [];
        }

        if (isset($row['allowed_days'])) {
            $row['allowed_days'] = $this->parsePgArray($row['allowed_days'], true);
        }

        return $row;
    }

    public function recordLogin(int $userId, string $ip, string $userAgent): void
    {
        $auditSql = "
            INSERT INTO proveedores.audit_logs (user_id, provider_id, action, context, ip_address, user_agent)
            VALUES (
                :user_id,
                (SELECT provider_id FROM proveedores.users WHERE id = :user_id),
                'login',
                json_build_object('ip', :ip),
                :ip,
                :agent
            )
        ";

        $this->db->beginTransaction();
        try {
            $update = $this->db->prepare("
                UPDATE proveedores.users
                SET last_login_at = NOW(), updated_at = NOW()
                WHERE id = :user_id
            ");
            $update->execute(['user_id' => $userId]);

            $audit = $this->db->prepare($auditSql);
            $audit->execute([
                'user_id' => $userId,
                'ip' => $ip,
                'agent' => $userAgent,
            ]);

            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            // No interrumpas la experiencia de login; solo registra el error.
            error_log('[proveedores_mvc] Error registrando login: ' . $exception->getMessage());
        }
    }

    public function getMenus(array $sessionUser): array
    {
        $isProviderAdmin = !empty($sessionUser['is_provider_admin']);
        $isSuperAdmin = !empty($sessionUser['is_super_admin']);
        $roles = $sessionUser['roles'] ?? [];

        if ($isSuperAdmin) {
            $menus = $this->fetchAllMenus();
        } else {
            $visible = $this->fetchMenusByVisibility($isProviderAdmin, $isSuperAdmin);
            $byRoles = !empty($roles) ? $this->fetchMenusByRoles($roles) : [];
            $menus = $this->mergeMenus($visible, $byRoles);
        }

        if (empty($menus)) {
            return [];
        }

        // For any menu that references a parent, ensure the parent record is loaded too.
        $parentIds = array_unique(array_map(
            'intval',
            array_filter(
                array_map(static fn($menu) => $menu['parent_id'] ?? null, $menus),
                static fn($id) => $id !== null
            )
        ));

        $existingIds = array_map(static fn($menu) => (int)$menu['id'], $menus);
        $missingParents = array_diff($parentIds, $existingIds);
        if (!empty($missingParents)) {
            $parents = $this->fetchMenusByIds($missingParents);
            $menus = $this->mergeMenus($menus, $parents);
        }

        // Re-index by id and build tree
        $indexed = [];
        foreach ($menus as $menu) {
            $indexed[(int)$menu['id']] = $this->normalizeMenuRow($menu);
        }

        // Attach children
        $tree = [];
        foreach ($indexed as $id => &$menu) {
            $parentId = $menu['parent_id'];
            if ($parentId !== null && isset($indexed[$parentId])) {
                $indexed[$parentId]['children'][] =& $menu;
            } else {
                $tree[] =& $menu;
            }
        }
        unset($menu); // break reference

        $sortFn = static function (array &$items) use (&$sortFn) {
            usort($items, static fn($a, $b) => [$a['sort_order'], $a['label']] <=> [$b['sort_order'], $b['label']]);
            foreach ($items as &$item) {
                if (!empty($item['children'])) {
                    $sortFn($item['children']);
                }
            }
            unset($item);
        };

        $sortFn($tree);

        return $tree;
    }

    protected function fetchAllMenus(): array
    {
        $sql = "
            SELECT id, label, route_name, icon, sort_order, visibility, parent_id
            FROM proveedores.menus
            ORDER BY sort_order, label
        ";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    protected function fetchMenusByVisibility(bool $isProviderAdmin, bool $isSuperAdmin): array
    {
        $isAdmin = $isProviderAdmin || $isSuperAdmin;
        $sql = "
            SELECT id, label, route_name, icon, sort_order, visibility, parent_id
            FROM proveedores.menus
            WHERE visibility = 'all'
               OR (visibility = 'admin' AND :is_admin = TRUE)
               OR (visibility = 'super_admin' AND :is_super_admin = TRUE)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue('is_admin', $isAdmin, \PDO::PARAM_BOOL);
        $stmt->bindValue('is_super_admin', $isSuperAdmin, \PDO::PARAM_BOOL);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    protected function fetchMenusByRoles(array $roles): array
    {
        $roleCodes = array_values(array_filter(array_map('strval', $roles)));
        if (empty($roleCodes)) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($roleCodes), '?'));
        $sql = "
            SELECT DISTINCT m.id, m.label, m.route_name, m.icon, m.sort_order, m.visibility, m.parent_id
            FROM proveedores.menus m
            INNER JOIN proveedores.menu_roles mr ON mr.menu_id = m.id
            INNER JOIN proveedores.roles r ON r.id = mr.role_id
            WHERE r.code IN ($placeholders)
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($roleCodes);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    protected function fetchMenusByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $sql = "
            SELECT id, label, route_name, icon, sort_order, visibility, parent_id
            FROM proveedores.menus
            WHERE id IN ($placeholders)
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($ids));
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    protected function mergeMenus(array $base, array $extra): array
    {
        $indexed = [];
        foreach ($base as $menu) {
            $indexed[(int)$menu['id']] = $menu;
        }

        foreach ($extra as $menu) {
            $indexed[(int)$menu['id']] = $menu;
        }

        return array_values($indexed);
    }

    protected function fetchUserProviderIds(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT provider_id FROM proveedores.user_providers WHERE user_id = :id ORDER BY provider_id');
        $stmt->execute(['id' => $userId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];

        $ids = array_map('intval', $rows);

        // ensure main provider included
        $stmt = $this->db->prepare('SELECT provider_id FROM proveedores.users WHERE id = :id');
        $stmt->execute(['id' => $userId]);
        $primary = $stmt->fetchColumn();
        if ($primary !== false) {
            $ids[] = (int)$primary;
        }

        return array_values(array_unique(array_filter($ids, static fn($id) => $id > 0)));
    }

    protected function normalizeMenuRow(array $menu): array
    {
        $routeName = (string)($menu['route_name'] ?? '');
        return [
            'id' => (int)$menu['id'],
            'label' => $menu['label'] ?? '',
            'route_name' => $routeName,
            'route' => $this->resolveRoutePath($routeName),
            'icon' => $menu['icon'] ?: 'fa-solid fa-circle',
            'sort_order' => (int)($menu['sort_order'] ?? 100),
            'visibility' => $menu['visibility'] ?? 'all',
            'parent_id' => isset($menu['parent_id']) ? (int)$menu['parent_id'] : null,
            'children' => [],
        ];
    }

    protected function resolveRoutePath(string $routeName): string
    {
        static $map = [
            'dashboard.index' => '/home',
            'sellinout.index' => '/ventas/sellinout',
            'sales.index' => '/ventas',
            'sales.periods' => '/ventas/periodos',
            'sales.sellout' => '/ventas/sellout',
            'purchases.index' => '/compras',
            'purchases.periods' => '/compras/periodos',
            'purchases.sellin' => '/compras/sellin',
            'orders.index' => '/ordenes',
            'orders.news' => '/ordenes/nuevas',
            'orders.backorders' => '/ordenes/backorder',
            'orders.entries' => '/ordenes/entradas',
            'providers.index' => '/proveedores',
            'others.index' => '/otros',
            'others.returns' => '/otros/devoluciones',
            'others.inventory' => '/otros/inventario',
            'users.index' => '/usuarios',
            'users.admin' => '/usuarios/admin',
            'notifications.index' => '/alertas',
            'reports.index' => '/reportes',
            'reports.sales' => '/reportes/ventas',
            'reports.orders' => '/reportes/ordenes',
            'reports.purchases' => '/reportes/compras',
        ];

        if ($routeName === '') {
            return '#';
        }

        if (isset($map[$routeName])) {
            return $map[$routeName];
        }

        return '/' . str_replace('.', '/', $routeName);
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
