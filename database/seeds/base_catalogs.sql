SET search_path TO proveedores, public;

-- Roles base
INSERT INTO proveedores.roles (code, name, description) VALUES
    ('super_admin', 'Super Admin', 'Acceso total a la plataforma'),
    ('provider_admin', 'Admin de proveedor', 'Gestiona su proveedor y subusuarios'),
    ('provider_user', 'Usuario de proveedor', 'Acceso controlado a módulos habilitados')
ON CONFLICT (code) DO UPDATE SET
    name = EXCLUDED.name,
    description = EXCLUDED.description;


-- Catálogo de módulos con tiers
INSERT INTO proveedores.modules (code, name, category, description, tier, sort_order, is_active, metadata) VALUES
    ('dashboard', 'Dashboard General', 'core', 'KPIs globales y alertas', 'free', 10, TRUE, '{}'::jsonb),
    ('kpis', 'KPIs Ejecutivos', 'analytics', 'Tarjetas ejecutivas, semaforos y comparativos rapidos', 'free', 15, TRUE, '{}'::jsonb),
    ('sales', 'Ventas (Sell-out)', 'analytics', 'KPIs de ventas, tickets y top productos', 'free', 20, TRUE, '{}'::jsonb),
    ('sales_periods', 'Ventas por Periodo', 'analytics', 'Detalle histórico de ventas por periodo', 'free', 25, TRUE, '{}'::jsonb),
    ('sales_sellout', 'Sell-out Detalle', 'analytics', 'Sell-out granular y tickets asociados', 'free', 30, TRUE, '{}'::jsonb),
    ('purchases', 'Compras (Sell-in)', 'analytics', 'KPIs de compras vs recepcion', 'pro', 40, TRUE, '{}'::jsonb),
    ('purchases_periods', 'Compras por Periodo', 'analytics', 'Detalle histórico de compras', 'pro', 45, TRUE, '{}'::jsonb),
    ('purchases_sellin', 'Sell-in Detalle', 'analytics', 'Análisis granular de sell-in', 'pro', 50, TRUE, '{}'::jsonb),
    ('inventory', 'Inventarios', 'analytics', 'Existencias, cobertura y quiebres', 'free', 60, TRUE, '{}'::jsonb),
    ('rotations', 'Rotaciones', 'analytics', 'Turnover, días de inventario y rotación', 'pro', 70, TRUE, '{}'::jsonb),
    ('tickets', 'Tickets', 'operations', 'Buscador, descarga y revision de tickets', 'free', 80, TRUE, '{}'::jsonb),
    ('others', 'Otros modulos', 'operations', 'Accesos a funciones complementarias', 'free', 90, TRUE, '{}'::jsonb),
    ('orders', 'Ordenes', 'operations', 'Ordenes de compra y seguimiento', 'free', 100, TRUE, '{}'::jsonb),
    ('orders_new', 'Ordenes nuevas', 'operations', 'Descarga de ordenes recien emitidas', 'free', 110, TRUE, '{}'::jsonb),
    ('orders_backorders', 'Backorders', 'operations', 'Seguimiento de backorders y retrasos', 'free', 120, TRUE, '{}'::jsonb),
    ('orders_entries', 'Ordenes con entradas', 'operations', 'Ordenes recepcionadas/entradas', 'free', 130, TRUE, '{}'::jsonb),
    ('appointments', 'Citas de proveedor', 'operations', 'Programacion y seguimiento de citas de entrega', 'free', 135, TRUE, '{}'::jsonb),
    ('exports', 'Exportaciones', 'operations', 'Descarga CSV/XLSX y programacion de reportes', 'pro', 140, TRUE, '{}'::jsonb),
    ('providers', 'Directorio de proveedores', 'operations', 'Datos maestros y contactos', 'free', 150, TRUE, '{}'::jsonb),
    ('users_admin', 'Usuarios', 'administration', 'Gestion de usuarios, roles y accesos', 'free', 160, TRUE, '{}'::jsonb),
    ('notifications', 'Alertas', 'core', 'Centro de notificaciones y recordatorios', 'free', 170, TRUE, '{}'::jsonb),
    ('api', 'API REST', 'integrations', 'Tokens y consumo API externo', 'pro', 180, TRUE, '{}'::jsonb),
    ('analytics', 'Analiticos avanzados', 'analytics', 'Comparativos YoY, tendencias y drill-down', 'pro', 190, TRUE, '{}'::jsonb)
