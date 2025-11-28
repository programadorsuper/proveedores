<?php

require_once __DIR__ . '/../core/ProtectedController.php';
require_once __DIR__ . '/../models/Appointments.php';
require_once __DIR__ . '/../models/Orders.php';

class AppointmentsController extends ProtectedController
{
    protected Appointments $appointments;
    protected Orders $orders;

    public function __construct()
    {
        parent::__construct();
        $this->appointments = new Appointments();
        $this->orders       = new Orders();
    }

    public function index(): void
    {
        $filters = [
            'status' => $_GET['status'] ?? null,
            'search' => trim((string)($_GET['search'] ?? '')),
        ];

        $providerIds  = $this->context->providerIds();
        $isSuperAdmin = !empty($this->user['is_super_admin']);

        $appointments = $this->appointments->listForUser(
            $this->user,
            $providerIds,
            $filters
        );

        $selectedStoreId    = (int)($_GET['store_id'] ?? 0);
        $selectedSeasonType = $_GET['season_type'] ?? '';

        $stores = $this->orders->getConsignationStores();

        $availableOrders = [];
        if ($selectedStoreId > 0) {
            $excludedIds = $this->appointments->reservedDocumentIds('order');

            $availableOrders = $this->orders->getNewOrdersForAppointmentsByStoreSeason(
                $providerIds,
                [
                    'store_id'    => $selectedStoreId,
                    'season_type' => $selectedSeasonType,
                ],
                $excludedIds,
                $isSuperAdmin
            );
        }

        $this->renderModule('appointments/index', [
            'title'              => 'Citas de proveedor',
            'appointments'       => $appointments,
            'filters'            => $filters,
            'stores'             => $stores,
            'selectedStoreId'    => $selectedStoreId,
            'selectedSeasonType' => $selectedSeasonType,
            'availableOrders'    => $availableOrders,
            'pageScripts'        => ['citas.js'],
        ], 'orders');
    }

    public function list(): void
    {
        $filters = [
            'status' => $_GET['status'] ?? null,
            'search' => trim((string)($_GET['search'] ?? '')),
            'limit'  => (int)($_GET['limit'] ?? 50),
            'offset' => (int)($_GET['offset'] ?? 0),
        ];

        $data = $this->appointments->listForUser(
            $this->user,
            $this->context->providerIds(),
            $filters
        );

        $this->jsonResponse(['data' => $data]);
    }

    public function createDraft(): void
    {
        $isAjax = $this->isAjaxRequest();
        $input  = $_POST;

        $providerId = $this->user['provider_id'] ?? null;
        if ($providerId === null) {
            $msg = 'Proveedor no definido para el usuario.';
            if ($isAjax) {
                $this->jsonResponse(['success' => false, 'message' => $msg], 422);
            }
            $_SESSION['appointments_error'] = $msg;
            $this->redirectToIndex();
        }

        $payload = [
            'provider_id'         => $providerId,
            'delivery_point_code' => $input['delivery_point_code'] ?? null,
            'delivery_point_name' => $input['delivery_point_name'] ?? null,
            'delivery_address'    => $input['delivery_address'] ?? null,
            'appointment_date'    => $input['appointment_date'] ?? date('Y-m-d'),
            'slot_start'          => $input['slot_start'] ?? '08:00',
            'slot_end'            => $input['slot_end'] ?? '09:00',
        ];

        try {
            $appointment = $this->appointments->create($payload, [], $this->user);
            $id          = (int)$appointment['id'];
            $redirectUrl = ($this->basePath !== '' ? $this->basePath : '') . '/citas/' . $id . '/editar';

            if ($isAjax) {
                $this->jsonResponse([
                    'success'      => true,
                    'id'           => $id,
                    'redirect_url' => $redirectUrl,
                ]);
            }

            header('Location: ' . $redirectUrl);
            exit;
        } catch (\Throwable $e) {
            if ($isAjax) {
                $this->jsonResponse(['success' => false, 'message' => $e->getMessage()], 422);
            }
            $_SESSION['appointments_error'] = $e->getMessage();
            $this->redirectToIndex();
        }
    }

