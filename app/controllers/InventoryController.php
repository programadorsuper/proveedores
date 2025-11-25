<?php

require_once __DIR__ . '/../core/ProtectedController.php';
require_once __DIR__ . '/../models/Inventory.php';

class InventoryController extends ProtectedController
{
    protected Inventory $inventory;

    public function __construct()
    {
        parent::__construct();
        $this->inventory = new Inventory();
    }

    public function index(): void
    {
        if (!$this->ensureModule('inventory')) {
            return;
        }

        $providerId = $this->providerContext()->primaryProviderId();
        $coverage = $providerId ? $this->inventory->getCoverage($providerId) : [];

        $this->renderModule('inventory/index', [
            'title' => 'Inventario',
            'coverage' => $coverage,
        ], 'inventory');
    }

    public function cover(): void
    {
        if (!$this->ensureModule('inventory')) {
            return;
        }

        $providerId = $this->providerContext()->primaryProviderId();
        $coverage = $providerId ? $this->inventory->getCoverage($providerId, $_GET ?? []) : [];

        $this->renderModule('inventory/index', [
            'title' => 'Cobertura',
            'coverage' => $coverage,
        ], 'inventory');
    }

    public function breaks(): void
    {
        if (!$this->ensureModule('inventory')) {
            return;
        }

        $providerId = $this->providerContext()->primaryProviderId();
        $breaks = $providerId ? $this->inventory->getBreaks($providerId, $_GET ?? []) : [];

        $this->renderModule('inventory/breaks', [
            'title' => 'Quiebres',
            'breaks' => $breaks,
        ], 'inventory');
    }
}
