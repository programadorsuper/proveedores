<?php

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/ProviderContext.php';
require_once __DIR__ . '/../models/Proveedor.php';
require_once __DIR__ . '/../models/Dashboard.php';

abstract class ProtectedController extends BaseController
{
    protected array $config = [];
    protected ?array $user = null;
    protected ProviderContext $context;
    protected array $menus = [];
    protected array $permissions = [];
    protected array $contact = [];
    protected string $basePath = '';
    protected array $moduleAccess = [];

    public function __construct()
    {
        $this->config = require __DIR__ . '/../../config/config.php';
        $this->basePath = $this->config['base_path'] ?? $this->config['base_url'] ?? '';
        $this->contact = $this->config['contact'] ?? [];
        $this->bootSession();
        $this->user = $_SESSION['auth_user'] ?? null;

        if (!$this->user) {
            $target = $this->basePath !== '' ? $this->basePath . '/login' : '/login';
            header('Location: ' . $target);
            exit;
        }

        $this->context = new ProviderContext($this->user);
        $this->moduleAccess = $this->context->moduleAccess();

        $model = new Proveedor();
        $this->menus = $model->getMenus($this->user, $this->context);
        $this->permissions = $this->mapPermissions($this->collectRouteNames($this->menus));
    }

    protected function renderModule(string $view, array $data = [], ?string $moduleCode = null): void
    {
        if ($moduleCode !== null && !$this->ensureModule($moduleCode)) {
            return;
        }

        $shared = [
            'title' => $data['title'] ?? 'Modulo',
            'user' => $this->user,
            'menus' => $this->menus,
            'basePath' => $this->basePath,
            'assets' => $this->config['assets'] ?? [],
            'contact' => $this->contact,
            'permissions' => $this->permissions,
            'moduleAccess' => $this->moduleAccess,
            'membershipPlan' => $this->context->membershipPlan(),
            'isProMembership' => $this->context->isPro(),
            'isEnterpriseMembership' => $this->context->isEnterprise(),
        ];

        $this->render($view, array_merge($shared, $data));
    }

    protected function bootSession(): void
    {
        $sessionName = $this->config['session']['name'] ?? $this->config['session_name'] ?? null;
        if ($sessionName && session_status() === PHP_SESSION_NONE) {
            session_name($sessionName);
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    protected function collectRouteNames(array $menus): array
    {
        $routes = [];
        $walker = static function (array $items) use (&$walker, &$routes): void {
            foreach ($items as $item) {
                if (!empty($item['route_name'])) {
                    $routes[] = (string)$item['route_name'];
                }
                if (!empty($item['children'])) {
                    $walker($item['children']);
                }
            }
        };
        $walker($menus);
        return array_values(array_unique($routes));
    }

    protected function mapPermissions(array $routes): array
    {
        $routes = array_map('strval', $routes);
        $hasAny = static function (array $need, array $routes): bool {
            foreach ($need as $candidate) {
                if (in_array($candidate, $routes, true)) {
                    return true;
                }
            }
            return false;
        };

        return [
            'dashboard' => $hasAny(['dashboard.index'], $routes),
            'sales' => $hasAny(['sales.index', 'sales.periods', 'sales.sellout', 'sellinout.index'], $routes),
            'purchases' => $hasAny(['purchases.index', 'purchases.periods', 'purchases.sellin'], $routes),
            'orders' => $hasAny(['orders.index', 'orders.news', 'orders.backorders', 'orders.entries'], $routes),
            'others' => $hasAny(['others.index', 'others.returns', 'others.inventory'], $routes),
            'users' => $hasAny(['users.index', 'users.admin'], $routes),
            'providers' => $hasAny(['providers.index'], $routes),
            'tickets' => $hasAny(['tickets.index', 'tickets.search'], $routes),
            'exports' => $hasAny(['exports.index'], $routes),
        ];
    }

    protected function dashboardService(): Dashboard
    {
        return new Dashboard();
    }

    protected function providerContext(): ProviderContext
    {
        return $this->context;
    }

    protected function isAjaxRequest(): bool
    {
        $requestedWith = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
        $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');

        return $requestedWith === 'xmlhttprequest'
            || $requestedWith === 'fetch'
            || str_contains($accept, 'application/json');
    }

    protected function ensureModule(string $moduleCode): bool
    {
        if ($this->context->canAccessModule($moduleCode)) {
            return true;
        }

        $this->denyModule($moduleCode);
        return false;
    }

    protected function denyModule(string $moduleCode): void
    {
        http_response_code(403);
        $this->render('errors/module_locked', [
            'title' => 'Acceso restringido',
            'lockedModule' => $moduleCode,
            'membershipPlan' => $this->context->membershipPlan(),
            'moduleAccess' => $this->moduleAccess,
            'user' => $this->user,
            'menus' => $this->menus,
            'basePath' => $this->basePath,
            'assets' => $this->config['assets'] ?? [],
            'contact' => $this->contact,
            'permissions' => $this->permissions,
            'isProMembership' => $this->context->isPro(),
            'isEnterpriseMembership' => $this->context->isEnterprise(),
        ]);
        exit;
    }
}
