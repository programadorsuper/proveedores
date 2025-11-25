-- Esquema PostgreSQL para Proveedor Nova Hub
-- Ejecutar en una instancia PostgreSQL con permisos suficientes.

CREATE SCHEMA IF NOT EXISTS proveedores AUTHORIZATION CURRENT_USER;
SET search_path TO proveedores, public;

-- Tabla principal de proveedores
CREATE TABLE IF NOT EXISTS proveedores.providers (
    id                BIGSERIAL PRIMARY KEY,
    external_id       INTEGER,
    slug              TEXT UNIQUE,
    name              TEXT NOT NULL,
    legal_name        TEXT,
    rfc               TEXT,
    contact_name      TEXT,
    contact_email     TEXT,
    contact_phone     TEXT,
    status            TEXT NOT NULL DEFAULT 'draft', -- draft|pending|active|suspended|archived
    activation_date   DATE,
    onboarding_notes  TEXT,
    base_timezone     TEXT DEFAULT 'America/Mexico_City',
    created_at        TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at        TIMESTAMPTZ NOT NULL DEFAULT NOW()
);


DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'providers_external_id_unique'
          AND conrelid = 'proveedores.providers'::regclass
    ) THEN
        ALTER TABLE proveedores.providers
            ADD CONSTRAINT providers_external_id_unique UNIQUE (external_id);
    END IF;
END
$$;


-- Membresías anuales y control de pagos
-- Roles base
CREATE TABLE IF NOT EXISTS proveedores.roles (
    id          SMALLSERIAL PRIMARY KEY,
    code        TEXT NOT NULL UNIQUE, -- super_admin|provider_admin|provider_user
    name        TEXT NOT NULL,
    description TEXT
);


-- Membresías anuales y control de pagos
CREATE TABLE IF NOT EXISTS proveedores.provider_memberships (
    id                   BIGSERIAL PRIMARY KEY,
    provider_id          BIGINT NOT NULL REFERENCES proveedores.providers(id) ON DELETE CASCADE,
    year                 INTEGER NOT NULL CHECK (year >= 2020),
    status               TEXT NOT NULL DEFAULT 'pending', -- pending|active|grace|suspended|archived
    payment_due_date     DATE NOT NULL, -- normalmente 2025-01-15
    grace_expires_at     DATE NOT NULL, -- normalmente 2025-01-16
    payment_date         DATE,
    activated_by_user_id BIGINT REFERENCES proveedores.users(id),
    activation_source    TEXT DEFAULT 'supernet', -- supernet|manual|auto
    notes                TEXT,
    last_notified_at     TIMESTAMPTZ,
    notification_steps   INTEGER[] DEFAULT ARRAY[]::INTEGER[],
    created_at           TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at           TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (provider_id, year)
);


-- Usuarios de la plataforma
CREATE TABLE IF NOT EXISTS proveedores.users (
    id                 BIGSERIAL PRIMARY KEY,
    provider_id        BIGINT REFERENCES proveedores.providers(id) ON DELETE SET NULL,
    parent_user_id     BIGINT REFERENCES proveedores.users(id) ON DELETE SET NULL,
    username           TEXT NOT NULL UNIQUE,
    email              TEXT,
    password_plain     TEXT NOT NULL,
    password_hash      TEXT NOT NULL,
    must_change_password BOOLEAN NOT NULL DEFAULT FALSE,
    is_active          BOOLEAN NOT NULL DEFAULT TRUE,
    is_collapsed       BOOLEAN NOT NULL DEFAULT FALSE,
    allowed_days       INTEGER[] NOT NULL DEFAULT ARRAY[1,2,3,4,5],
    max_child_users    INTEGER NOT NULL DEFAULT 5,
    last_login_at      TIMESTAMPTZ,
    last_password_reset TIMESTAMPTZ,
    locale             TEXT DEFAULT 'es_MX',
    created_at         TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at         TIMESTAMPTZ NOT NULL DEFAULT NOW()
);


-- Relación usuario ↔ rol
CREATE TABLE IF NOT EXISTS proveedores.user_roles (
    user_id BIGINT NOT NULL REFERENCES proveedores.users(id) ON DELETE CASCADE,
    role_id SMALLINT NOT NULL REFERENCES proveedores.roles(id) ON DELETE CASCADE,
    assigned_by BIGINT REFERENCES proveedores.users(id),
    assigned_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY (user_id, role_id)
);


