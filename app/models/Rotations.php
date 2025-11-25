<?php

class Rotations
{
    protected \PDO $db;

    public function __construct()
    {
        $this->db = Database::pgsql();
    }

    public function getMonthly(int $providerId, array $filters = []): array
    {
        $sql = "
            SELECT month, turnover
            FROM proveedores.vw_turnover_monthly
            WHERE provider_id = :pid
            ORDER BY month DESC
            LIMIT :limit
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue('pid', $providerId, \PDO::PARAM_INT);
        $stmt->bindValue('limit', min(60, max(12, (int)($filters['limit'] ?? 24))), \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return array_map(static function (array $row): array {
            return [
                'month' => isset($row['month']) ? (new \DateTimeImmutable($row['month']))->format('Y-m') : '',
                'turnover' => (float)($row['turnover'] ?? 0),
            ];
        }, $rows);
    }
}
