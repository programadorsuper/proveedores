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

        $providerIds = $this->context->providerIds();
        $isSuperAdmin = !empty($this->user['is_super_admin']);

        // Listado de citas existentes (Postgres)
        $appointments = $this->appointments->listForUser(
            $this->user,
            $providerIds,
            $filters
        );

        // --- Filtros para órdenes nuevas (Firebird) controlados desde el modal ---
        $selectedStoreId   = (int)($_GET['store_id'] ?? 0);
        $selectedSeasonType = $_GET['season_type'] ?? ''; // 'special' | 'normal' | ''

        // Catálogo de tiendas de consignación (Firebird)
        $stores = $this->orders->getConsignationStores();

        $availableOrders = [];
        if ($selectedStoreId > 0) {
            // Órdenes ya reservadas en citas "vivas"
            $excludedIds = $this->appointments->reservedDocumentIds('order');

            // Órdenes nuevas por tienda + tipo temporada
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
            'title'             => 'Citas de proveedor',
            'appointments'      => $appointments,
            'filters'           => $filters,
            'stores'            => $stores,
            'selectedStoreId'   => $selectedStoreId,
            'selectedSeasonType'=> $selectedSeasonType,
            'availableOrders'   => $availableOrders,
            'pageScripts'       => ['citas.js'],
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

    /**
     * Crear una nueva cita a partir de órdenes seleccionadas (order_ids[]).
     */
    public function store(): void
    {
        $input  = $_POST;
        $isAjax = $this->isAjaxRequest();

        // Órdenes seleccionadas en la tabla
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

            // 1) Cargar las órdenes desde Firebird
            $orders = $this->orders->getOrdenesByIds($orderIds, $providerIds, $isSuperAdmin);
            if (empty($orders)) {
                throw new RuntimeException('No se encontraron las órdenes seleccionadas.');
            }

            // 2) Validar reglas de negocio: mismo punto de entrega + regla alias S
            $this->validateOrdersForAppointment($orders);

            // 3) Construir arreglo de documentos para Appointments::create
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

            // 4) Normalizar delivery_point_* de la cita desde la primera orden, si no viene en el form
            if (!empty($orders)) {
                $first = $orders[0];
                if (empty($input['delivery_point_code'])) {
                    $input['delivery_point_code'] = (string)($first['ID_TIENDA_CONSIGNADA'] ?? 0);
                }
                if (empty($input['delivery_point_name'])) {
                    $input['delivery_point_name'] = (string)($first['NOMBRE_CORTO'] ?? '');
                }
            }

            // 5) Crear la cita (Postgres)
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

    public function cancel(): void
    {
        $id      = (int)($_POST['id'] ?? 0);
        $reason  = trim((string)($_POST['reason'] ?? 'Cancelado por proveedor'));
        $isAjax  = $this->isAjaxRequest();

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

    /**
     * Reglas de negocio de órdenes en una cita:
     * - Todas del mismo punto de entrega.
     * - Si alguna es alias S (temporada especial), todas deben ser alias S.
     */
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
            if (strtoupper((string)($order['ALIAS'] ?? '')) === 'S' || (int)($order['ID_TEMPORADA'] ?? 0) === 3) {
                $hasSpecial = true;
                break;
            }
        }

        if ($hasSpecial) {
            foreach ($orders as $order) {
                if (strtoupper((string)($order['ALIAS'] ?? '')) !== 'S'
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
        /**
     * AJAX: listar órdenes disponibles según tienda + tipo temporada.
     * URL sugerida: GET /citas/ordenes-disponibles
     */
    public function availableOrdersAjax(): void
    {
        $isAjax = $this->isAjaxRequest();
        if (!$isAjax) {
            http_response_code(400);
            echo 'Bad request';
            exit;
        }

        $storeId    = (int)($_GET['store_id'] ?? 0);
        $seasonType = (string)($_GET['season_type'] ?? '');
        $providerIds  = $this->context->providerIds();
        $isSuperAdmin = !empty($this->user['is_super_admin']);

        if ($storeId < 0 || $seasonType === '') {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Debes seleccionar tienda y tipo de temporada.',
                'orders'  => [],
            ], 422);
        }

        try {
            $excludedIds = $this->appointments->reservedDocumentIds('order');

            $orders = $this->orders->getNewOrdersForAppointmentsByStoreSeason(
                $providerIds,
                [
                    'store_id'    => $storeId,
                    'season_type' => $seasonType,
                ],
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
     * URL sugerida: GET /citas/orden-resumen?id_compra=123
     */
    public function orderSummaryAjax(): void
    {
        $isAjax = $this->isAjaxRequest();
        if (!$isAjax) {
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

            // Validamos que la orden pertenezca al proveedor, si aplica
            $orders = $this->orders->getOrdenesByIds([$idCompra], $providerIds, $isSuperAdmin);
            if (empty($orders)) {
                throw new RuntimeException('No se encontró la orden o no tienes acceso.');
            }
            $header  = $orders[0];
            $detalle = $this->orders->detallesByOrden($idCompra);

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
     * AJAX: subir archivos XML/PDF para una orden.
     * Por ahora solo te dejo el esqueleto; aquí guardarías los archivos en disco
     * y en una tabla de staging ligada al ID_COMPRA.
     *
     * URL sugerida: POST /citas/orden-archivos
     * Campos:
     *  - id_compra
     *  - files[] (input multiple)
     */
    public function uploadOrderFilesAjax(): void
    {
        $isAjax = $this->isAjaxRequest();
        if (!$isAjax) {
            http_response_code(400);
            echo 'Bad request';
            exit;
        }

        $idCompra = (int)($_POST['id_compra'] ?? 0);
        if ($idCompra <= 0) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Orden inválida.',
            ], 422);
        }

        if (empty($_FILES['files']) || !is_array($_FILES['files']['name'])) {
            $this->jsonResponse([
                'success' => false,
                'message' => 'Debes seleccionar al menos un archivo XML o PDF.',
            ], 422);
        }

        // TODO: ajusta esta ruta a tu estructura real
        $baseDir = __DIR__ . '/../../storage/appointments/orders/' . $idCompra;
        if (!is_dir($baseDir)) {
            @mkdir($baseDir, 0775, true);
        }

        $saved = [];
        $errors = [];

        $names     = $_FILES['files']['name'];
        $tmpNames  = $_FILES['files']['tmp_name'];
        $types     = $_FILES['files']['type'];
        $sizes     = $_FILES['files']['size'];
        $errorsArr = $_FILES['files']['error'];

        foreach ($names as $idx => $filename) {
            if ((int)$errorsArr[$idx] !== UPLOAD_ERR_OK) {
                $errors[] = $filename . ' (error de subida)';
                continue;
            }

            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (!in_array($ext, ['xml', 'pdf'], true)) {
                $errors[] = $filename . ' (extensión no permitida)';
                continue;
            }

            $safeName = date('Ymd_His') . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $filename);
            $target   = $baseDir . '/' . $safeName;

            if (!move_uploaded_file($tmpNames[$idx], $target)) {
                $errors[] = $filename . ' (no se pudo guardar)';
                continue;
            }

            // Aquí podrías guardar en Postgres una fila en appointment_files_temp, por ejemplo
            // con: id_compra, nombre_archivo, ruta, tipo (xml/pdf), tamaño, etc.

            $saved[] = $safeName;
        }

        $this->jsonResponse([
            'success' => empty($errors),
            'saved'   => $saved,
            'errors'  => $errors,
        ], empty($errors) ? 200 : 207); // 207 Multi-Status si hubo mezcla
    }

}