ON CONFLICT (code) DO UPDATE SET
    name = EXCLUDED.name,
    category = EXCLUDED.category,
    description = EXCLUDED.description,
    tier = EXCLUDED.tier,
    sort_order = EXCLUDED.sort_order,
    is_active = EXCLUDED.is_active,
    metadata = EXCLUDED.metadata;


-- Menús principales
WITH module_ids AS (
    SELECT code, id FROM proveedores.modules
)
INSERT INTO proveedores.menus (module_id, label, route_name, icon, sort_order, visibility, parent_id) VALUES
    ((SELECT id FROM module_ids WHERE code = 'dashboard'), 'Dashboard', 'dashboard.index', 'ph-gauge', 10, 'all', NULL),
    ((SELECT id FROM module_ids WHERE code = 'kpis'), 'KPIs', 'kpis.index', 'ph-chart-donut', 15, 'all', NULL),
    ((SELECT id FROM module_ids WHERE code = 'sales'), 'Ventas', 'sales.index', 'fa-solid fa-money-bill-trend-up', 20, 'all', NULL),
    ((SELECT id FROM module_ids WHERE code = 'purchases'), 'Compras', 'purchases.index', 'fa-solid fa-boxes-stacked', 30, 'all', NULL),
    ((SELECT id FROM module_ids WHERE code = 'inventory'), 'Inventarios', 'inventory.index', 'fa-solid fa-warehouse', 40, 'all', NULL),
    ((SELECT id FROM module_ids WHERE code = 'rotations'), 'Rotaciones', 'rotations.index', 'fa-solid fa-rotate', 45, 'admin', NULL),
    ((SELECT id FROM module_ids WHERE code = 'tickets'), 'Tickets', 'tickets.index', 'fa-solid fa-ticket', 50, 'all', NULL),
    ((SELECT id FROM module_ids WHERE code = 'others'), 'Otros', 'others.index', 'fa-solid fa-dice-d20', 55, 'all', NULL),
    ((SELECT id FROM module_ids WHERE code = 'orders'), 'Ordenes', 'orders.index', 'fa-solid fa-receipt', 60, 'all', NULL),
    ((SELECT id FROM module_ids WHERE code = 'appointments'), 'Citas', 'appointments.index', 'fa-solid fa-calendar-check', 65, 'all', NULL),
    ((SELECT id FROM module_ids WHERE code = 'exports'), 'Exportaciones', 'exports.index', 'fa-solid fa-file-export', 70, 'admin', NULL),
    ((SELECT id FROM module_ids WHERE code = 'providers'), 'Configuracion', 'providers.index', 'fa-solid fa-building-user', 80, 'admin', NULL),
    ((SELECT id FROM module_ids WHERE code = 'users_admin'), 'Usuarios', 'users.index', 'fa-solid fa-users-gear', 90, 'admin', NULL),
    ((SELECT id FROM module_ids WHERE code = 'notifications'), 'Alertas', 'notifications.index', 'ph-bell', 95, 'all', NULL),
    ((SELECT id FROM module_ids WHERE code = 'api'), 'API Tokens', 'api.tokens', 'ph-plug', 100, 'admin', NULL)
ON CONFLICT (route_name) DO UPDATE SET
    module_id = EXCLUDED.module_id,
    label = EXCLUDED.label,
    icon = EXCLUDED.icon,
    sort_order = EXCLUDED.sort_order,
    visibility = EXCLUDED.visibility,
    parent_id = EXCLUDED.parent_id;


