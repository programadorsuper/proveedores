<?php

require_once __DIR__ . '/../core/ProtectedController.php';

class ReportsController extends ProtectedController
{
    public function index(): void
    {
        $this->renderModule('reports/index', [
            'title' => 'Reportes',
        ], 'analytics');
    }
}
