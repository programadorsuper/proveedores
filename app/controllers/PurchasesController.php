<?php

require_once __DIR__ . '/../core/ProtectedController.php';

class PurchasesController extends ProtectedController
{
    public function index(): void
    {
        $this->renderModule('purchases/index', [
            'title' => 'Compras',
            'description' => 'Resumen de ordenes de compra y recepciones.',
        ]);
    }

    public function periods(): void
    {
        $this->renderModule('purchases/index', [
            'title' => 'Compras por periodo',
            'description' => 'Analisis por periodo y cadena.',
        ]);
    }

    public function sellin(): void
    {
        $this->renderModule('purchases/index', [
            'title' => 'Sell In compras',
            'description' => 'Movimiento de entrada contra presupuestos.',
        ]);
    }
}
