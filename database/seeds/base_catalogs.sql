SET search_path TO proveedores, public;

-- Roles base
INSERT INTO proveedores.roles (code, name, description) VALUES
    ('super_admin', 'Super Admin', 'Acceso total a toda la plataforma'),
    ('provider_admin', 'Administrador de proveedor', 'Gestiona su proveedor y sus usuarios'),
    ('provider_user', 'Usuario de proveedor', 'Acceso a módulos habilitados')
ON CONFLICT (code) DO UPDATE SET
    name = EXCLUDED.name,
    description = EXCLUDED.description;


-- Módulos disponibles
INSERT INTO proveedores.modules (code, name, category, description, sort_order) VALUES
    ('dashboard', 'Dashboard Inteligente', 'core', 'Resumen general y alertas en tiempo real', 10),
    ('sellinout', 'Sell In/Out', 'analitica', 'Indicadores principales de Sell In y Sell Out', 20),
    ('sales', 'Ventas', 'operaciones', 'Panel principal de ventas', 30),
    ('providers', 'Proveedores', 'operaciones', 'Directorio de proveedores relacionados', 35),
    ('sales_periods', 'Ventas por periodo', 'operaciones', 'Consulta histórica de ventas por periodo', 31),
    ('sales_sellout', 'Sell Out', 'operaciones', 'Detalle de ventas Sell Out', 32),
    ('orders', 'Órdenes', 'operaciones', 'Panel general de órdenes de compra', 40),
    ('orders_new', 'Órdenes nuevas', 'operaciones', 'Descarga de órdenes nuevas', 41),
    ('orders_backorders', 'Backorders', 'operaciones', 'Seguimiento de backorders', 42),
    ('orders_entries', 'Órdenes con entradas', 'operaciones', 'Órdenes de compra con entradas registradas', 43),
    ('others', 'Otros módulos', 'operaciones', 'Accesos adicionales (devoluciones, inventario, etc.)', 49),
    ('returns', 'Devoluciones', 'operaciones', 'Gestión de devoluciones y notas de crédito', 50),
    ('inventory', 'Inventario', 'operaciones', 'Existencias e inventarios disponibles', 51),
    ('users_admin', 'Usuarios', 'administracion', 'Administración de usuarios y permisos', 60),
    ('purchases', 'Compras', 'operaciones', 'Panel principal de compras', 70),
    ('purchases_periods', 'Compras por periodo', 'operaciones', 'Consulta histórica de compras', 71),
    ('purchases_sellin', 'Sell In', 'operaciones', 'Detalle de compras Sell In', 72),
    ('notifications', 'Avisos y alertas', 'core', 'Centro de notificaciones y recordatorios', 90),
    ('api', 'API REST', 'integraciones', 'End-points públicos con tokens por usuario', 100)
ON CONFLICT (code) DO UPDATE SET
    name = EXCLUDED.name,
    category = EXCLUDED.category,
    description = EXCLUDED.description,
    sort_order = EXCLUDED.sort_order;


- Menús principales
WITH module_ids AS (
    SELECT code, id FROM proveedores.modules
)
INSERT INTO proveedores.menus (module_id, label, route_name, icon, sort_order, visibility, parent_id) VALUES
    ((SELECT id FROM module_ids WHERE code = 'dashboard'), 'Dashboard', 'dashboard.index', 'ph-gauge', 10, 'all', NULL),
    ((SELECT id FROM module_ids WHERE code = 'sellinout'), 'Sell In / Out', 'sellinout.index', 'fa-solid fa-chart-pie-simple-circle-dollar', 20, 'admin', NULL),
    ((SELECT id FROM module_ids WHERE code = 'sales'), 'Ventas', 'sales.index', 'fa-solid fa-money-bill-trend-up', 30, 'all', NULL),
    ((SELECT id FROM module_ids WHERE code = 'providers'), 'Proveedores', 'providers.index', 'fa-solid fa-building-user', 35, 'admin', NULL),
    ((SELECT id FROM module_ids WHERE code = 'orders'), 'Órdenes', 'orders.index', 'fa-solid fa-memo-circle-check', 40, 'all', NULL),
    ((SELECT id FROM module_ids WHERE code = 'purchases'), 'Compras', 'purchases.index', 'fa-solid fa-bag-shopping', 50, 'all', NULL),
    ((SELECT id FROM module_ids WHERE code = 'others'), 'Otros', 'others.index', 'fa-solid fa-dice-d20', 60, 'all', NULL),
    ((SELECT id FROM module_ids WHERE code = 'users_admin'), 'Usuarios', 'users.index', 'fa-solid fa-users', 70, 'admin', NULL),
    ((SELECT id FROM module_ids WHERE code = 'notifications'), 'Alertas', 'notifications.index', 'ph-bell', 80, 'all', NULL),
    ((SELECT id FROM module_ids WHERE code = 'api'), 'API Tokens', 'api.tokens', 'ph-plug', 90, 'admin', NULL)
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
    ((SELECT id FROM module_ids WHERE code = 'orders_new'), 'Órdenes nuevas', 'orders.news', NULL, 41, 'all', (SELECT id FROM proveedores.menus WHERE route_name = 'orders.index')),
    ((SELECT id FROM module_ids WHERE code = 'orders_backorders'), 'Backorders', 'orders.backorders', NULL, 42, 'all', (SELECT id FROM proveedores.menus WHERE route_name = 'orders.index')),
    ((SELECT id FROM module_ids WHERE code = 'orders_entries'), 'Órdenes con entradas', 'orders.entries', NULL, 43, 'all', (SELECT id FROM proveedores.menus WHERE route_name = 'orders.index')),

    ((SELECT id FROM module_ids WHERE code = 'sales_periods'), 'Periodos', 'sales.periods', NULL, 31, 'all', (SELECT id FROM proveedores.menus WHERE route_name = 'sales.index')),
    ((SELECT id FROM module_ids WHERE code = 'sales_sellout'), 'Sell-Out', 'sales.sellout', NULL, 32, 'all', (SELECT id FROM proveedores.menus WHERE route_name = 'sales.index')),

    ((SELECT id FROM module_ids WHERE code = 'purchases_periods'), 'Periodos', 'purchases.periods', NULL, 71, 'all', (SELECT id FROM proveedores.menus WHERE route_name = 'purchases.index')),
    ((SELECT id FROM module_ids WHERE code = 'purchases_sellin'), 'Sell-In', 'purchases.sellin', NULL, 72, 'all', (SELECT id FROM proveedores.menus WHERE route_name = 'purchases.index')),

    ((SELECT id FROM module_ids WHERE code = 'returns'), 'Devolución', 'others.returns', NULL, 51, 'all', (SELECT id FROM proveedores.menus WHERE route_name = 'others.index')),
    ((SELECT id FROM module_ids WHERE code = 'inventory'), 'Inventario', 'others.inventory', NULL, 52, 'all', (SELECT id FROM proveedores.menus WHERE route_name = 'others.index'))