-- Sesiones persistentes (remember me)
CREATE TABLE IF NOT EXISTS proveedores.user_sessions (
    id             BIGSERIAL PRIMARY KEY,
    user_id        BIGINT NOT NULL REFERENCES proveedores.users(id) ON DELETE CASCADE,
    selector_hash  TEXT NOT NULL UNIQUE,
    validator_hash TEXT NOT NULL,
    ip_address     TEXT,
    user_agent     TEXT,
    last_seen_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    expires_at     TIMESTAMPTZ NOT NULL,
    is_revoked     BOOLEAN NOT NULL DEFAULT FALSE,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at     TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS user_sessions_user_id_index
    ON proveedores.user_sessions (user_id);


-- Seguimiento de vistas de ordenes
CREATE TABLE IF NOT EXISTS proveedores.order_views (
    id             BIGSERIAL PRIMARY KEY,
    order_id       BIGINT NOT NULL,
    provider_id    BIGINT REFERENCES proveedores.providers(id) ON DELETE SET NULL,
    user_id        BIGINT NOT NULL REFERENCES proveedores.users(id) ON DELETE CASCADE,
    first_seen_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    last_seen_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    seen_count     INTEGER NOT NULL DEFAULT 1,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at     TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (order_id, user_id)
);

CREATE INDEX IF NOT EXISTS order_views_order_idx
    ON proveedores.order_views (order_id);

CREATE INDEX IF NOT EXISTS order_views_provider_idx
    ON proveedores.order_views (provider_id);


-- Citas y logistica de entregas
CREATE TABLE IF NOT EXISTS proveedores.appointments (
    id                  BIGSERIAL PRIMARY KEY,
    folio               TEXT UNIQUE,
    provider_id         BIGINT NOT NULL REFERENCES proveedores.providers(id) ON DELETE CASCADE,
    created_by          BIGINT NOT NULL REFERENCES proveedores.users(id),
    delivery_point_code TEXT,
    delivery_point_name TEXT,
    delivery_point_type TEXT,
    delivery_address    TEXT,
    appointment_date    DATE NOT NULL,
    slot_start          TIME NOT NULL,
    slot_end            TIME NOT NULL,
    status              TEXT NOT NULL DEFAULT 'in_process' CHECK (status IN (
        'in_process',
        'accepted',
        'rejected',
        'cancelled',
        'delivered'
    )),
    status_reason       TEXT,
    status_payload      JSONB DEFAULT '{}'::JSONB,
    status_changed_by   BIGINT REFERENCES proveedores.users(id),
    status_changed_at   TIMESTAMPTZ,
    metadata            JSONB DEFAULT '{}'::JSONB,
    created_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at          TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    cancelled_at        TIMESTAMPTZ
);

CREATE INDEX IF NOT EXISTS appointments_provider_idx
    ON proveedores.appointments (provider_id);

CREATE INDEX IF NOT EXISTS appointments_status_idx
    ON proveedores.appointments (status);

CREATE TABLE IF NOT EXISTS proveedores.appointment_documents (
    id                    BIGSERIAL PRIMARY KEY,
    appointment_id        BIGINT NOT NULL REFERENCES proveedores.appointments(id) ON DELETE CASCADE,
    provider_id           BIGINT NOT NULL REFERENCES proveedores.providers(id) ON DELETE CASCADE,
    document_type         TEXT NOT NULL,
    document_id           BIGINT,
    document_reference    TEXT NOT NULL,
    delivery_point_code   TEXT,
    delivery_point_name   TEXT,
    status                TEXT NOT NULL DEFAULT 'draft' CHECK (status IN (
        'draft',
        'pending',
        'completed',
        'cancelled'
    )),
    requested_total       NUMERIC(18,2),
    invoiced_total        NUMERIC(18,2),
    summary               JSONB DEFAULT '{}'::JSONB,
    closed_at             TIMESTAMPTZ,
    created_at            TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at            TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS appointment_documents_app_idx
    ON proveedores.appointment_documents (appointment_id);

CREATE TABLE IF NOT EXISTS proveedores.appointment_document_items (
    id                       BIGSERIAL PRIMARY KEY,
    appointment_document_id  BIGINT NOT NULL REFERENCES proveedores.appointment_documents(id) ON DELETE CASCADE,
    product_code             TEXT,
    sku                      TEXT,
    description              TEXT,
    unit_requested           TEXT,
    unit_invoiced            TEXT,
    ordered_quantity         NUMERIC(18,4),
    invoiced_quantity        NUMERIC(18,4),
    status                   TEXT DEFAULT 'pending',
    metadata                 JSONB DEFAULT '{}'::JSONB
);

CREATE INDEX IF NOT EXISTS appointment_document_items_doc_idx
    ON proveedores.appointment_document_items (appointment_document_id);

CREATE TABLE IF NOT EXISTS proveedores.appointment_files (
    id                       BIGSERIAL PRIMARY KEY,
    appointment_document_id  BIGINT NOT NULL REFERENCES proveedores.appointment_documents(id) ON DELETE CASCADE,
    file_type                TEXT NOT NULL,
    storage_path             TEXT NOT NULL,
    original_name            TEXT NOT NULL,
    mime_type                TEXT,
    size_bytes               BIGINT,
    checksum                 TEXT,
    metadata                 JSONB DEFAULT '{}'::JSONB,
    created_at               TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS appointment_files_doc_idx
    ON proveedores.appointment_files (appointment_document_id);

CREATE TABLE IF NOT EXISTS proveedores.appointment_events (
    id             BIGSERIAL PRIMARY KEY,
    appointment_id BIGINT NOT NULL REFERENCES proveedores.appointments(id) ON DELETE CASCADE,
    event_type     TEXT NOT NULL,
    payload        JSONB DEFAULT '{}'::JSONB,
    created_by     BIGINT REFERENCES proveedores.users(id),
    created_at     TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS appointment_events_app_idx
    ON proveedores.appointment_events (appointment_id);

CREATE TABLE IF NOT EXISTS proveedores.document_reservations (
    id             BIGSERIAL PRIMARY KEY,
    document_type  TEXT NOT NULL,
    document_id    BIGINT NOT NULL,
    provider_id    BIGINT NOT NULL REFERENCES proveedores.providers(id) ON DELETE CASCADE,
    appointment_id BIGINT NOT NULL REFERENCES proveedores.appointments(id) ON DELETE CASCADE,
    delivery_point_code TEXT,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (document_type, document_id)
);

CREATE INDEX IF NOT EXISTS document_reservations_provider_idx
    ON proveedores.document_reservations (provider_id);



-- Módulos disponibles en la plataforma
CREATE TABLE IF NOT EXISTS proveedores.modules (
    id           SMALLSERIAL PRIMARY KEY,
    code         TEXT NOT NULL UNIQUE, -- dashboard|orders|purchases|sales|sales_compare|returns|b2b|kawaii|analytics|api
    name         TEXT NOT NULL,
    category     TEXT NOT NULL DEFAULT 'core',
    description  TEXT,
    sort_order   INTEGER NOT NULL DEFAULT 100,
    is_active    BOOLEAN NOT NULL DEFAULT TRUE,
    created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at   TIMESTAMPTZ NOT NULL DEFAULT NOW()
);


-- Módulos habilitados por proveedor
CREATE TABLE IF NOT EXISTS proveedores.provider_modules (
    id           BIGSERIAL PRIMARY KEY,
    provider_id  BIGINT NOT NULL REFERENCES proveedores.providers(id) ON DELETE CASCADE,
    module_id    SMALLINT NOT NULL REFERENCES proveedores.modules(id) ON DELETE CASCADE,
    is_enabled   BOOLEAN NOT NULL DEFAULT TRUE,
    enabled_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    enabled_by   BIGINT REFERENCES proveedores.users(id),
    UNIQUE (provider_id, module_id)
);


-- Módulos habilitados por usuario
CREATE TABLE IF NOT EXISTS proveedores.user_modules (
    id           BIGSERIAL PRIMARY KEY,
    user_id      BIGINT NOT NULL REFERENCES proveedores.users(id) ON DELETE CASCADE,
    module_id    SMALLINT NOT NULL REFERENCES proveedores.modules(id) ON DELETE CASCADE,
    granted_by   BIGINT REFERENCES proveedores.users(id),
    granted_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    expires_at   TIMESTAMPTZ,
    UNIQUE (user_id, module_id)
);


-- Relación usuario ↔ proveedores vinculados
CREATE TABLE IF NOT EXISTS proveedores.user_providers (
    user_id     BIGINT NOT NULL REFERENCES proveedores.users(id) ON DELETE CASCADE,
    provider_id BIGINT NOT NULL REFERENCES proveedores.providers(id) ON DELETE CASCADE,
    PRIMARY KEY (user_id, provider_id)
);

CREATE INDEX IF NOT EXISTS idx_user_providers_user ON proveedores.user_providers(user_id);
CREATE INDEX IF NOT EXISTS idx_user_providers_provider ON proveedores.user_providers(provider_id);


-- Menú de navegación configurable
CREATE TABLE IF NOT EXISTS proveedores.menus (
    id           BIGSERIAL PRIMARY KEY,
    module_id    SMALLINT NOT NULL REFERENCES proveedores.modules(id) ON DELETE CASCADE,
    label        TEXT NOT NULL,
    route_name   TEXT NOT NULL,
    icon         TEXT,
    sort_order   INTEGER NOT NULL DEFAULT 100,
    parent_id    BIGINT REFERENCES proveedores.menus(id) ON DELETE SET NULL,
    visibility   TEXT NOT NULL DEFAULT 'all', -- all|admin|super_admin
    created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at   TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE UNIQUE INDEX IF NOT EXISTS menus_route_name_unique
    ON proveedores.menus(route_name);


-- Vistas de menú permitidas por rol
CREATE TABLE IF NOT EXISTS proveedores.menu_roles (
    menu_id BIGINT NOT NULL REFERENCES proveedores.menus(id) ON DELETE CASCADE,
    role_id SMALLINT NOT NULL REFERENCES proveedores.roles(id) ON DELETE CASCADE,
    PRIMARY KEY (menu_id, role_id)
);


-- Notificaciones generadas por el sistema
CREATE TABLE IF NOT EXISTS proveedores.notifications (
    id            BIGSERIAL PRIMARY KEY,
    provider_id   BIGINT REFERENCES proveedores.providers(id) ON DELETE CASCADE,
    user_id       BIGINT REFERENCES proveedores.users(id) ON DELETE CASCADE,
    type          TEXT NOT NULL, -- payment_reminder|payment_overdue|order_downloaded|report_ready|custom
    title         TEXT NOT NULL,
    message       TEXT NOT NULL,
    payload       JSONB,
    severity      TEXT NOT NULL DEFAULT 'info', -- info|warning|critical|success
    is_read       BOOLEAN NOT NULL DEFAULT FALSE,
    read_at       TIMESTAMPTZ,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW()
);


-- Auditoría de descargas y confirmaciones
CREATE TABLE IF NOT EXISTS proveedores.data_exports (
    id             BIGSERIAL PRIMARY KEY,
    provider_id    BIGINT NOT NULL REFERENCES proveedores.providers(id) ON DELETE CASCADE,
    user_id        BIGINT NOT NULL REFERENCES proveedores.users(id) ON DELETE CASCADE,
    module_id      SMALLINT NOT NULL REFERENCES proveedores.modules(id) ON DELETE CASCADE,
    reference      TEXT NOT NULL,
    export_type    TEXT NOT NULL, -- orders|sales|purchases|returns|custom
    filters        JSONB,
    compared_with  JSONB,
    exported_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    acknowledged_at TIMESTAMPTZ,
    acknowledged_by BIGINT REFERENCES proveedores.users(id)
);


-- Tokens para consumo de la API REST
CREATE TABLE IF NOT EXISTS proveedores.api_tokens (
    id             BIGSERIAL PRIMARY KEY,
    user_id        BIGINT NOT NULL REFERENCES proveedores.users(id) ON DELETE CASCADE,
    provider_id    BIGINT REFERENCES proveedores.providers(id) ON DELETE SET NULL,
    name           TEXT NOT NULL,
    token          TEXT NOT NULL UNIQUE,
    scopes         TEXT[] NOT NULL DEFAULT ARRAY['read'],
    expires_at     TIMESTAMPTZ,
    last_used_at   TIMESTAMPTZ,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    revoked_at     TIMESTAMPTZ
);


-- Bitácora general
CREATE TABLE IF NOT EXISTS proveedores.audit_logs (
    id            BIGSERIAL PRIMARY KEY,
    user_id       BIGINT REFERENCES proveedores.users(id) ON DELETE SET NULL,
    provider_id   BIGINT REFERENCES proveedores.providers(id) ON DELETE SET NULL,
    action        TEXT NOT NULL,
    context       JSONB,
    ip_address    TEXT,
    user_agent    TEXT,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW()
);


-- Preferencias por usuario (colapsar dashboard, filtros, etc.)
CREATE TABLE IF NOT EXISTS proveedores.user_preferences (
    user_id     BIGINT PRIMARY KEY REFERENCES proveedores.users(id) ON DELETE CASCADE,
    preferences JSONB NOT NULL DEFAULT '{}'::JSONB,
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT NOW()
);


-- Jobs programados para avisos automáticos
CREATE TABLE IF NOT EXISTS proveedores.scheduled_jobs (
    id            BIGSERIAL PRIMARY KEY,
    job_type      TEXT NOT NULL, -- payment_reminder|stats_refresh|sync_firebird
    payload       JSONB NOT NULL,
    scheduled_at  TIMESTAMPTZ NOT NULL,
    processed_at  TIMESTAMPTZ,
    status        TEXT NOT NULL DEFAULT 'pending', -- pending|running|done|failed
    result        JSONB,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW()
);


-- Vistas materializadas sugeridas (no se crean automáticamente)
-- CREATE MATERIALIZED VIEW proveedores.mv_sales_summary ...


-- ---------------------------------------------------------------------------
-- Extensiones de modelo para licenciamiento, métricas y scoping
-- ---------------------------------------------------------------------------

ALTER TABLE proveedores.modules
    ADD COLUMN IF NOT EXISTS tier TEXT NOT NULL DEFAULT 'free', -- free|pro|enterprise
    ADD COLUMN IF NOT EXISTS metadata JSONB NOT NULL DEFAULT '{}'::JSONB;

ALTER TABLE proveedores.provider_memberships
    ADD COLUMN IF NOT EXISTS plan TEXT NOT NULL DEFAULT 'free', -- free|pro|enterprise
    ADD COLUMN IF NOT EXISTS activated_at TIMESTAMPTZ,
    ADD COLUMN IF NOT EXISTS expires_at TIMESTAMPTZ,
    ADD COLUMN IF NOT EXISTS auto_renew BOOLEAN NOT NULL DEFAULT FALSE;


-- Límite y métricas de uso por proveedor-módulo
CREATE TABLE IF NOT EXISTS proveedores.provider_quotas (
    id           BIGSERIAL PRIMARY KEY,
    provider_id  BIGINT NOT NULL REFERENCES proveedores.providers(id) ON DELETE CASCADE,
    module_id    SMALLINT NOT NULL REFERENCES proveedores.modules(id) ON DELETE CASCADE,
    quota_key    TEXT NOT NULL,             -- p.ej. exports_per_day, api_calls_month
    quota_value  BIGINT NOT NULL DEFAULT 0,
    period_start TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    period_end   TIMESTAMPTZ,
    updated_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (provider_id, module_id, quota_key)
);


-- Unlocks manuales (por año, campaña, trial, etc.)
CREATE TABLE IF NOT EXISTS proveedores.provider_unlocks (
    id              BIGSERIAL PRIMARY KEY,
    provider_id     BIGINT NOT NULL REFERENCES proveedores.providers(id) ON DELETE CASCADE,
    module_id       SMALLINT NOT NULL REFERENCES proveedores.modules(id) ON DELETE CASCADE,
    unlock_type     TEXT NOT NULL DEFAULT 'manual', -- manual|trial|campaign
    unlocked_at     TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    expires_at      TIMESTAMPTZ,
    granted_by_user BIGINT REFERENCES proveedores.users(id),
    notes           TEXT,
    UNIQUE (provider_id, module_id, unlock_type, unlocked_at)
);


-- SKUs por proveedor para mapear productos externos
CREATE TABLE IF NOT EXISTS proveedores.provider_skus (
    id           BIGSERIAL PRIMARY KEY,
    provider_id  BIGINT NOT NULL REFERENCES proveedores.providers(id) ON DELETE CASCADE,
    product_id   BIGINT,
    sku          TEXT,
    vendor_code  TEXT,
    product_name TEXT,
    created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (provider_id, sku),
    UNIQUE (provider_id, product_id)
);


-- Tickets revisados por proveedor/usuario
CREATE TABLE IF NOT EXISTS proveedores.ticket_reviews (
    id           BIGSERIAL PRIMARY KEY,
    provider_id  BIGINT NOT NULL REFERENCES proveedores.providers(id) ON DELETE CASCADE,
    user_id      BIGINT NOT NULL REFERENCES proveedores.users(id) ON DELETE CASCADE,
    ticket_id    BIGINT NOT NULL,
    reviewed_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    notes        TEXT,
    status       TEXT NOT NULL DEFAULT 'reviewed', -- reviewed|flagged
    UNIQUE (provider_id, ticket_id)
);


-- Puntos para concursos/promociones por ticket
CREATE TABLE IF NOT EXISTS proveedores.ticket_points (
    id           BIGSERIAL PRIMARY KEY,
    provider_id  BIGINT NOT NULL REFERENCES proveedores.providers(id) ON DELETE CASCADE,
    ticket_id    BIGINT NOT NULL,
    points       NUMERIC(14,2) NOT NULL DEFAULT 0,
    reason       TEXT,
    created_at   TIMESTAMPTZ NOT NULL DEFAULT NOW()
);


-- Bitácora de actividades por usuario (supervisión admin proveedor / super admin)
CREATE TABLE IF NOT EXISTS proveedores.user_activity_logs (
    id            BIGSERIAL PRIMARY KEY,
    provider_id   BIGINT REFERENCES proveedores.providers(id) ON DELETE SET NULL,
    actor_user_id BIGINT REFERENCES proveedores.users(id) ON DELETE SET NULL,
    target_user_id BIGINT REFERENCES proveedores.users(id) ON DELETE SET NULL,
    action        TEXT NOT NULL, -- login|logout|create_user|update_user|delete_user|download|module_access|ticket_review
    context       JSONB,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW()
);


-- Sincronizaciones con Firebird (órdenes, inventario, tickets)
CREATE TABLE IF NOT EXISTS proveedores.firebird_sync_runs (
    id             BIGSERIAL PRIMARY KEY,
    sync_type      TEXT NOT NULL, -- orders|inventory|tickets
    started_at     TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    finished_at    TIMESTAMPTZ,
    status         TEXT NOT NULL DEFAULT 'running', -- running|completed|failed
    stats          JSONB,
    error_message  TEXT
);


-- Staging de órdenes de compra provenientes de Firebird
CREATE TABLE IF NOT EXISTS proveedores.firebird_purchase_orders (
    id               BIGSERIAL PRIMARY KEY,
    provider_id      BIGINT NOT NULL REFERENCES proveedores.providers(id) ON DELETE CASCADE,
    external_id      TEXT NOT NULL,
    order_number     TEXT,
    order_date       DATE,
    expected_date    DATE,
    status           TEXT,
    currency         TEXT,
    total_amount     NUMERIC(16,2),
    payload          JSONB,
    imported_at      TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    last_download_at TIMESTAMPTZ,
    UNIQUE (provider_id, external_id)
);


CREATE TABLE IF NOT EXISTS proveedores.firebird_purchase_order_items (
    id             BIGSERIAL PRIMARY KEY,
    purchase_order_id BIGINT NOT NULL REFERENCES proveedores.firebird_purchase_orders(id) ON DELETE CASCADE,
    line_number    INTEGER,
    product_code   TEXT,
    sku            TEXT,
    quantity       NUMERIC(14,4),
    unit_cost      NUMERIC(16,6),
    total_cost     NUMERIC(16,6),
    payload        JSONB
);


-- Inventario proveniente de Firebird
CREATE TABLE IF NOT EXISTS proveedores.firebird_inventory_snapshots (
    id            BIGSERIAL PRIMARY KEY,
    provider_id   BIGINT NOT NULL REFERENCES proveedores.providers(id) ON DELETE CASCADE,
    snapshot_date DATE NOT NULL,
    warehouse     TEXT,
    product_code  TEXT,
    sku           TEXT,
    on_hand       NUMERIC(14,4),
    on_order      NUMERIC(14,4),
    payload       JSONB,
    imported_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (provider_id, snapshot_date, warehouse, product_code)
);


-- Tickets (cabecera) provenientes de Firebird
CREATE TABLE IF NOT EXISTS proveedores.firebird_ticket_headers (
    id            BIGSERIAL PRIMARY KEY,
    provider_id   BIGINT NOT NULL REFERENCES proveedores.providers(id) ON DELETE CASCADE,
    ticket_id     BIGINT NOT NULL,
    series        TEXT,
    folio         TEXT,
    ticket_date   TIMESTAMPTZ,
    store_code    TEXT,
    customer_code TEXT,
    total_amount  NUMERIC(16,2),
    payload       JSONB,
    imported_at   TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE (provider_id, ticket_id)
);


CREATE TABLE IF NOT EXISTS proveedores.firebird_ticket_items (
    id           BIGSERIAL PRIMARY KEY,
    ticket_id    BIGINT NOT NULL,
    provider_id  BIGINT NOT NULL REFERENCES proveedores.providers(id) ON DELETE CASCADE,
    line_number  INTEGER,
    product_code TEXT,
    sku          TEXT,
    quantity     NUMERIC(14,4),
    unit_price   NUMERIC(16,6),
    unit_cost    NUMERIC(16,6),
    payload      JSONB,
    UNIQUE (provider_id, ticket_id, line_number)
);


-- ---------------------------------------------------------------------------
-- Helpers y vistas para licenciamiento y scoping de datos
-- ---------------------------------------------------------------------------

CREATE OR REPLACE VIEW proveedores.vw_provider_membership_status AS
SELECT
    pm.provider_id,
    pm.year,
    pm.plan,
    pm.status,
    pm.payment_due_date,
    pm.grace_expires_at,
    pm.payment_date,
    pm.activated_at,
    pm.expires_at,
    pm.auto_renew,
    CASE
        WHEN pm.status IN ('active', 'grace')
             AND (pm.expires_at IS NULL OR pm.expires_at >= NOW())
             THEN pm.plan
        ELSE 'free'
    END AS effective_plan
FROM proveedores.provider_memberships pm;


CREATE OR REPLACE VIEW proveedores.vw_provider_is_pro AS
SELECT
    v.provider_id,
    (v.effective_plan IN ('pro', 'enterprise')) AS is_pro,
    (v.effective_plan = 'enterprise') AS is_enterprise
FROM proveedores.vw_provider_membership_status v
WHERE v.year = EXTRACT(YEAR FROM CURRENT_DATE);


CREATE OR REPLACE VIEW proveedores.vw_provider_module_access AS
SELECT
    pm.provider_id,
    m.code        AS module_code,
    m.tier,
    m.metadata,
    pm.is_enabled,
    pm.enabled_at,
    COALESCE(unlock.expires_at >= NOW(), unlock.id IS NOT NULL) AS has_unlock,
    unlock.expires_at AS unlock_expires_at
FROM proveedores.provider_modules pm
JOIN proveedores.modules m ON m.id = pm.module_id
LEFT JOIN LATERAL (
    SELECT pu.id, pu.expires_at
    FROM proveedores.provider_unlocks pu
    WHERE pu.provider_id = pm.provider_id
      AND pu.module_id = pm.module_id
    ORDER BY pu.expires_at DESC NULLS LAST
    LIMIT 1
) unlock ON TRUE;


CREATE OR REPLACE VIEW proveedores.vw_provider_module_effective AS
SELECT
    a.provider_id,
    a.module_code,
    a.tier,
    a.metadata,
    a.is_enabled,
    a.enabled_at,
    COALESCE(a.has_unlock, FALSE) AS has_unlock,
    a.unlock_expires_at,
    CASE
        WHEN a.tier = 'free' THEN a.is_enabled
        WHEN a.tier = 'pro' THEN a.is_enabled AND COALESCE(p.is_pro, FALSE)
        WHEN a.tier = 'enterprise' THEN a.is_enabled AND COALESCE(p.is_enterprise, FALSE)
        ELSE a.is_enabled
    END AS can_access
FROM proveedores.vw_provider_module_access a
LEFT JOIN proveedores.vw_provider_is_pro p
  ON p.provider_id = a.provider_id;


-- ---------------------------------------------------------------------------
-- Vistas de analytics (ajustar tablas reales en public.*)
-- ---------------------------------------------------------------------------

CREATE OR REPLACE VIEW proveedores.vw_sellout_daily AS
SELECT
    pr.id AS provider_id,
    v.fecha::date AS day,
    SUM(COALESCE(v.venta_bruta, 0) - COALESCE(v.descuento, 0) - COALESCE(v.devolucion, 0)) AS net_sales,
    SUM(COALESCE(v.venta_costo_promedio, 0) - COALESCE(v.devolucion_costo_promedio, 0)) AS cost_of_goods,
    SUM(COALESCE(v.venta_bruta_pza, 0) - COALESCE(v.devolucion_pza, 0)) AS units
FROM public.ventas v
JOIN proveedores.providers pr ON pr.external_id = v.id_proveedor
WHERE v.fecha >= CURRENT_DATE - INTERVAL '5 years'
GROUP BY pr.id, v.fecha::date;


CREATE OR REPLACE VIEW proveedores.vw_sellin_monthly AS
SELECT
    pr.id AS provider_id,
    date_trunc('month', m.fecha)::date AS month,
    SUM(GREATEST(COALESCE(m.entradas, 0), 0) * COALESCE(m.costo, 0)) AS net_purchases,
    SUM(GREATEST(COALESCE(m.entradas, 0), 0)) AS units
FROM public.movimientos m
JOIN proveedores.providers pr ON pr.external_id = m.id_proveedor
WHERE m.fecha >= CURRENT_DATE - INTERVAL '5 years'
GROUP BY pr.id, date_trunc('month', m.fecha);


CREATE OR REPLACE VIEW proveedores.vw_inventory_cover AS
SELECT
    pr.id AS provider_id,
    m.id_tienda AS store_id,
    m.id_articulo AS product_id,
    SUM(COALESCE(m.entradas, 0) - COALESCE(m.salidas, 0)) AS on_hand,
    SUM(GREATEST(COALESCE(m.entradas, 0), 0)) AS on_order,
    NULL::numeric AS days_of_inventory
FROM public.movimientos m
JOIN proveedores.providers pr ON pr.external_id = m.id_proveedor
WHERE m.fecha >= CURRENT_DATE - INTERVAL '180 days'
GROUP BY pr.id, m.id_tienda, m.id_articulo;


CREATE OR REPLACE VIEW proveedores.vw_turnover_monthly AS
SELECT
    so.provider_id,
    so.month,
    CASE
        WHEN COALESCE(si.net_purchases, 0) = 0 THEN NULL
        ELSE so.net_sales / si.net_purchases
    END AS turnover
FROM (
    SELECT
        pr.id AS provider_id,
        date_trunc('month', v.fecha)::date AS month,
        SUM(COALESCE(v.venta_bruta, 0) - COALESCE(v.descuento, 0) - COALESCE(v.devolucion, 0)) AS net_sales
    FROM public.ventas v
    JOIN proveedores.providers pr ON pr.external_id = v.id_proveedor
    WHERE v.fecha >= CURRENT_DATE - INTERVAL '5 years'
    GROUP BY pr.id, date_trunc('month', v.fecha)
) so
LEFT JOIN (
    SELECT
        pr.id AS provider_id,
        date_trunc('month', m.fecha)::date AS month,
        SUM(GREATEST(COALESCE(m.entradas, 0), 0) * COALESCE(m.costo, 0)) AS net_purchases
    FROM public.movimientos m
    JOIN proveedores.providers pr ON pr.external_id = m.id_proveedor
    WHERE m.fecha >= CURRENT_DATE - INTERVAL '5 years'
    GROUP BY pr.id, date_trunc('month', m.fecha)
) si ON si.provider_id = so.provider_id AND si.month = so.month;


CREATE OR REPLACE VIEW proveedores.vw_top_products AS
SELECT
    pr.id AS provider_id,
    v.id_articulo AS product_id,
    SUM(COALESCE(v.venta_bruta, 0) - COALESCE(v.descuento, 0) - COALESCE(v.devolucion, 0)) AS net_sales,
    SUM(COALESCE(v.venta_bruta_pza, 0) - COALESCE(v.devolucion_pza, 0)) AS units
FROM public.ventas v
JOIN proveedores.providers pr ON pr.external_id = v.id_proveedor
WHERE v.fecha >= CURRENT_DATE - INTERVAL '5 years'
GROUP BY pr.id, v.id_articulo;


CREATE OR REPLACE VIEW proveedores.vw_top_customers AS
SELECT
    pr.id AS provider_id,
    v.id_cliente AS customer_id,
    SUM(COALESCE(v.venta_bruta, 0) - COALESCE(v.descuento, 0) - COALESCE(v.devolucion, 0)) AS net_sales,
    COUNT(*) AS ticket_count
FROM public.ventas v
JOIN proveedores.providers pr ON pr.external_id = v.id_proveedor
WHERE v.fecha >= CURRENT_DATE - INTERVAL '5 years'
GROUP BY pr.id, v.id_cliente;


CREATE OR REPLACE VIEW proveedores.vw_tickets AS
WITH base AS (
    SELECT
        pr.id AS provider_id,
        v.fecha::timestamp AS ticket_date,
        v.id_tienda AS store_id,
        v.id_cliente AS customer_id,
        SUM(COALESCE(v.venta_bruta, 0) - COALESCE(v.descuento, 0) - COALESCE(v.devolucion, 0)) AS net_sales
    FROM public.ventas v
    JOIN proveedores.providers pr ON pr.external_id = v.id_proveedor
    WHERE v.fecha >= CURRENT_DATE - INTERVAL '5 years'
    GROUP BY pr.id, v.fecha, v.id_tienda, v.id_cliente
)
SELECT
    base.provider_id,
    ROW_NUMBER() OVER (PARTITION BY base.provider_id ORDER BY base.ticket_date, base.store_id, base.customer_id) AS ticket_id,
    base.ticket_date,
    base.store_id,
    base.customer_id,
    NULL::text AS series,
    NULL::text AS folio,
    base.net_sales
FROM base;


CREATE OR REPLACE VIEW proveedores.vw_ticket_items AS
WITH base AS (
    SELECT
        pr.id AS provider_id,
        v.fecha::timestamp AS ticket_date,
        v.id_tienda AS store_id,
        v.id_cliente AS customer_id,
        v.id_articulo AS product_id,
        SUM(COALESCE(v.venta_bruta_pza, 0) - COALESCE(v.devolucion_pza, 0)) AS qty,
        SUM(COALESCE(v.venta_bruta, 0) - COALESCE(v.descuento, 0) - COALESCE(v.devolucion, 0)) AS total,
        SUM(COALESCE(v.venta_costo_promedio, 0)) AS cost
    FROM public.ventas v
    JOIN proveedores.providers pr ON pr.external_id = v.id_proveedor
    WHERE v.fecha >= CURRENT_DATE - INTERVAL '5 years'
    GROUP BY pr.id, v.fecha, v.id_tienda, v.id_cliente, v.id_articulo
)
SELECT
    base.provider_id,
    t.ticket_id,
    base.product_id,
    art.sku,
    art.descripcion AS name,
    base.qty,
    CASE WHEN base.qty = 0 THEN 0 ELSE base.total / base.qty END AS price,
    base.total,
    base.cost
FROM base
JOIN proveedores.vw_tickets t
  ON t.provider_id = base.provider_id
 AND t.ticket_date = base.ticket_date
 AND t.store_id = base.store_id
 AND t.customer_id = base.customer_id
LEFT JOIN public.articulo art ON art.id_articulo = base.product_id;


-- ---------------------------------------------------------------------------
-- Funciones utilitarias
-- ---------------------------------------------------------------------------

CREATE OR REPLACE FUNCTION proveedores.fn_current_membership_plan(p_provider_id BIGINT)
RETURNS TEXT
LANGUAGE plpgsql
AS $$
DECLARE
    plan TEXT;
BEGIN
    SELECT effective_plan
      INTO plan
    FROM proveedores.vw_provider_membership_status
    WHERE provider_id = p_provider_id
      AND year = EXTRACT(YEAR FROM CURRENT_DATE)
    ORDER BY expires_at DESC NULLS LAST
    LIMIT 1;

    IF plan IS NULL THEN
        RETURN 'free';
    END IF;
    RETURN plan;
END;
$$;


CREATE OR REPLACE FUNCTION proveedores.fn_can_access_module(p_provider_id BIGINT, p_module_code TEXT)
RETURNS BOOLEAN
LANGUAGE plpgsql
AS $$
DECLARE
    access BOOLEAN;
BEGIN
    SELECT can_access
      INTO access
    FROM proveedores.vw_provider_module_effective
    WHERE provider_id = p_provider_id
      AND module_code = p_module_code
    LIMIT 1;

    RETURN COALESCE(access, FALSE);
END;
$$;


-- Índices recomendados
CREATE INDEX IF NOT EXISTS idx_ticket_reviews_provider ON proveedores.ticket_reviews(provider_id);
CREATE INDEX IF NOT EXISTS idx_ticket_reviews_ticket ON proveedores.ticket_reviews(ticket_id);
CREATE INDEX IF NOT EXISTS idx_ticket_points_provider ON proveedores.ticket_points(provider_id);
CREATE INDEX IF NOT EXISTS idx_provider_module_effective ON proveedores.provider_modules(provider_id, module_id);
CREATE INDEX IF NOT EXISTS idx_provider_quotas_provider ON proveedores.provider_quotas(provider_id, module_id);
CREATE INDEX IF NOT EXISTS idx_firebird_purchase_orders_provider ON proveedores.firebird_purchase_orders(provider_id);
CREATE INDEX IF NOT EXISTS idx_firebird_inventory_provider ON proveedores.firebird_inventory_snapshots(provider_id, snapshot_date);
CREATE INDEX IF NOT EXISTS idx_firebird_tickets_provider ON proveedores.firebird_ticket_headers(provider_id, ticket_date);


-- ---------------------------------------------------------------------------
-- Materialized views sugeridas (se crean vacías)
-- ---------------------------------------------------------------------------

CREATE MATERIALIZED VIEW IF NOT EXISTS proveedores.mv_sellout_daily
AS SELECT * FROM proveedores.vw_sellout_daily WITH NO DATA;

CREATE MATERIALIZED VIEW IF NOT EXISTS proveedores.mv_sellin_monthly
AS SELECT * FROM proveedores.vw_sellin_monthly WITH NO DATA;

CREATE MATERIALIZED VIEW IF NOT EXISTS proveedores.mv_inventory_cover
AS SELECT * FROM proveedores.vw_inventory_cover WITH NO DATA;

CREATE MATERIALIZED VIEW IF NOT EXISTS proveedores.mv_turnover_monthly
AS SELECT * FROM proveedores.vw_turnover_monthly WITH NO DATA;

CREATE MATERIALIZED VIEW IF NOT EXISTS proveedores.mv_top_products
AS SELECT * FROM proveedores.vw_top_products WITH NO DATA;

CREATE MATERIALIZED VIEW IF NOT EXISTS proveedores.mv_top_customers
AS SELECT * FROM proveedores.vw_top_customers WITH NO DATA;


-- Advertencia: ejecutar REFRESH MATERIALIZED VIEW (CONCURRENTLY cuando aplique)
