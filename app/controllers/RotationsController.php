<?php

require_once __DIR__ . '/../core/ProtectedController.php';
require_once __DIR__ . '/../models/Rotations.php';

class RotationsController extends ProtectedController
{
    protected Rotations $rotations;

    public function __construct()
    {
        parent::__construct();
        $this->rotations = new Rotations();
    }

    public function index(): void
    {
        if (!$this->ensureModule('rotations')) {
            return;
        }

        $providerId = $this->providerContext()->primaryProviderId();
        $data = $providerId ? $this->rotations->getMonthly($providerId) : [];

        $this->renderModule('rotations/index', [
            'title' => 'Rotacion',
            'series' => $data,
        ], 'rotations');
    }

    public function turnover(): void
    {
        $this->index();
    }
}
