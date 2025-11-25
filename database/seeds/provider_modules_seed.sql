SET search_path TO proveedores, public;

-- Habilita automaticamente modulos tier FREE para todos los proveedores activos/pending
WITH free_modules AS (
    SELECT id
    FROM proveedores.modules
    WHERE tier = 'free'
      AND is_active = TRUE
),
live_providers AS (
    SELECT id
    FROM proveedores.providers
    WHERE status IN ('active', 'pending')
)
INSERT INTO proveedores.provider_modules (provider_id, module_id, is_enabled, enabled_at, enabled_by)
SELECT p.id, m.id, TRUE, NOW(), NULL
FROM live_providers p
CROSS JOIN free_modules m
ON CONFLICT (provider_id, module_id) DO UPDATE
    SET is_enabled = EXCLUDED.is_enabled,
        enabled_at = EXCLUDED.enabled_at,
        enabled_by = EXCLUDED.enabled_by;


-- Inicializa cuotas base (ejemplo: 5 exportes diarios pro)
INSERT INTO proveedores.provider_quotas (provider_id, module_id, quota_key, quota_value)
SELECT
    pm.provider_id,
    pm.module_id,
    'exports_per_day',
    5
FROM proveedores.provider_modules pm
JOIN proveedores.modules m ON m.id = pm.module_id
WHERE m.code = 'exports'
ON CONFLICT (provider_id, module_id, quota_key) DO NOTHING;
