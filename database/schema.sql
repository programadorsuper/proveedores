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

DROP INDEX IF EXISTS proveedores.providers_external_id_unique;

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
