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
                $conditions[]     = 'a.provider_id = ANY(:provider_ids::bigint[])';
                $params['provider_ids'] = '{' . implode(',', array_map('intval', $providerIds)) . '}';
            } else {
                $conditions[]     = 'a.created_by = :created_by';
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

        // Validar horario permitido 08:00–15:00
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
                    'in_process'
                )
                RETURNING id
            ");

            $insert->execute([
                'provider_id'        => (int)$providerId,
                'created_by'         => (int)$user['id'],
                'delivery_point_code'=> $payload['delivery_point_code'] ?? null,
                'delivery_point_name'=> $payload['delivery_point_name'] ?? null,
                'delivery_point_type'=> $payload['delivery_point_type'] ?? null,
                'delivery_address'   => $payload['delivery_address'] ?? null,
                'appointment_date'   => $date,
                'slot_start'         => $slotStart,
                'slot_end'           => $slotEnd,
            ]);

            $appointmentId = (int)$insert->fetchColumn();
            $folio         = sprintf('CITA-%s-%05d', date('Ymd'), $appointmentId);

            $this->db->prepare("UPDATE proveedores.appointments SET folio = :folio WHERE id = :id")
                ->execute(['folio' => $folio, 'id' => $appointmentId]);

            // Insert de documentos
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

            foreach ($documents as $doc) {
                $docType    = $doc['document_type'] ?? 'order';
                $docId      = isset($doc['document_id']) ? (int)$doc['document_id'] : null;
                $reference  = trim((string)($doc['document_reference'] ?? ''));

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
                    status_reason    = :reason,
                    status_changed_by= :user_id,
                    status_changed_at= NOW(),
                    cancelled_at     = NOW()
                WHERE id = :id
            ")->execute([
                'reason' => $reason,
                'user_id'=> (int)$user['id'],
                'id'     => $appointmentId,
            ]);

            // Liberar reservas de documentos
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
}
