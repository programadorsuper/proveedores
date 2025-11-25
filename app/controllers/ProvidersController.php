<?php

require_once __DIR__ . '/../core/ProtectedController.php';
require_once __DIR__ . '/../models/Providers.php';

class ProvidersController extends ProtectedController
{
    protected Providers $providers;

    public function __construct()
    {
        parent::__construct();
        $this->providers = new Providers();
    }

    public function index(): void
    {
        if (!$this->ensureModule('providers')) {
            return;
        }

        if (empty($this->permissions['providers'])) {
            $this->renderModule('providers/index', [
                'title' => 'Proveedores',
                'providers' => [],
                'accessDenied' => true,
            ], 'providers');
            return;
        }

        $list = $this->providers->listForUser($this->user);

        $this->renderModule('providers/index', [
            'title' => 'Proveedores',
            'providers' => $list,
            'accessDenied' => false,
            'isSuperAdmin' => !empty($this->user['is_super_admin']),
        ], 'providers');
    }
}