-- Menús secundarios
WITH module_ids AS (
    SELECT code, id FROM proveedores.modules
)
INSERT INTO proveedores.menus (module_id, label, route_name, icon, sort_order, visibility, parent_id) VALUES
    ((SELECT id FROM module_ids WHERE code = 'sales_periods'), 'Por periodos', 'sales.periods', NULL, 21, 'all', (SELECT id FROM proveedores.menus WHERE route_name = 'sales.index')),
    ((SELECT id FROM module_ids WHERE code = 'sales_sellout'), 'Sell-out', 'sales.sellout', NULL, 22, 'all', (SELECT id FROM proveedores.menus WHERE route_name = 'sales.index')),
    ((SELECT id FROM module_ids WHERE code = 'analytics'), 'Comparativos', 'sales.compare', NULL, 23, 'admin', (SELECT id FROM proveedores.menus WHERE route_name = 'sales.index')),

    ((SELECT id FROM module_ids WHERE code = 'purchases_periods'), 'Por periodos', 'purchases.periods', NULL, 31, 'admin', (SELECT id FROM proveedores.menus WHERE route_name = 'purchases.index')),
    ((SELECT id FROM module_ids WHERE code = 'purchases_sellin'), 'Sell-in', 'purchases.sellin', NULL, 32, 'admin', (SELECT id FROM proveedores.menus WHERE route_name = 'purchases.index')),

    ((SELECT id FROM module_ids WHERE code = 'orders_new'), 'Ordenes nuevas', 'orders.news', NULL, 61, 'all', (SELECT id FROM proveedores.menus WHERE route_name = 'orders.index')),
    ((SELECT id FROM module_ids WHERE code = 'orders_backorders'), 'Backorders', 'orders.backorders', NULL, 62, 'all', (SELECT id FROM proveedores.menus WHERE route_name = 'orders.index')),
    ((SELECT id FROM module_ids WHERE code = 'orders_entries'), 'Ordenes con entradas', 'orders.entries', NULL, 63, 'all', (SELECT id FROM proveedores.menus WHERE route_name = 'orders.index')),

    ((SELECT id FROM module_ids WHERE code = 'tickets'), 'Buscador', 'tickets.search', NULL, 51, 'all', (SELECT id FROM proveedores.menus WHERE route_name = 'tickets.index')),
    ((SELECT id FROM module_ids WHERE code = 'tickets'), 'Concursos', 'tickets.points', NULL, 52, 'admin', (SELECT id FROM proveedores.menus WHERE route_name = 'tickets.index')),

    ((SELECT id FROM module_ids WHERE code = 'inventory'), 'Cobertura', 'inventory.cover', NULL, 41, 'all', (SELECT id FROM proveedores.menus WHERE route_name = 'inventory.index')),
    ((SELECT id FROM module_ids WHERE code = 'inventory'), 'Quiebres', 'inventory.breaks', NULL, 42, 'all', (SELECT id FROM proveedores.menus WHERE route_name = 'inventory.index')),

    ((SELECT id FROM module_ids WHERE code = 'others'), 'Devoluciones', 'others.devoluciones', NULL, 56, 'admin', (SELECT id FROM proveedores.menus WHERE route_name = 'others.index')),
    ((SELECT id FROM module_ids WHERE code = 'others'), 'Inventario legacy', 'others.inventario', NULL, 57, 'admin', (SELECT id FROM proveedores.menus WHERE route_name = 'others.index')),

    ((SELECT id FROM module_ids WHERE code = 'rotations'), 'Turnover', 'rotations.turnover', NULL, 46, 'admin', (SELECT id FROM proveedores.menus WHERE route_name = 'rotations.index')),

    ((SELECT id FROM module_ids WHERE code = 'exports'), 'Historial de exportes', 'exports.history', NULL, 71, 'admin', (SELECT id FROM proveedores.menus WHERE route_name = 'exports.index')),
    ((SELECT id FROM module_ids WHERE code = 'providers'), 'Datos maestro', 'providers.settings', NULL, 81, 'admin', (SELECT id FROM proveedores.menus WHERE route_name = 'providers.index')),
    ((SELECT id FROM module_ids WHERE code = 'users_admin'), 'Administrar', 'users.manage', NULL, 91, 'admin', (SELECT id FROM proveedores.menus WHERE route_name = 'users.index'))
ON CONFLICT (route_name) DO UPDATE SET
    module_id = EXCLUDED.module_id,
    label = EXCLUDED.label,
    icon = EXCLUDED.icon,
    sort_order = EXCLUDED.sort_order,
    visibility = EXCLUDED.visibility,
    parent_id = EXCLUDED.parent_id;


