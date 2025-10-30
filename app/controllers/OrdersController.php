<?php

require_once __DIR__ . '/../core/ProtectedController.php';

class OrdersController extends ProtectedController
{
    public function index(): void
    {
        $this->renderOrdersPage('Ordenes', 'index');
    }

    public function nuevas(): void
    {
        $this->renderOrdersPage('Ordenes nuevas', 'nuevas');
    }

    public function backorder(): void
    {
        $this->renderOrdersPage('Ordenes backorder', 'backorder');
    }

    public function entradas(): void
    {
        $this->renderOrdersPage('Ordenes con entrada', 'entradas');
    }

    protected function renderOrdersPage(string $title, string $page): void
    {
        $this->renderModule('orders/index', [
            'title' => $title,
            'page' => $page,
        ]);
    }
}
