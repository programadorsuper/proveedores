<?php

class Providers
{
    protected \PDO $db;

    public function __construct()
    {
        $this->db = Database::pgsql();
    }

    public function listForUser(array $sessionUser): array
    {
        $isSuperAdmin = !empty($sessionUser['is_super_admin']);
        $userId = (int)($sessionUser['id'] ?? 0);

        if ($isSuperAdmin) {
            $sql = "
                SELECT p.id,
                       p.external_id,
                       p.slug,
                       p.name,
                       p.status,
                       p.activation_date,
                       p.created_at,
                       p.updated_at,
                       pb.numero_proveedor,
                       pb.razon_social,
                       NULL::date AS fecha_baja,
                       (
                           SELECT COUNT(DISTINCT up.user_id)
                           FROM proveedores.user_providers up
                           WHERE up.provider_id = p.id
                       ) AS linked_users
                FROM proveedores.providers p
                LEFT JOIN public.proveedores pb ON pb.id_proveedor = p.external_id
                ORDER BY p.external_id NULLS LAST, p.name
            ";
            $stmt = $this->db->query($sql);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } else {
            $sql = "
                WITH scope AS (
                    SELECT provider_id
                    FROM proveedores.user_providers
                    WHERE user_id = :user
                    UNION
                    SELECT provider_id
                    FROM proveedores.users
                    WHERE id = :user
                )
                SELECT DISTINCT p.id,
                                p.external_id,
                                p.slug,
                                p.name,
                                p.status,
                                p.activation_date,
                                p.created_at,
                                p.updated_at,
                                pb.numero_proveedor,
                                pb.razon_social,
                                NULL::date AS fecha_baja,
                                (
                                    SELECT COUNT(DISTINCT up_inner.user_id)
                                    FROM proveedores.user_providers up_inner
                                    WHERE up_inner.provider_id = p.id
                                ) AS linked_users
                FROM scope s
                JOIN proveedores.providers p ON p.id = s.provider_id
                LEFT JOIN public.proveedores pb ON pb.id_proveedor = p.external_id
                ORDER BY p.external_id NULLS LAST, p.name
            ";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user' => $userId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        }

        return array_map(function (array $row): array {
            $row['id'] = (int)$row['id'];
            $row['external_id'] = $row['external_id'] !== null ? (int)$row['external_id'] : null;
            $row['linked_users'] = (int)($row['linked_users'] ?? 0);
            $row['numero_proveedor'] = $row['numero_proveedor'] ?? null;
            $row['razon_social'] = $row['razon_social'] ?? null;
            $row['fecha_baja'] = null;
            return $row;
        }, $rows);
    }
}