ON CONFLICT (route_name) DO UPDATE SET
    module_id = EXCLUDED.module_id,
    label = EXCLUDED.label,
    icon = EXCLUDED.icon,
    sort_order = EXCLUDED.sort_order,
    visibility = EXCLUDED.visibility,
    parent_id = EXCLUDED.parent_id;


-- Reglas de menú por rol
WITH role_ids AS (
    SELECT code, id FROM proveedores.roles
), menu_ids AS (
    SELECT route_name, id FROM proveedores.menus
)
INSERT INTO proveedores.menu_roles (menu_id, role_id) VALUES
    ((SELECT id FROM menu_ids WHERE route_name = 'dashboard.index'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'dashboard.index'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'dashboard.index'), (SELECT id FROM role_ids WHERE code = 'provider_user')),

    ((SELECT id FROM menu_ids WHERE route_name = 'sellinout.index'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'sellinout.index'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),

    ((SELECT id FROM menu_ids WHERE route_name = 'providers.index'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'providers.index'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),

    ((SELECT id FROM menu_ids WHERE route_name = 'orders.index'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'orders.index'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'orders.index'), (SELECT id FROM role_ids WHERE code = 'provider_user')),

    ((SELECT id FROM menu_ids WHERE route_name = 'orders.news'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'orders.news'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'orders.news'), (SELECT id FROM role_ids WHERE code = 'provider_user')),

    ((SELECT id FROM menu_ids WHERE route_name = 'orders.backorders'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'orders.backorders'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'orders.backorders'), (SELECT id FROM role_ids WHERE code = 'provider_user')),

    ((SELECT id FROM menu_ids WHERE route_name = 'orders.entries'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'orders.entries'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'orders.entries'), (SELECT id FROM role_ids WHERE code = 'provider_user')),

    ((SELECT id FROM menu_ids WHERE route_name = 'purchases.index'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'purchases.index'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'purchases.index'), (SELECT id FROM role_ids WHERE code = 'provider_user')),

    ((SELECT id FROM menu_ids WHERE route_name = 'purchases.periods'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'purchases.periods'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'purchases.periods'), (SELECT id FROM role_ids WHERE code = 'provider_user')),

    ((SELECT id FROM menu_ids WHERE route_name = 'purchases.sellin'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'purchases.sellin'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'purchases.sellin'), (SELECT id FROM role_ids WHERE code = 'provider_user')),

    ((SELECT id FROM menu_ids WHERE route_name = 'sales.index'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'sales.index'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'sales.index'), (SELECT id FROM role_ids WHERE code = 'provider_user')),

    ((SELECT id FROM menu_ids WHERE route_name = 'sales.periods'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'sales.periods'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'sales.periods'), (SELECT id FROM role_ids WHERE code = 'provider_user')),

    ((SELECT id FROM menu_ids WHERE route_name = 'sales.sellout'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'sales.sellout'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'sales.sellout'), (SELECT id FROM role_ids WHERE code = 'provider_user')),

    ((SELECT id FROM menu_ids WHERE route_name = 'others.index'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'others.index'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'others.index'), (SELECT id FROM role_ids WHERE code = 'provider_user')),

    ((SELECT id FROM menu_ids WHERE route_name = 'others.returns'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'others.returns'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'others.returns'), (SELECT id FROM role_ids WHERE code = 'provider_user')),

    ((SELECT id FROM menu_ids WHERE route_name = 'others.inventory'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'others.inventory'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'others.inventory'), (SELECT id FROM role_ids WHERE code = 'provider_user')),

    ((SELECT id FROM menu_ids WHERE route_name = 'notifications.index'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'notifications.index'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'notifications.index'), (SELECT id FROM role_ids WHERE code = 'provider_user')),

    ((SELECT id FROM menu_ids WHERE route_name = 'users.index'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'users.index'), (SELECT id FROM role_ids WHERE code = 'provider_admin')),

    ((SELECT id FROM menu_ids WHERE route_name = 'api.tokens'), (SELECT id FROM role_ids WHERE code = 'super_admin')),
    ((SELECT id FROM menu_ids WHERE route_name = 'api.tokens'), (SELECT id FROM role_ids WHERE code = 'provider_admin'))
ON CONFLICT DO NOTHING;