-- Menús por rol
WITH menu_ids AS (
    SELECT route_name, id FROM proveedores.menus
), role_ids AS (
    SELECT code, id FROM proveedores.roles
)
INSERT INTO proveedores.menu_roles (menu_id, role_id) VALUES
    ((SELECT id FROM menu_ids WHERE route_name = 'dashboard.index'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'dashboard.index'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'dashboard.index'), (SELECT id FROM role_ids WHERE code = 'provider_user')),

    ((SELECT id FROM menu_ids WHERE route_name = 'kpis.index'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'kpis.index'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'kpis.index'), (SELECT id FROM role_ids WHERE code = 'provider_user')),

    ((SELECT id FROM menu_ids WHERE route_name = 'sales.index'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'sales.index'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'sales.index'), (SELECT id FROM role_ids WHERE code = 'provider_user')),

    ((SELECT id FROM menu_ids WHERE route_name = 'sales.periods'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'sales.periods'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'sales.periods'), (SELECT id FROM role_ids WHERE code = 'provider_user')),

    ((SELECT id FROM menu_ids WHERE route_name = 'sales.sellout'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'sales.sellout'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'sales.sellout'), (SELECT id FROM role_ids WHERE code = 'provider_user')),

    ((SELECT id FROM menu_ids WHERE route_name = 'sales.compare'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'sales.compare'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),

    ((SELECT id FROM menu_ids WHERE route_name = 'purchases.index'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'purchases.index'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'purchases.index'), (SELECT id FROM role_ids WHERE code = 'provider_user')),

    ((SELECT id FROM menu_ids WHERE route_name = 'purchases.periods'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'purchases.periods'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),

    ((SELECT id FROM menu_ids WHERE route_name = 'purchases.sellin'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'purchases.sellin'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),

    ((SELECT id FROM menu_ids WHERE route_name = 'others.index'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'others.index'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'others.index'), (SELECT id FROM role_ids WHERE code = 'provider_user')),

    ((SELECT id FROM menu_ids WHERE route_name = 'others.devoluciones'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'others.devoluciones'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),

    ((SELECT id FROM menu_ids WHERE route_name = 'others.inventario'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'others.inventario'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),

    ((SELECT id FROM menu_ids WHERE route_name = 'inventory.index'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'inventory.index'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'inventory.index'), (SELECT id FROM role_ids WHERE code = 'provider_user')),

    ((SELECT id FROM menu_ids WHERE route_name = 'inventory.cover'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'inventory.cover'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'inventory.cover'), (SELECT id FROM role_ids WHERE code = 'provider_user')),

    ((SELECT id FROM menu_ids WHERE route_name = 'inventory.breaks'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'inventory.breaks'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),

    ((SELECT id FROM menu_ids WHERE route_name = 'rotations.index'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'rotations.index'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),

    ((SELECT id FROM menu_ids WHERE route_name = 'rotations.turnover'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'rotations.turnover'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),

    ((SELECT id FROM menu_ids WHERE route_name = 'tickets.index'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'tickets.index'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'tickets.index'), (SELECT id FROM role_ids WHERE code = 'provider_user')),

    ((SELECT id FROM menu_ids WHERE route_name = 'tickets.search'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'tickets.search'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'tickets.search'), (SELECT id FROM role_ids WHERE code = 'provider_user')),

    ((SELECT id FROM menu_ids WHERE route_name = 'tickets.points'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'tickets.points'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),

    ((SELECT id FROM menu_ids WHERE route_name = 'orders.index'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'orders.index'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'orders.index'), (SELECT id FROM role_ids WHERE code = 'provider_user')),

    ((SELECT id FROM menu_ids WHERE route_name = 'orders.news'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'orders.news'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),

    ((SELECT id FROM menu_ids WHERE route_name = 'orders.backorders'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'orders.backorders'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),

    ((SELECT id FROM menu_ids WHERE route_name = 'orders.entries'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'orders.entries'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),

    ((SELECT id FROM menu_ids WHERE route_name = 'exports.index'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'exports.index'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),

    ((SELECT id FROM menu_ids WHERE route_name = 'exports.history'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'exports.history'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),

    ((SELECT id FROM menu_ids WHERE route_name = 'providers.index'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'providers.index'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),

    ((SELECT id FROM menu_ids WHERE route_name = 'providers.settings'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'providers.settings'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),

    ((SELECT id FROM menu_ids WHERE route_name = 'users.index'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'users.index'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),

    ((SELECT id FROM menu_ids WHERE route_name = 'users.manage'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'users.manage'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),

    ((SELECT id FROM menu_ids WHERE route_name = 'notifications.index'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'notifications.index'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'notifications.index'), (SELECT id FROM role_ids WHERE code = 'provider_user')),

    ((SELECT id FROM menu_ids WHERE route_name = 'api.tokens'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'api.tokens'), (SELECT id FROM role_ids WHERE code = 'provider_admin'))
ON CONFLICT DO NOTHING;
