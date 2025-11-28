<?php

class Appointments
{
    protected \PDO $db;

    public function __construct()
    {
        $this->db = Database::pgsql();
    }

    public function listForUser(array $user, array $providerIds, array $filters = []): array
    {
        $isSuperAdmin    = !empty($user['is_super_admin']);
        $isProviderAdmin = !empty($user['is_provider_admin']);

        $conditions = [];
        $params     = [];

        if (!$isSuperAdmin) {
            if ($isProviderAdmin && !empty($providerIds)) {
                $conditions[]          = 'a.provider_id = ANY(:provider_ids::bigint[])';
                $params['provider_ids'] = '{' . implode(',', array_map('intval', $providerIds)) . '}';
            } else {
                $conditions[]        = 'a.created_by = :created_by';
                $params['created_by'] = (int)($user['id'] ?? 0);
            }
        }

        if (!empty($filters['status']) && is_string($filters['status'])) {
            $conditions[]   = 'a.status = :status';
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['search']) && is_string($filters['search'])) {
            $conditions[]    = '(a.folio ILIKE :search OR a.delivery_point_name ILIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }

        $where  = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $limit  = max(1, min(200, (int)($filters['limit'] ?? 50)));
        $offset = max(0, (int)($filters['offset'] ?? 0));

        $sql = "
            SELECT
                a.*,
                u.username AS created_by_username,
                prov.name  AS provider_name,
                COUNT(ad.id) AS documents_count,
                COALESCE(SUM(ad.requested_total), 0) AS total_requested,
                COALESCE(SUM(ad.invoiced_total), 0) AS total_invoiced
            FROM proveedores.appointments a
            LEFT JOIN proveedores.users u ON u.id = a.created_by
            LEFT JOIN proveedores.providers prov ON prov.id = a.provider_id
            LEFT JOIN proveedores.appointment_documents ad ON ad.appointment_id = a.id
            {$where}
            GROUP BY a.id, u.username, prov.name
            ORDER BY a.created_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            if (is_int($value)) {
                $stmt->bindValue(':' . $key, $value, \PDO::PARAM_INT);
            } elseif ($key === 'provider_ids') {
                $stmt->bindValue(':' . $key, $value, \PDO::PARAM_STR);
            } else {
                $stmt->bindValue(':' . $key, $value);
            }
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function create(array $payload, array $documents, array $user): array
    {
        $providerId = $payload['provider_id'] ?? $user['provider_id'] ?? null;
        if ($providerId === null) {
            throw new RuntimeException('Proveedor requerido para crear la cita.');
        }

        $date      = $payload['appointment_date'] ?? null;
        $slotStart = $payload['slot_start'] ?? null;
        $slotEnd   = $payload['slot_end'] ?? null;

        if (!$date || !$slotStart || !$slotEnd) {
            throw new RuntimeException('Debes capturar fecha y horario para la cita.');
        }

        if ($slotStart < '08:00' || $slotStart > '15:00' || $slotEnd < '08:00' || $slotEnd > '15:00') {
            throw new RuntimeException('El horario de la cita debe estar entre 08:00 y 15:00 hrs.');
        }

        $this->db->beginTransaction();
        try {
            $insert = $this->db->prepare("
                INSERT INTO proveedores.appointments (
                    folio,
                    provider_id,
                    created_by,
                    delivery_point_code,
                    delivery_point_name,
                    delivery_point_type,
                    delivery_address,
                    appointment_date,
                    slot_start,
                    slot_end,
                    status
                ) VALUES (
                    NULL,
                    :provider_id,
                    :created_by,
                    :delivery_point_code,
                    :delivery_point_name,
                    :delivery_point_type,
                    :delivery_address,
                    :appointment_date,
                    :slot_start,
                    :slot_end,
                    'draft'
                )
                RETURNING id
            ");

            $insert->execute([
                'provider_id'         => (int)$providerId,
                'created_by'          => (int)$user['id'],
                'delivery_point_code' => $payload['delivery_point_code'] ?? null,
                'delivery_point_name' => $payload['delivery_point_name'] ?? null,
                'delivery_point_type' => $payload['delivery_point_type'] ?? null,
                'delivery_address'    => $payload['delivery_address'] ?? null,
                // puedes ajustar luego a la fecha real; aquí usas la que viene
                'appointment_date'    => $date,
                'slot_start'          => $slotStart,
                'slot_end'            => $slotEnd,
            ]);

            $appointmentId = (int)$insert->fetchColumn();
            $folio         = sprintf('CITA-%s-%05d', date('Ymd'), $appointmentId);

            $this->db->prepare("UPDATE proveedores.appointments SET folio = :folio WHERE id = :id")
                ->execute(['folio' => $folio, 'id' => $appointmentId]);

            $docStmt = $this->db->prepare("
                INSERT INTO proveedores.appointment_documents (
                    appointment_id,
                    provider_id,
                    document_type,
                    document_id,
                    document_reference,
                    delivery_point_code,
                    delivery_point_name,
                    status,
                    requested_total,
                    invoiced_total,
                    summary
                ) VALUES (
                    :appointment_id,
                    :provider_id,
                    :document_type,
                    :document_id,
                    :document_reference,
                    :delivery_point_code,
                    :delivery_point_name,
                    :status,
                    :requested_total,
                    :invoiced_total,
                    :summary
                )
                RETURNING id
            ");

            $reservationStmt = $this->db->prepare("
                INSERT INTO proveedores.document_reservations (
                    document_type,
                    document_id,
                    provider_id,
                    appointment_id,
                    delivery_point_code
                ) VALUES (
                    :document_type,
                    :document_id,
                    :provider_id,
                    :appointment_id,
                    :delivery_point_code
                )
            ");

            if (!empty($documents)) {
                foreach ($documents as $doc) {
                    $docType   = $doc['document_type'] ?? 'order';
                    $docId     = isset($doc['document_id']) ? (int)$doc['document_id'] : null;
                    $reference = trim((string)($doc['document_reference'] ?? ''));

                    if ($reference === '') {
                        continue;
                    }

                    $docStmt->execute([
                        'appointment_id'      => $appointmentId,
                        'provider_id'         => (int)$providerId,
                        'document_type'       => $docType,
                        'document_id'         => $docId,
                        'document_reference'  => $reference,
                        'delivery_point_code' => $doc['delivery_point_code'] ?? $payload['delivery_point_code'] ?? null,
                        'delivery_point_name' => $doc['delivery_point_name'] ?? $payload['delivery_point_name'] ?? null,
                        'status'              => $doc['status'] ?? 'pending',
                        'requested_total'     => (float)($doc['requested_total'] ?? 0),
                        'invoiced_total'      => (float)($doc['invoiced_total'] ?? 0),
                        'summary'             => json_encode($doc['summary'] ?? [], JSON_UNESCAPED_UNICODE),
                    ]);

                    if ($docId !== null) {
                        $reservationStmt->execute([
                            'document_type'       => $docType,
                            'document_id'         => $docId,
                            'provider_id'         => (int)$providerId,
                            'appointment_id'      => $appointmentId,
                            'delivery_point_code' => $doc['delivery_point_code'] ?? $payload['delivery_point_code'] ?? null,
                        ]);
                    }
                }
            }

            $this->addEvent($appointmentId, 'created', [
                'creator'   => $user['username'] ?? '',
                'documents' => count($documents),
            ], (int)$user['id']);

            $this->db->commit();

            return $this->findById($appointmentId);
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function getDocumentsForAppointment(int $appointmentId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                ad.*,
                COUNT(af.id) AS files_count,
                COALESCE(SUM(aoi.qty_to_deliver), 0) AS total_items
            FROM proveedores.appointment_documents ad
            LEFT JOIN proveedores.appointment_files af
                ON af.appointment_document_id = ad.id
            LEFT JOIN proveedores.appointment_order_items aoi
                ON aoi.appointment_id = ad.appointment_id
            AND aoi.order_id       = ad.document_id
            WHERE ad.appointment_id = :id
            GROUP BY ad.id
            ORDER BY ad.id ASC
        ");
        $stmt->execute(['id' => $appointmentId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }


    public function cancel(int $appointmentId, array $user, string $reason = null): array
    {
        $appointment = $this->findById($appointmentId);
        if (!$appointment) {
            throw new RuntimeException('Cita no encontrada.');
        }
        if ($appointment['status'] !== 'in_process') {
            throw new RuntimeException('Solo puedes cancelar citas en proceso.');
        }

        $this->db->beginTransaction();
        try {
            $this->db->prepare("
                UPDATE proveedores.appointments
                SET status = 'cancelled',
                    status_reason     = :reason,
                    status_changed_by = :user_id,
                    status_changed_at = NOW(),
                    cancelled_at      = NOW()
                WHERE id = :id
            ")->execute([
                'reason'  => $reason,
                'user_id' => (int)$user['id'],
                'id'      => $appointmentId,
            ]);

            $this->db->prepare("DELETE FROM proveedores.document_reservations WHERE appointment_id = :id")
                ->execute(['id' => $appointmentId]);

            $this->addEvent($appointmentId, 'cancelled', [
                'reason' => $reason,
                'user'   => $user['username'] ?? '',
            ], (int)$user['id']);

            $this->db->commit();
            return $this->findById($appointmentId);
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function findById(int $appointmentId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                a.*,
                u.username AS created_by_username,
                prov.name  AS provider_name
            FROM proveedores.appointments a
            LEFT JOIN proveedores.users u ON u.id = a.created_by
            LEFT JOIN proveedores.providers prov ON prov.id = a.provider_id
            WHERE a.id = :id
        ");
        $stmt->execute(['id' => $appointmentId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function reservedDocumentIds(string $documentType = 'order'): array
    {
        $stmt = $this->db->prepare("
            SELECT document_id
            FROM proveedores.document_reservations
            WHERE document_type = :type
        ");
        $stmt->execute(['type' => $documentType]);
        $rows = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        return array_values(array_unique(array_map('intval', array_filter($rows, static function ($value) {
            return $value !== null;
        }))));
    }

    protected function addEvent(int $appointmentId, string $eventType, array $payload, int $userId): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO proveedores.appointment_events (appointment_id, event_type, payload, created_by)
            VALUES (:appointment_id, :event_type, :payload, :created_by)
        ");
        $stmt->execute([
            'appointment_id' => $appointmentId,
            'event_type'     => $eventType,
            'payload'        => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'created_by'     => $userId,
        ]);
    }

    /**
     * Guarda:
     * - archivos en appointment_files
     * - renglones en appointment_document_items (status full/partial/over)
     * - mapping proveedor+SKU+unidades en provider_article_units (cuando se puede deducir)
     */

    public function saveOrderDeliveryFromModal(
        int $appointmentId,
        int $orderId,
        array $deliveryItems,
        array $files,
        ?string $comment,
        array $user
    ): void {
        $this->db->beginTransaction();

        try {
            // -----------------------------------------------------------------
            // 0) Buscar (y si no existe, crear) el appointment_document
            // -----------------------------------------------------------------
            $stmt = $this->db->prepare("
            SELECT id
            FROM proveedores.appointment_documents
            WHERE appointment_id = :appointment_id
              AND document_type = 'order'
              AND document_id   = :order_id
            FOR UPDATE
        ");
            $stmt->execute([
                'appointment_id' => $appointmentId,
                'order_id'       => $orderId,
            ]);

            $appointmentDocumentId = $stmt->fetchColumn();

            if (!$appointmentDocumentId) {
                // 0.1) Datos básicos de la cita
                $aStmt = $this->db->prepare("
                SELECT provider_id, delivery_point_code, delivery_point_name
                FROM proveedores.appointments
                WHERE id = :id
                FOR UPDATE
            ");
                $aStmt->execute(['id' => $appointmentId]);
                $appointmentRow = $aStmt->fetch(\PDO::FETCH_ASSOC);

                if (!$appointmentRow) {
                    throw new RuntimeException(
                        'Cita no encontrada al intentar crear el documento de orden.'
                    );
                }

                $providerId        = (int)$appointmentRow['provider_id'];
                $deliveryPointCode = $appointmentRow['delivery_point_code'] ?? null;
                $deliveryPointName = $appointmentRow['delivery_point_name'] ?? null;

                // 0.2) Totales estimados usando los items (si traen costo)
                $requestedTotal = 0.0;
                foreach ($deliveryItems as $item) {
                    $qty  = (float)($item['qty_pedida']   ?? 0);
                    $cost = (float)($item['cost']         ?? 0);
                    $requestedTotal += $qty * $cost;
                }

                // 0.3) Insertar appointment_documents
                $insertDoc = $this->db->prepare("
                INSERT INTO proveedores.appointment_documents (
                    appointment_id,
                    provider_id,
                    document_type,
                    document_id,
                    document_reference,
                    delivery_point_code,
                    delivery_point_name,
                    status,
                    requested_total,
                    invoiced_total,
                    summary
                ) VALUES (
                    :appointment_id,
                    :provider_id,
                    'order',
                    :document_id,
                    :document_reference,
                    :delivery_point_code,
                    :delivery_point_name,
                    'pending',
                    :requested_total,
                    0,
                    :summary
                )
                RETURNING id
            ");

                $insertDoc->execute([
                    'appointment_id'      => $appointmentId,
                    'provider_id'         => $providerId,
                    'document_id'         => $orderId,
                    // mientras no tengas el FOLIO, usamos el ID de compra como referencia
                    'document_reference'  => (string)$orderId,
                    'delivery_point_code' => $deliveryPointCode,
                    'delivery_point_name' => $deliveryPointName,
                    'requested_total'     => $requestedTotal,
                    'summary'             => json_encode(
                        ['auto_created_from_modal' => true],
                        JSON_UNESCAPED_UNICODE
                    ),
                ]);

                $appointmentDocumentId = (int)$insertDoc->fetchColumn();

                // 0.4) Crear la reserva en document_reservations
                $reservationStmt = $this->db->prepare("
                INSERT INTO proveedores.document_reservations (
                    document_type,
                    document_id,
                    provider_id,
                    appointment_id,
                    delivery_point_code
                ) VALUES (
                    'order',
                    :document_id,
                    :provider_id,
                    :appointment_id,
                    :delivery_point_code
                )
            ");

                $reservationStmt->execute([
                    'document_id'         => $orderId,
                    'provider_id'         => $providerId,
                    'appointment_id'      => $appointmentId,
                    'delivery_point_code' => $deliveryPointCode,
                ]);
            } else {
                $appointmentDocumentId = (int)$appointmentDocumentId;
            }

            // -----------------------------------------------------------------
            // 1) Guardar comentario dentro del summary JSON (appointment_documents)
            // -----------------------------------------------------------------
            if ($comment !== null && $comment !== '') {
                $stmtSummary = $this->db->prepare("
                SELECT summary
                FROM proveedores.appointment_documents
                WHERE id = :id
                FOR UPDATE
            ");
                $stmtSummary->execute(['id' => $appointmentDocumentId]);
                $row = $stmtSummary->fetch(\PDO::FETCH_ASSOC);

                $summaryArr = [];
                if ($row && !empty($row['summary'])) {
                    $decoded = json_decode($row['summary'], true);
                    if (is_array($decoded)) {
                        $summaryArr = $decoded;
                    }
                }

                $summaryArr['comment'] = $comment;

                $this->db->prepare("
                UPDATE proveedores.appointment_documents
                SET summary = :summary,
                    updated_at = NOW()
                WHERE id = :id
            ")->execute([
                    'summary' => json_encode($summaryArr, JSON_UNESCAPED_UNICODE),
                    'id'      => $appointmentDocumentId,
                ]);
            }

            // -----------------------------------------------------------------
            // 2) Limpiar items anteriores de esta cita+orden
            //    (tabla real: appointment_order_items)
            // -----------------------------------------------------------------
            $this->db->prepare("
            DELETE FROM proveedores.appointment_order_items
            WHERE appointment_id = :appointment_id
              AND order_id       = :order_id
        ")->execute([
                'appointment_id' => $appointmentId,
                'order_id'       => $orderId,
            ]);

            // -----------------------------------------------------------------
            // 3) Insertar items nuevos en appointment_order_items
            //    (SIN columna unit, se guarda en status_payload)
            // -----------------------------------------------------------------
            if (!empty($deliveryItems)) {
                $insertItem = $this->db->prepare("
                INSERT INTO proveedores.appointment_order_items (
                    appointment_id,
                    order_id,
                    article_id,
                    description,
                    qty_ordered,
                    qty_received,
                    qty_pending,
                    qty_to_deliver,
                    status,
                    comment,
                    status_payload
                ) VALUES (
                    :appointment_id,
                    :order_id,
                    :article_id,
                    :description,
                    :qty_ordered,
                    :qty_received,
                    :qty_pending,
                    :qty_to_deliver,
                    :status,
                    :comment,
                    :status_payload
                )
            ");

                foreach ($deliveryItems as $item) {
                    $articleId    = (int)($item['id_articulo']    ?? 0);
                    $sku          = (string)($item['sku']         ?? '');
                    $unit         = (string)($item['unit']        ?? '');
                    $cost         = (float)($item['cost']         ?? 0);
                    $qtyOrdered   = (float)($item['qty_pedida']   ?? 0);
                    $qtyReceived  = (float)($item['qty_recibida'] ?? 0);
                    $qtyPending   = (float)($item['qty_faltante'] ?? 0);
                    $qtyDeliver   = (float)($item['qty_entregar'] ?? 0);
                    $status       = $item['status']               ?? null;

                    // Guardamos datos extra (sku, unit, cost, etc.) en JSON
                    $statusPayload = [
                        'sku'        => $sku,
                        'unit'       => $unit,
                        'cost'       => $cost,
                        'source'     => 'modal_delivery',
                        'original'   => [
                            'qty_ordered'    => $qtyOrdered,
                            'qty_received'   => $qtyReceived,
                            'qty_pending'    => $qtyPending,
                            'qty_to_deliver' => $qtyDeliver,
                        ],
                    ];

                    $insertItem->execute([
                        'appointment_id' => $appointmentId,
                        'order_id'       => $orderId,
                        'article_id'     => $articleId ?: null,
                        // por ahora usamos SKU como description; luego puedes cambiar a descripción real
                        'description'    => $sku,
                        'qty_ordered'    => $qtyOrdered,
                        'qty_received'   => $qtyReceived,
                        'qty_pending'    => $qtyPending,
                        'qty_to_deliver' => $qtyDeliver,
                        'status'         => $status,
                        'comment'        => $comment,
                        'status_payload' => json_encode($statusPayload, JSON_UNESCAPED_UNICODE),
                    ]);
                }
            }

            // -----------------------------------------------------------------
            // 4) Guardar archivos en appointment_files
            // -----------------------------------------------------------------
            $insertFile = $this->db->prepare("
            INSERT INTO proveedores.appointment_files (
                appointment_document_id,
                file_type,
                storage_path,
                original_name,
                mime_type,
                size_bytes,
                checksum,
                metadata
            ) VALUES (
                :appointment_document_id,
                :file_type,
                :storage_path,
                :original_name,
                :mime_type,
                :size_bytes,
                :checksum,
                :metadata
            )
        ");

            $baseMetadata = [
                'uploaded_by' => $user['username'] ?? null,
                'user_id'     => (int)($user['id'] ?? 0),
                'source'      => 'modal_order_files',
            ];

            foreach ($files['xml'] ?? [] as $xml) {
                $insertFile->execute([
                    'appointment_document_id' => $appointmentDocumentId,
                    'file_type'               => 'xml',
                    'storage_path'            => $xml['storage_path'],
                    'original_name'           => $xml['original_name'],
                    'mime_type'               => $xml['mime_type'] ?? 'text/xml',
                    'size_bytes'              => (int)($xml['size_bytes'] ?? 0),
                    'checksum'                => $xml['checksum'] ?? null,
                    'metadata'                => json_encode($baseMetadata, JSON_UNESCAPED_UNICODE),
                ]);
            }

            foreach ($files['pdf'] ?? [] as $pdf) {
                $insertFile->execute([
                    'appointment_document_id' => $appointmentDocumentId,
                    'file_type'               => 'pdf',
                    'storage_path'            => $pdf['storage_path'],
                    'original_name'           => $pdf['original_name'],
                    'mime_type'               => $pdf['mime_type'] ?? 'application/pdf',
                    'size_bytes'              => (int)($pdf['size_bytes'] ?? 0),
                    'checksum'                => $pdf['checksum'] ?? null,
                    'metadata'                => json_encode($baseMetadata, JSON_UNESCAPED_UNICODE),
                ]);
            }

            // -----------------------------------------------------------------
            // 5) Recalcular totales requested_total / invoiced_total
            // -----------------------------------------------------------------
            $requestedTotal = 0.0;
            $invoicedTotal  = 0.0;

            foreach ($deliveryItems as $item) {
                $qtyOrdered  = (float)($item['qty_pedida']   ?? 0);
                $qtyDeliver  = (float)($item['qty_entregar'] ?? 0);
                $cost        = (float)($item['cost']         ?? 0);

                $requestedTotal += $qtyOrdered * $cost;
                $invoicedTotal  += $qtyDeliver * $cost;
            }

            $this->db->prepare("
            UPDATE proveedores.appointment_documents
            SET requested_total = :requested_total,
                invoiced_total  = :invoiced_total,
                updated_at      = NOW()
            WHERE id = :id
        ")->execute([
                'requested_total' => $requestedTotal,
                'invoiced_total'  => $invoicedTotal,
                'id'              => $appointmentDocumentId,
            ]);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }


    /**
     * Lee los XML recién guardados y deduce cómo factura el proveedor cada SKU:
     * - provider_id + sku + sale_unit (Unidad/ClaveUnidad en XML)
     * - order_unit (UNIDAD_CORTA en la orden)
     * - factor = (cantidad pedida) / (cantidad XML)
     *
     * Solo se genera mapping cuando el status del renglón es 'full' para evitar ruido.
     */
    protected function updateProviderArticleUnitsFromXml(
        int $providerId,
        array $deliveryItems,
        array $savedXmlFiles
    ): void {
        // 1) Map de items por SKU
        $itemsBySku = [];
        foreach ($deliveryItems as $item) {
            $sku = trim((string)($item['sku'] ?? ''));
            if ($sku === '') {
                continue;
            }
            $itemsBySku[$sku] = [
                'article_id'   => (int)($item['id_articulo'] ?? 0) ?: null,
                'unit'         => (string)($item['unit'] ?? ''),
                'qty_pedida'   => (float)($item['qty_pedida'] ?? 0),
                'qty_entregar' => (float)($item['qty_entregar'] ?? 0),
                'status'       => (string)($item['status'] ?? ''),
            ];
        }

        if (empty($itemsBySku)) {
            return;
        }

        // 2) Agregar conceptos de todos los XML: sku => total_qty, unidad
        $aggregated = []; // sku => ['qty' => ..., 'unidad' => ...]
        $projectRoot = dirname(__DIR__, 2);

        foreach ($savedXmlFiles as $file) {
            $relPath = $file['path'] ?? '';
            if ($relPath === '') {
                continue;
            }
            $fullPath = $projectRoot . '/' . ltrim($relPath, '/');
            if (!is_file($fullPath)) {
                continue;
            }

            $xmlContent = @file_get_contents($fullPath);
            if ($xmlContent === false) {
                continue;
            }

            try {
                $xml = new \SimpleXMLElement($xmlContent);
            } catch (\Throwable $e) {
                continue;
            }

            $namespaces = $xml->getNamespaces(true);
            $cfdiNs     = $namespaces['cfdi'] ?? null;

            $conceptosNodes = [];
            if ($cfdiNs) {
                $cfdi       = $xml->children($cfdiNs);
                $conceptos  = $cfdi->Conceptos ?? null;
                if ($conceptos) {
                    foreach ($conceptos->Concepto as $c) {
                        $conceptosNodes[] = $c;
                    }
                }
            }

            if (empty($conceptosNodes) && isset($xml->Conceptos)) {
                foreach ($xml->Conceptos->Concepto as $c) {
                    $conceptosNodes[] = $c;
                }
            }

            if (empty($conceptosNodes)) {
                if (isset($xml->Concepto)) {
                    foreach ($xml->Concepto as $c) {
                        $conceptosNodes[] = $c;
                    }
                }
            }

            foreach ($conceptosNodes as $c) {
                $attrs = $c->attributes();
                $sku   = trim((string)($attrs['NoIdentificacion'] ?? ''));
                $qty   = (float)($attrs['Cantidad'] ?? 0);
                $unit  = (string)($attrs['Unidad'] ?? ($attrs['ClaveUnidad'] ?? ''));

                if ($sku === '' || $qty <= 0) {
                    continue;
                }

                if (!isset($aggregated[$sku])) {
                    $aggregated[$sku] = [
                        'qty'   => 0.0,
                        'unit'  => $unit,
                    ];
                }
                $aggregated[$sku]['qty'] += $qty;
                if ($aggregated[$sku]['unit'] === '' && $unit !== '') {
                    $aggregated[$sku]['unit'] = $unit;
                }
            }
        }

        if (empty($aggregated)) {
            return;
        }

        // 3) Por cada SKU de la orden, generar mapping si status=full
        foreach ($itemsBySku as $sku => $item) {
            if (!isset($aggregated[$sku])) {
                continue;
            }
            $xmlQty   = (float)$aggregated[$sku]['qty'];
            $saleUnit = (string)$aggregated[$sku]['unit'] ?: null;

            if ($xmlQty <= 0 || !$saleUnit) {
                continue;
            }

            $orderUnit = (string)$item['unit'] ?: null;
            if (!$orderUnit) {
                continue;
            }

            $status = $item['status'] ?? '';
            if ($status !== 'full') {
                continue;
            }

            $baseQty = (float)$item['qty_pedida'];
            if ($baseQty <= 0) {
                $baseQty = (float)$item['qty_entregar'];
            }
            if ($baseQty <= 0) {
                continue;
            }

            $factor = $baseQty / $xmlQty;
            if ($factor <= 0) {
                continue;
            }

            $this->upsertProviderArticleUnit(
                $providerId,
                $item['article_id'],
                $sku,
                $saleUnit,
                $orderUnit,
                $factor
            );
        }
    }

    /**
     * UPSERT de provider_article_units
     */
    protected function upsertProviderArticleUnit(
        int $providerId,
        ?int $articleId,
        string $sku,
        string $saleUnit,
        string $orderUnit,
        float $factor
    ): void {
        if ($providerId <= 0 || $sku === '' || $saleUnit === '' || $orderUnit === '' || $factor <= 0) {
            return;
        }

        $stmt = $this->db->prepare("
            INSERT INTO proveedores.provider_article_units (
                provider_id,
                article_id,
                sku,
                sale_unit,
                order_unit,
                factor,
                last_seen_at
            ) VALUES (
                :provider_id,
                :article_id,
                :sku,
                :sale_unit,
                :order_unit,
                :factor,
                NOW()
            )
            ON CONFLICT (provider_id, sku, sale_unit, order_unit)
            DO UPDATE SET
                factor      = EXCLUDED.factor,
                last_seen_at= NOW()
        ");

        $stmt->execute([
            'provider_id' => $providerId,
            'article_id'  => $articleId,
            'sku'         => $sku,
            'sale_unit'   => $saleUnit,
            'order_unit'  => $orderUnit,
            'factor'      => $factor,
        ]);
    }
}
