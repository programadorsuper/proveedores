<?php

require_once __DIR__ . '/../core/ProtectedController.php';

class OthersController extends ProtectedController
{
    public function index(): void
    {
        $this->renderOthersPage('Otros modulos', 'index');
    }

    public function devoluciones(): void
    {
        $this->renderOthersPage('Devoluciones', 'returns');
    }

    public function inventario(): void
    {
        $this->renderOthersPage('Inventario', 'inventory');
    }

    protected function renderOthersPage(string $title, string $page): void
    {
        $this->renderModule('others/index', [
            'title' => $title,
            'page' => $page,
        ], 'others');
    }
}