    public function store(): void
    {
        $input  = $_POST;
        $isAjax = $this->isAjaxRequest();

        $orderIds = array_map('intval', $input['order_ids'] ?? []);

        if (empty($orderIds)) {
            $message = 'Debes seleccionar al menos una orden para crear la cita.';
            if ($isAjax) {
                $this->jsonResponse(['success' => false, 'message' => $message], 422);
            }
            $_SESSION['appointments_error'] = $message;
            $this->redirectToIndex();
        }

        try {
            $providerIds  = $this->context->providerIds();
            $isSuperAdmin = !empty($this->user['is_super_admin']);

            $orders = $this->orders->getOrdenesByIds($orderIds, $providerIds, $isSuperAdmin);
            if (empty($orders)) {
                throw new RuntimeException('No se encontraron las órdenes seleccionadas.');
            }

            $this->validateOrdersForAppointment($orders);

            $documents = [];
            foreach ($orders as $order) {
                $documents[] = [
                    'document_type'       => 'order',
                    'document_id'         => (int)$order['ID_COMPRA'],
                    'document_reference'  => (string)$order['FOLIO'],
                    'delivery_point_code' => (string)($order['ID_TIENDA_CONSIGNADA'] ?? 0),
                    'delivery_point_name' => (string)($order['NOMBRE_CORTO'] ?? ''),
                    'status'              => 'pending',
                    'requested_total'     => (float)($order['TOTAL'] ?? 0),
                    'invoiced_total'      => 0.0,
                    'summary'             => [
                        'alias'     => $order['ALIAS'] ?? '',
                        'temporada' => $order['TEMPORADA'] ?? '',
                    ],
                ];
            }

            if (!empty($orders)) {
                $first = $orders[0];
                if (empty($input['delivery_point_code'])) {
                    $input['delivery_point_code'] = (string)($first['ID_TIENDA_CONSIGNADA'] ?? 0);
                }
                if (empty($input['delivery_point_name'])) {
                    $input['delivery_point_name'] = (string)($first['NOMBRE_CORTO'] ?? '');
                }
            }

            $appointment = $this->appointments->create($input, $documents, $this->user);

            if ($isAjax) {
                $this->jsonResponse([
                    'success'     => true,
                    'appointment' => $appointment,
                ]);
            }

            $_SESSION['appointments_status'] = 'Cita creada correctamente.';
            $this->redirectToIndex();
        } catch (\Throwable $exception) {
            if ($isAjax) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $exception->getMessage(),
                ], 422);
            }

            $_SESSION['appointments_error'] = $exception->getMessage();
            $this->redirectToIndex();
        }
    }

    public function edit(int $id): void
    {
        $appointment = $this->appointments->findById($id);
        if (!$appointment) {
            $_SESSION['appointments_error'] = 'Cita no encontrada.';
            $this->redirectToIndex();
        }

        $documents = $this->appointments->getDocumentsForAppointment($id);

        $this->renderModule('appointments/edit', [
            'title'       => 'Cita ' . ($appointment['folio'] ?? 'CITA-' . $id),
            'appointment' => $appointment,
            'documents'   => $documents,
            'pageScripts' => ['citas-detalles.js'],
        ], 'orders');
    }

    public function cancel(): void
    {
        $id     = (int)($_POST['id'] ?? 0);
        $reason = trim((string)($_POST['reason'] ?? 'Cancelado por proveedor'));
        $isAjax = $this->isAjaxRequest();

        if ($id <= 0) {
            if ($isAjax) {
                $this->jsonResponse(['success' => false, 'message' => 'Cita inválida.'], 422);
                return;
            }
            $_SESSION['appointments_error'] = 'Cita inválida.';
            $this->redirectToIndex();
        }

        try {
            $appointment = $this->appointments->cancel($id, $this->user, $reason);
            if ($isAjax) {
                $this->jsonResponse(['success' => true, 'appointment' => $appointment]);
            }

            $_SESSION['appointments_status'] = 'Cita cancelada correctamente.';
            $this->redirectToIndex();
        } catch (\Throwable $exception) {
            if ($isAjax) {
                $this->jsonResponse([
                    'success' => false,
                    'message' => $exception->getMessage(),
                ], 422);
            }

            $_SESSION['appointments_error'] = $exception->getMessage();
            $this->redirectToIndex();
        }
    }

    protected function validateOrdersForAppointment(array $orders): void
    {
        if (empty($orders)) {
            throw new RuntimeException('No hay órdenes para validar.');
        }

        $first        = $orders[0];
        $expectedName = strtoupper((string)($first['NOMBRE_CORTO'] ?? ''));
        $expectedCode = (string)($first['ID_TIENDA_CONSIGNADA'] ?? 0);

        foreach ($orders as $order) {
            $name = strtoupper((string)($order['NOMBRE_CORTO'] ?? ''));
            $code = (string)($order['ID_TIENDA_CONSIGNADA'] ?? 0);

            if ($name !== $expectedName || $code !== $expectedCode) {
                throw new RuntimeException('Todas las órdenes de una cita deben ser del mismo punto de entrega.');
            }
        }

        $hasSpecial = false;
        foreach ($orders as $order) {
            if (
                strtoupper((string)($order['ALIAS'] ?? '')) === 'S'
                || (int)($order['ID_TEMPORADA'] ?? 0) === 3
            ) {
                $hasSpecial = true;
                break;
            }
        }

        if ($hasSpecial) {
            foreach ($orders as $order) {
                if (
                    strtoupper((string)($order['ALIAS'] ?? '')) !== 'S'
                    && (int)($order['ID_TEMPORADA'] ?? 0) !== 3
                ) {
                    throw new RuntimeException('Si una orden es especial (alias S / temporada 3), todas las órdenes de la cita deben ser especiales.');
                }
            }
        }
    }

    protected function jsonResponse(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }

    protected function redirectToIndex(): void
    {
        $target = $this->basePath !== '' ? $this->basePath . '/citas' : '/citas';
        header('Location: ' . $target);
        exit;
    }

    public function availableOrdersAjax(): void
    {
        if (!$this->isAjaxRequest()) {
            http_response_code(400);
            echo 'Bad request';
            exit;
        }

        $storeId = (int)($_GET['store_id'] ?? 0);
        $alias   = isset($_GET['alias']) ? trim((string)$_GET['alias']) : '';
        $folio   = isset($_GET['folio']) ? (int)($_GET['folio'] ?? 0) : 0;
        $serie   = isset($_GET['serie']) ? trim((string)$_GET['serie']) : '';

        $providerIds  = $this->context->providerIds();
        $isSuperAdmin = !empty($this->user['is_super_admin']);

        if ($storeId < 0) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Debes seleccionar una tienda.',
                'orders'  => [],
            ], 422);
        }

        $filters = [
            'store_id' => $storeId,
        ];
        if ($alias !== '') {
            $filters['alias'] = $alias;
        }
        if ($folio > 0) {
            $filters['folio'] = $folio;
        }
        if ($serie !== '') {
            $filters['serie'] = $serie;
        }

        try {
            $excludedIds = $this->appointments->reservedDocumentIds('order');

            $orders = $this->orders->getNewOrdersForAppointmentsByStoreSeason(
                $providerIds,
                $filters,
                $excludedIds,
                $isSuperAdmin
            );

            $this->jsonResponse([
                'success' => true,
                'orders'  => $orders,
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
                'orders'  => [],
            ], 500);
        }
    }

    /**
     * AJAX: resumen de una orden (cabecera + detalle) para el modal.
     * GET /citas/orden-resumen?id_compra=123
     */
    public function orderSummaryAjax(): void
    {
        if (!$this->isAjaxRequest()) {
            http_response_code(400);
            echo 'Bad request';
            exit;
        }

        $idCompra = (int)($_GET['id_compra'] ?? 0);
        if ($idCompra <= 0) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Orden inválida.',
            ], 422);
        }

        try {
            $providerIds  = $this->context->providerIds();
            $isSuperAdmin = !empty($this->user['is_super_admin']);

            $orders = $this->orders->getOrdenesByIds2([$idCompra], $providerIds, $isSuperAdmin);
            if (empty($orders)) {
                throw new RuntimeException('No se encontró la orden o no tienes acceso.');
            }

            $header  = $orders[0];
            $detalle = $this->orders->detallesParaCita($idCompra);

            $this->jsonResponse([
                'success' => true,
                'header'  => $header,
                'detalle' => $detalle,
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * AJAX: subir XML/PDF + cantidades capturadas para una orden en una cita.
     */

    // app/controllers/AppointmentsController.php

    public function uploadOrderFilesAjax(): void
    {
        if (!$this->isAjaxRequest()) {
            http_response_code(400);
            echo 'Bad request';
            exit;
        }

        $idCompra      = (int)($_POST['id_compra'] ?? 0);
        $appointmentId = (int)($_POST['appointment_id'] ?? 0);
        $comment       = trim((string)($_POST['comment'] ?? ''));

        if ($idCompra <= 0 || $appointmentId <= 0) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Orden o cita inválida.',
            ], 422);
        }

        // Items de entrega (vienen del JS como JSON)
        $deliveryItemsJson = $_POST['delivery_items'] ?? '[]';
        $deliveryItems     = json_decode($deliveryItemsJson, true);
        if (!is_array($deliveryItems)) {
            $deliveryItems = [];
        }

        // Nombres de inputs que arma el JS
        $xmlFiles = $_FILES['xml_files'] ?? null;
        $pdfFiles = $_FILES['pdf_files'] ?? null;

        $hasXml = $xmlFiles && isset($xmlFiles['name']) && is_array($xmlFiles['name']) && count(array_filter($xmlFiles['name'])) > 0;
        $hasPdf = $pdfFiles && isset($pdfFiles['name']) && is_array($pdfFiles['name']) && count(array_filter($pdfFiles['name'])) > 0;

        if (!$hasXml && !$hasPdf) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Debes seleccionar al menos un archivo XML o PDF.',
            ], 422);
        }

        // Raíz del proyecto (app/controllers => subir dos niveles)
        $projectRoot = dirname(__DIR__, 2);
        $relativeDir = 'storage/appointments/orders/' . $idCompra;
        $baseDir     = $projectRoot . '/' . $relativeDir;

        if (!is_dir($baseDir)) {
            @mkdir($baseDir, 0775, true);
        }

        $saved  = [
            'xml' => [],
            'pdf' => [],
        ];
        $errors = [];

        // Para no subir duplicados en la misma petición (mismo nombre/tamaño/tipo)
        $seen = [
            'xml' => [],
            'pdf' => [],
        ];

        $processGroup = function (array $bag, string $type) use (
            &$saved,
            &$errors,
            &$seen,
            $baseDir,
            $relativeDir
        ) {
            $names     = $bag['name'] ?? [];
            $tmpNames  = $bag['tmp_name'] ?? [];
            $sizes     = $bag['size'] ?? [];
            $types     = $bag['type'] ?? [];
            $errorsArr = $bag['error'] ?? [];

            foreach ($names as $idx => $filename) {
                if ($filename === null || $filename === '') {
                    continue;
                }

                $size = (int)($sizes[$idx] ?? 0);
                $mime = (string)($types[$idx] ?? '');

                // evitar duplicado dentro de la misma petición
                $dupKey = $filename . '|' . $size . '|' . $mime;
                if (in_array($dupKey, $seen[$type], true)) {
                    $errors[] = $filename . ' (duplicado ignorado)';
                    continue;
                }
                $seen[$type][] = $dupKey;

                $errorCode = (int)($errorsArr[$idx] ?? UPLOAD_ERR_NO_FILE);
                if ($errorCode !== UPLOAD_ERR_OK) {
                    $errors[] = $filename . ' (error de subida)';
                    continue;
                }

                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

                if ($type === 'xml' && $ext !== 'xml') {
                    $errors[] = $filename . ' (debe ser XML)';
                    continue;
                }
                if ($type === 'pdf' && $ext !== 'pdf') {
                    $errors[] = $filename . ' (debe ser PDF)';
                    continue;
                }

                $safeOriginal = preg_replace('/[^a-zA-Z0-9_\.\-]/', '_', $filename);
                $safeName     = date('Ymd_His') . '_' . $safeOriginal;
                $target       = $baseDir . '/' . $safeName;
                $storagePath  = $relativeDir . '/' . $safeName;

                if (!move_uploaded_file($tmpNames[$idx], $target)) {
                    $errors[] = $filename . ' (no se pudo guardar)';
                    continue;
                }

                $checksum = null;
                if (is_readable($target)) {
                    $checksum = hash_file('sha256', $target);
                }

                $saved[$type][] = [
                    'original_name' => $filename,
                    'storage_path'  => $storagePath, // relativo a la raíz del proyecto
                    'mime_type'     => $mime,
                    'size_bytes'    => $size,
                    'checksum'      => $checksum,
                ];
            }
        };

        if ($hasXml) {
            $processGroup($xmlFiles, 'xml');
        }
        if ($hasPdf) {
            $processGroup($pdfFiles, 'pdf');
        }

        try {
            // 👉 aquí ya usamos el método del modelo con la firma nueva
            $this->appointments->saveOrderDeliveryFromModal(
                $appointmentId,
                $idCompra,
                $deliveryItems,
                $saved,
                $comment,
                $this->user
            );

            $this->jsonResponse([
                'success'        => true,
                'saved'          => $saved,
                'errors'         => $errors,
                'delivery_items' => $deliveryItems,
                'comment'        => $comment,
            ]);
        } catch (\Throwable $e) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Error al guardar resumen de la orden: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function documentsAjax(int $id): void
    {
        if (!$this->isAjaxRequest()) {
            http_response_code(400);
            echo 'Bad request';
            exit;
        }

        $appointment = $this->appointments->findById($id);
        if (!$appointment) {
            $this->jsonResponse([
                'success'   => false,
                'message'   => 'Cita no encontrada.',
                'documents' => [],
            ], 404);
        }

        $documents = $this->appointments->getDocumentsForAppointment($id);

        $this->jsonResponse([
            'success'   => true,
            'documents' => $documents,
        ]);
    }
}
