<?php

require_once __DIR__ . '/../core/ProtectedController.php';

class ExportsController extends ProtectedController
{
    public function index(): void
    {
        if (!$this->ensureModule('exports')) {
            return;
        }

        $this->renderModule('exports/index', [
            'title' => 'Exportaciones',
        ], 'exports');
    }

    public function create(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            return;
        }

        if (!$this->providerContext()->canAccessModule('exports')) {
            http_response_code(403);
            echo 'Modulo no disponible';
            return;
        }

        // Placeholder para programar exportes; se integra con jobs posteriormente
        http_response_code(200);
        echo 'Exportacion programada';
    }
}
