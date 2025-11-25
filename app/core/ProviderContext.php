<?php

require_once __DIR__ . '/Database.php';

/**
 * Mantiene el contexto de proveedor/membresía y accesos por módulo
 * para la sesión actual.
 */
class ProviderContext
{
    protected array $user;
    protected \PDO $db;
    protected ?int $primaryProviderId;
    protected array $providerIds = [];
    protected array $moduleAccess = [];
    protected string $membershipPlan = 'free';

    public function __construct(array $user)
    {
        $this->user = $user;
        $this->db = Database::pgsql();
        $this->primaryProviderId = isset($user['provider_id']) ? (int)$user['provider_id'] : null;
        $this->providerIds = $this->resolveProviderIds();

        if ($this->primaryProviderId !== null && $this->primaryProviderId > 0) {
            $this->membershipPlan = $this->fetchMembershipPlan($this->primaryProviderId);
            $this->moduleAccess = $this->fetchModuleAccess($this->primaryProviderId);
        }
    }

    public function user(): array
    {
        return $this->user;
    }

    public function primaryProviderId(): ?int
    {
        return $this->primaryProviderId;
    }

    public function providerIds(): array
    {
        return $this->providerIds;
    }

    public function membershipPlan(): string
    {
        return $this->membershipPlan;
    }

    public function isPro(): bool
    {
        return in_array($this->membershipPlan, ['pro', 'enterprise'], true);
    }

    public function isEnterprise(): bool
    {
        return $this->membershipPlan === 'enterprise';
    }

    public function moduleAccess(): array
    {
        return $this->moduleAccess;
    }

    public function moduleInfo(string $moduleCode): ?array
    {
        $code = strtolower(trim($moduleCode));
        return $this->moduleAccess[$code] ?? null;
    }

    public function canAccessModule(string $moduleCode): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $info = $this->moduleInfo($moduleCode);
        if ($info === null) {
            return false;
        }

        return (bool)($info['can_access'] ?? false);
    }

    public function isSuperAdmin(): bool
    {
        return in_array('super_admin', $this->user['roles'] ?? [], true);
    }

    public function isProviderAdmin(): bool
    {
        return in_array('provider_admin', $this->user['roles'] ?? [], true);
    }

    protected function resolveProviderIds(): array
    {
        $ids = [];
        if ($this->primaryProviderId) {
            $ids[] = $this->primaryProviderId;
        }

        if (!empty($this->user['provider_ids']) && is_array($this->user['provider_ids'])) {
            foreach ($this->user['provider_ids'] as $providerId) {
                $ids[] = (int)$providerId;
            }
        }

        if (!empty($this->user['id'])) {
            $stmt = $this->db->prepare('SELECT provider_id FROM proveedores.user_providers WHERE user_id = :uid');
            $stmt->execute(['uid' => (int)$this->user['id']]);
            $linked = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            foreach ($linked as $providerId) {
                $ids[] = (int)$providerId;
            }
        }

        $ids = array_values(array_unique(array_filter($ids, static fn($id) => $id > 0)));

        sort($ids);
        return $ids;
    }

    protected function fetchMembershipPlan(int $providerId): string
    {
        try {
            $stmt = $this->db->prepare('SELECT proveedores.fn_current_membership_plan(:pid) AS plan');
            $stmt->execute(['pid' => $providerId]);
            $plan = $stmt->fetchColumn();
            return $plan ? (string)$plan : 'free';
        } catch (\Throwable $exception) {
            error_log('[ProviderContext] Error obteniendo plan de membresia: ' . $exception->getMessage());
            return 'free';
        }
    }

    protected function fetchModuleAccess(int $providerId): array
    {
        $access = [];
        try {
            $sql = "
                SELECT module_code, can_access, tier, is_enabled, metadata, has_unlock, unlock_expires_at
                FROM proveedores.vw_provider_module_effective
                WHERE provider_id = :pid
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['pid' => $providerId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $row) {
                $code = strtolower((string)$row['module_code']);
                $access[$code] = [
                    'module_code' => $code,
                    'can_access' => (bool)$row['can_access'],
                    'tier' => (string)($row['tier'] ?? 'free'),
                    'is_enabled' => (bool)($row['is_enabled'] ?? false),
                    'metadata' => is_array($row['metadata']) ? $row['metadata'] : json_decode($row['metadata'] ?? '{}', true),
                    'has_unlock' => (bool)($row['has_unlock'] ?? false),
                    'unlock_expires_at' => $row['unlock_expires_at'] ?? null,
                ];
            }
        } catch (\Throwable $exception) {
            error_log('[ProviderContext] Error obteniendo accesos de modulos: ' . $exception->getMessage());
        }

        return $access;
    }
}
