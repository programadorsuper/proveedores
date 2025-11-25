<?php

class Inventory
{
    protected \PDO $db;

    public function __construct()
    {
        $this->db = Database::pgsql();
    }

    public function getCoverage(int $providerId, array $filters = []): array
    {
        $sql = "
            SELECT store_id, product_id, on_hand, on_order, days_of_inventory
            FROM proveedores.vw_inventory_cover
            WHERE provider_id = :pid
            ORDER BY store_id, product_id
            LIMIT :limit
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue('pid', $providerId, \PDO::PARAM_INT);
        $stmt->bindValue('limit', min(5000, max(100, (int)($filters['limit'] ?? 1000))), \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function getBreaks(int $providerId, array $filters = []): array
    {
        $threshold = isset($filters['threshold']) ? (float)$filters['threshold'] : 3.0;
        $sql = "
            SELECT store_id, product_id, on_hand, on_order, days_of_inventory
            FROM proveedores.vw_inventory_cover
            WHERE provider_id = :pid
              AND (on_hand <= 0 OR days_of_inventory IS NULL OR days_of_inventory <= :threshold)
            ORDER BY store_id, product_id
            LIMIT :limit
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue('pid', $providerId, \PDO::PARAM_INT);
        $stmt->bindValue('threshold', $threshold);
        $stmt->bindValue('limit', min(5000, max(100, (int)($filters['limit'] ?? 1000))), \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }
}
