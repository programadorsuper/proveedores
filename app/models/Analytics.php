<?php

class Analytics
{
    protected \PDO $db;

    public function __construct()
    {
        $this->db = Database::pgsql();
    }

    public function aggregateSellout(
        int $providerId,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        ?int $storeId = null,
        array $filters = []
    ): array {
        $providerIds = $this->resolveExternalIds($providerId);
        if (empty($providerIds)) {
            return $this->emptySelloutAggregate();
        }

        $params = [
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
        ];

        $providerCondition = $this->buildInCondition('v.id_proveedor', $providerIds, $params, 'prov');
        $storeCondition = $this->optionalEqualsCondition('v.id_tienda', $storeId, $params, 'store');
        $articleCondition = $this->buildArticleFilter('a', $filters, $params, 'so');

        $sql = "
            SELECT
                COUNT(*) FILTER (WHERE {$storeCondition}) AS rows_count,
                SUM(CASE WHEN {$storeCondition}
                         THEN COALESCE(v.venta_bruta,0) - COALESCE(v.descuento,0) - COALESCE(v.devolucion,0)
                         ELSE 0 END) AS net_value,
                SUM(CASE WHEN {$storeCondition}
                         THEN COALESCE(v.venta_bruta_pza,0) - COALESCE(v.devolucion_pza,0)
                         ELSE 0 END) AS net_units,
                SUM(CASE WHEN {$storeCondition}
                         THEN COALESCE(v.venta_costo_promedio,0) - COALESCE(v.devolucion_costo_promedio,0)
                         ELSE 0 END) AS net_cogs,
                SUM(CASE WHEN {$storeCondition}
                         THEN COALESCE(v.devolucion,0)
                         ELSE 0 END) AS returns_value,
                COUNT(*) FILTER (WHERE {$storeCondition} AND COALESCE(v.devolucion,0) > 0) AS returns_count
            FROM public.ventas v
            JOIN proveedores.providers pr ON pr.external_id = v.id_proveedor
            JOIN public.articulo a ON a.id_articulo = v.id_articulo\n            LEFT JOIN public.tienda t ON t.id_tienda = v.id_tienda
            WHERE {$providerCondition}
              AND {$articleCondition}
              AND v.fecha BETWEEN :start AND :end
        ";

        $row = $this->fetchRow($sql, $params);

        return [
            'value' => (float)($row['net_value'] ?? 0),
            'units' => (float)($row['net_units'] ?? 0),
            'cogs' => (float)($row['net_cogs'] ?? 0),
            'returns_value' => (float)($row['returns_value'] ?? 0),
            'returns_count' => (int)($row['returns_count'] ?? 0),
            'rows' => (int)($row['rows_count'] ?? 0),
        ];
    }

    public function aggregateSellin(
        int $providerId,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        ?int $storeId = null,
        array $filters = []
    ): array {
        $providerIds = $this->resolveExternalIds($providerId);
        if (empty($providerIds)) {
            return [
                'value' => 0.0,
                'units' => 0.0,
                'rows' => 0,
            ];
        }

        $params = [
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
        ];

        $providerCondition = $this->buildInCondition('m.id_proveedor', $providerIds, $params, 'prov');
        $storeCondition = $this->optionalEqualsCondition('m.id_tienda', $storeId, $params, 'store');
        $articleCondition = $this->buildArticleFilter('a', $filters, $params, 'si');
        $entriesExpr = 'COALESCE(m.entradas_sell_in, m.entradas, 0)';

        $sql = "
            SELECT
                COUNT(*) FILTER (WHERE {$storeCondition}) AS rows_count,
                SUM(CASE WHEN {$storeCondition}
                         THEN {$entriesExpr} * COALESCE(m.costo,0)
                         ELSE 0 END) AS value_total,
                SUM(CASE WHEN {$storeCondition}
                         THEN {$entriesExpr}
                         ELSE 0 END) AS units_total
            FROM public.movimientos m
            JOIN proveedores.providers pr ON pr.external_id = m.id_proveedor
            JOIN public.articulo a ON a.id_articulo = m.id_articulo
            WHERE {$providerCondition}
              AND {$articleCondition}
              AND m.fecha BETWEEN :start AND :end
        ";

        $row = $this->fetchRow($sql, $params);

        return [
            'value' => (float)($row['value_total'] ?? 0),
            'units' => (float)($row['units_total'] ?? 0),
            'rows' => (int)($row['rows_count'] ?? 0),
        ];
    }

    public function selloutTimeseries(int $providerId, array $filters): array
    {
        $providerIds = $this->resolveExternalIds($providerId);
        if (empty($providerIds)) {
            return ['categories' => [], 'series' => [['name' => 'Sell-out', 'data' => []]], 'points' => []];
        }

        $start = $filters['start_date'];
        $end = $filters['end_date'];
        $groupBy = strtolower((string)($filters['group_by'] ?? 'month'));
        $storeId = $filters['store_id'] ?? null;

        [$bucketExpr, $format] = $this->resolveGroupBy('v.fecha', $groupBy);

        $params = [
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
        ];

        $providerCondition = $this->buildInCondition('v.id_proveedor', $providerIds, $params, 'prov');
        $storeCondition = $this->optionalEqualsCondition('v.id_tienda', $storeId, $params, 'store');
        $articleCondition = $this->buildArticleFilter('a', $filters, $params, 'sotp');

        $sql = "
            SELECT
                {$bucketExpr} AS bucket,
                TO_CHAR({$bucketExpr}, '{$format}') AS label,
                SUM(CASE WHEN {$storeCondition}
                         THEN COALESCE(v.venta_bruta,0) - COALESCE(v.descuento,0) - COALESCE(v.devolucion,0)
                         ELSE 0 END) AS value_total
            FROM public.ventas v
            JOIN proveedores.providers pr ON pr.external_id = v.id_proveedor
            JOIN public.articulo a ON a.id_articulo = v.id_articulo\n            LEFT JOIN public.tienda t ON t.id_tienda = v.id_tienda
            WHERE {$providerCondition}
              AND {$articleCondition}
              AND v.fecha BETWEEN :start AND :end
            GROUP BY bucket, label
            ORDER BY bucket
        ";

        $stmt = $this->db->prepare($sql);
        $this->bindAll($stmt, $params);
        $stmt->execute();

        $labels = [];
        $data = [];
        $points = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $value = (float)($row['value_total'] ?? 0);
            $labels[] = $row['label'];
            $data[] = round($value, 2);
            $points[] = [
                'bucket' => $row['bucket'],
                'label' => $row['label'],
                'value' => $value,
            ];
        }

        return [
            'categories' => $labels,
            'series' => [['name' => 'Sell-out', 'data' => $data]],
            'points' => $points,
        ];
    }

    public function sellinTimeseries(int $providerId, array $filters): array
    {
        $providerIds = $this->resolveExternalIds($providerId);
        if (empty($providerIds)) {
            return ['categories' => [], 'series' => [['name' => 'Sell-in', 'data' => []]], 'points' => []];
        }

        $start = $filters['start_date'];
        $end = $filters['end_date'];
        $groupBy = strtolower((string)($filters['group_by'] ?? 'month'));
        $storeId = $filters['store_id'] ?? null;

        [$bucketExpr, $format] = $this->resolveGroupBy('m.fecha', $groupBy);

        $params = [
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
        ];

        $providerCondition = $this->buildInCondition('m.id_proveedor', $providerIds, $params, 'prov');
        $storeCondition = $this->optionalEqualsCondition('m.id_tienda', $storeId, $params, 'store');
        $articleCondition = $this->buildArticleFilter('a', $filters, $params, 'sitp');
        $entriesExpr = 'COALESCE(m.entradas_sell_in, m.entradas, 0)';

        $sql = "
            SELECT
                {$bucketExpr} AS bucket,
                TO_CHAR({$bucketExpr}, '{$format}') AS label,
                SUM(CASE WHEN {$storeCondition}
                         THEN {$entriesExpr} * COALESCE(m.costo,0)
                         ELSE 0 END) AS value_total
            FROM public.movimientos m
            JOIN proveedores.providers pr ON pr.external_id = m.id_proveedor
            JOIN public.articulo a ON a.id_articulo = m.id_articulo
            WHERE {$providerCondition}
              AND {$articleCondition}
              AND m.fecha BETWEEN :start AND :end
            GROUP BY bucket, label
            ORDER BY bucket
        ";

        $stmt = $this->db->prepare($sql);
        $this->bindAll($stmt, $params);
        $stmt->execute();

        $labels = [];
        $data = [];
        $points = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $value = (float)($row['value_total'] ?? 0);
            $labels[] = $row['label'];
            $data[] = round($value, 2);
            $points[] = [
                'bucket' => $row['bucket'],
                'label' => $row['label'],
                'value' => $value,
            ];
        }

        return [
            'categories' => $labels,
            'series' => [['name' => 'Sell-in', 'data' => $data]],
            'points' => $points,
        ];
    }

    public function selloutProducts(int $providerId, array $filters, int $limit = 200): array
    {
        $providerIds = $this->resolveExternalIds($providerId);
        if (empty($providerIds)) {
            return [];
        }

        $currentStart = $filters['start_date'];
        $currentEnd = $filters['end_date'];
        $compareStart = $filters['compare_start'];
        $compareEnd = $filters['compare_end'];
        $yearStart = $filters['year_start'];
        $yearCompareStart = $filters['year_compare_start'];
        $storeId = $filters['store_id'] ?? null;

        $params = [
            'current_start' => $currentStart->format('Y-m-d H:i:s'),
            'current_end' => $currentEnd->format('Y-m-d H:i:s'),
            'compare_start' => $compareStart->format('Y-m-d H:i:s'),
            'compare_end' => $compareEnd->format('Y-m-d H:i:s'),
            'year_start' => $yearStart->format('Y-m-d H:i:s'),
            'year_compare_start' => $yearCompareStart->format('Y-m-d H:i:s'),
            'limit' => $limit,
        ];

        $providerCondition = $this->buildInCondition('v.id_proveedor', $providerIds, $params, 'prov');
        $storeCondition = $this->optionalEqualsCondition('v.id_tienda', $storeId, $params, 'store');
        $articleCondition = $this->buildArticleFilter('a', $filters, $params, 'sop');

        $sql = "
            SELECT
                a.id_articulo,
                a.descripcion,
                a.codigo,
                a.sku,
                a.codigo_barras,
                CASE 
                    WHEN a.descontinuado = true THEN 'Baja'
                    WHEN a.descontinuado = false
                         AND a.fecha_alta >= DATE_TRUNC('month', NOW()) - INTERVAL '3 months' THEN 'Nuevo'
                    ELSE 'Vigente'
                END AS status,
                SUM(CASE WHEN {$storeCondition}
                           AND v.fecha BETWEEN :current_start AND :current_end
                         THEN COALESCE(v.venta_bruta_pza,0) ELSE 0 END) AS units_current,
                SUM(CASE WHEN {$storeCondition}
                           AND v.fecha BETWEEN :current_start AND :current_end
                         THEN COALESCE(v.venta_bruta,0) ELSE 0 END) AS value_current,
                SUM(CASE WHEN {$storeCondition}
                           AND v.fecha BETWEEN :current_start AND :current_end
                         THEN COALESCE(v.venta_ultimo_costo,0) ELSE 0 END) AS cost_current,

                SUM(CASE WHEN {$storeCondition}
                           AND v.fecha BETWEEN :year_start AND :current_end
                         THEN COALESCE(v.venta_bruta_pza,0) ELSE 0 END) AS units_ytd,
                SUM(CASE WHEN {$storeCondition}
                           AND v.fecha BETWEEN :year_start AND :current_end
                         THEN COALESCE(v.venta_bruta,0) ELSE 0 END) AS value_ytd,

                SUM(CASE WHEN {$storeCondition}
                           AND v.fecha BETWEEN :compare_start AND :compare_end
                         THEN COALESCE(v.venta_bruta_pza,0) ELSE 0 END) AS units_compare,
                SUM(CASE WHEN {$storeCondition}
                           AND v.fecha BETWEEN :compare_start AND :compare_end
                         THEN COALESCE(v.venta_bruta,0) ELSE 0 END) AS value_compare,

                SUM(CASE WHEN {$storeCondition}
                           AND v.fecha BETWEEN :year_compare_start AND :compare_end
                         THEN COALESCE(v.venta_bruta_pza,0) ELSE 0 END) AS units_ytd_compare,
                SUM(CASE WHEN {$storeCondition}
                           AND v.fecha BETWEEN :year_compare_start AND :compare_end
                         THEN COALESCE(v.venta_bruta,0) ELSE 0 END) AS value_ytd_compare
            FROM public.articulo a
            LEFT JOIN public.ventas v
                   ON v.id_articulo = a.id_articulo
                  AND {$providerCondition}
            WHERE {$this->buildArticleFilter('a', $filters, $params, 'sop_self')}
            GROUP BY a.id_articulo, a.descripcion, a.codigo, a.sku, a.codigo_barras, a.descontinuado, a.fecha_alta
            HAVING
                SUM(CASE WHEN {$storeCondition}
                           AND v.fecha BETWEEN :current_start AND :current_end
                         THEN COALESCE(v.venta_bruta,0) ELSE 0 END) <> 0
                OR SUM(CASE WHEN {$storeCondition}
                           AND v.fecha BETWEEN :compare_start AND :compare_end
                         THEN COALESCE(v.venta_bruta,0) ELSE 0 END) <> 0
                OR SUM(CASE WHEN {$storeCondition}
                           AND v.fecha BETWEEN :year_start AND :current_end
                         THEN COALESCE(v.venta_bruta,0) ELSE 0 END) <> 0
            ORDER BY value_current DESC, a.descripcion ASC
            LIMIT :limit
        ";

        $stmt = $this->db->prepare($sql);
        $this->bindAll($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function sellinProducts(int $providerId, array $filters, int $limit = 200): array
    {
        $providerIds = $this->resolveExternalIds($providerId);
        if (empty($providerIds)) {
            return [];
        }

        $currentStart = $filters['start_date'];
        $currentEnd = $filters['end_date'];
        $compareStart = $filters['compare_start'];
        $compareEnd = $filters['compare_end'];
        $yearStart = $filters['year_start'];
        $yearCompareStart = $filters['year_compare_start'];
        $storeId = $filters['store_id'] ?? null;
        $entriesExpr = 'COALESCE(m.entradas_sell_in, m.entradas, 0)';

        $params = [
            'current_start' => $currentStart->format('Y-m-d H:i:s'),
            'current_end' => $currentEnd->format('Y-m-d H:i:s'),
            'compare_start' => $compareStart->format('Y-m-d H:i:s'),
            'compare_end' => $compareEnd->format('Y-m-d H:i:s'),
            'year_start' => $yearStart->format('Y-m-d H:i:s'),
            'year_compare_start' => $yearCompareStart->format('Y-m-d H:i:s'),
            'limit' => $limit,
        ];

        $providerCondition = $this->buildInCondition('m.id_proveedor', $providerIds, $params, 'prov');
        $storeCondition = $this->optionalEqualsCondition('m.id_tienda', $storeId, $params, 'store');
        $articleCondition = $this->buildArticleFilter('a', $filters, $params, 'sip');

        $sql = "
            SELECT
                a.id_articulo,
                a.descripcion,
                a.codigo,
                a.sku,
                a.codigo_barras,
                CASE 
                    WHEN a.descontinuado = true THEN 'Baja'
                    WHEN a.descontinuado = false
                         AND a.fecha_alta >= DATE_TRUNC('month', NOW()) - INTERVAL '3 months' THEN 'Nuevo'
                    ELSE 'Vigente'
                END AS status,
                SUM(CASE WHEN {$storeCondition}
                           AND m.fecha BETWEEN :current_start AND :current_end
                         THEN {$entriesExpr}
                         ELSE 0 END) AS units_current,
                SUM(CASE WHEN {$storeCondition}
                           AND m.fecha BETWEEN :current_start AND :current_end
                         THEN {$entriesExpr} * COALESCE(m.costo,0)
                         ELSE 0 END) AS value_current,

                SUM(CASE WHEN {$storeCondition}
                           AND m.fecha BETWEEN :year_start AND :current_end
                         THEN {$entriesExpr}
                         ELSE 0 END) AS units_ytd,
                SUM(CASE WHEN {$storeCondition}
                           AND m.fecha BETWEEN :year_start AND :current_end
                         THEN {$entriesExpr} * COALESCE(m.costo,0)
                         ELSE 0 END) AS value_ytd,

                SUM(CASE WHEN {$storeCondition}
                           AND m.fecha BETWEEN :compare_start AND :compare_end
                         THEN {$entriesExpr}
                         ELSE 0 END) AS units_compare,
                SUM(CASE WHEN {$storeCondition}
                           AND m.fecha BETWEEN :compare_start AND :compare_end
                         THEN {$entriesExpr} * COALESCE(m.costo,0)
                         ELSE 0 END) AS value_compare,

                SUM(CASE WHEN {$storeCondition}
                           AND m.fecha BETWEEN :year_compare_start AND :compare_end
                         THEN {$entriesExpr}
                         ELSE 0 END) AS units_ytd_compare,
                SUM(CASE WHEN {$storeCondition}
                           AND m.fecha BETWEEN :year_compare_start AND :compare_end
                         THEN {$entriesExpr} * COALESCE(m.costo,0)
                         ELSE 0 END) AS value_ytd_compare
            FROM public.articulo a
            LEFT JOIN public.movimientos m
                   ON m.id_articulo = a.id_articulo
                  AND {$providerCondition}
            WHERE {$this->buildArticleFilter('a', $filters, $params, 'sip_self')}
            GROUP BY a.id_articulo, a.descripcion, a.codigo, a.sku, a.codigo_barras, a.descontinuado, a.fecha_alta
            HAVING
                SUM(CASE WHEN {$storeCondition}
                           AND m.fecha BETWEEN :current_start AND :current_end
                         THEN {$entriesExpr} ELSE 0 END) <> 0
                OR SUM(CASE WHEN {$storeCondition}
                           AND m.fecha BETWEEN :compare_start AND :compare_end
                         THEN {$entriesExpr} ELSE 0 END) <> 0
                OR SUM(CASE WHEN {$storeCondition}
                           AND m.fecha BETWEEN :year_start AND :current_end
                         THEN {$entriesExpr} ELSE 0 END) <> 0
            ORDER BY value_current DESC, a.descripcion ASC
            LIMIT :limit
        ";

        $stmt = $this->db->prepare($sql);
        $this->bindAll($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function topCustomersSummary(int $providerId, array $filters, int $limit = 10): array
    {
        $providerIds = $this->resolveExternalIds($providerId);
        if (empty($providerIds)) {
            return [];
        }

        $start = $filters['start_date'];
        $end = $filters['end_date'];
        $storeId = $filters['store_id'] ?? null;

        $params = [
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
            'limit' => $limit,
        ];

        $providerCondition = $this->buildInCondition('v.id_proveedor', $providerIds, $params, 'prov');
        $storeCondition = $this->optionalEqualsCondition('v.id_tienda', $storeId, $params, 'store');
        $articleCondition = $this->buildArticleFilter('a', $filters, $params, 'cust');

        $sql = "
            SELECT
                v.id_cliente AS customer_id,
                COALESCE(cli.razon_social, cli.nombre, 'Cliente ' || v.id_cliente) AS customer_name,
                SUM(CASE WHEN {$storeCondition}
                         THEN COALESCE(v.venta_bruta,0) - COALESCE(v.descuento,0) - COALESCE(v.devolucion,0)
                         ELSE 0 END) AS value_total,
                SUM(CASE WHEN {$storeCondition}
                         THEN COALESCE(v.venta_bruta_pza,0) - COALESCE(v.devolucion_pza,0)
                         ELSE 0 END) AS units_total,
                COUNT(*) FILTER (WHERE {$storeCondition}) AS tickets
            FROM public.ventas v
            JOIN proveedores.providers pr ON pr.external_id = v.id_proveedor
            JOIN public.articulo a ON a.id_articulo = v.id_articulo\n            LEFT JOIN public.tienda t ON t.id_tienda = v.id_tienda
            LEFT JOIN public.cliente cli ON cli.id_cliente = v.id_cliente
            WHERE {$providerCondition}
              AND {$articleCondition}
              AND v.fecha BETWEEN :start AND :end
            GROUP BY v.id_cliente, customer_name
            ORDER BY value_total DESC
            LIMIT :limit
        ";

        $stmt = $this->db->prepare($sql);
        $this->bindAll($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function topStoresSummary(int $providerId, array $filters, int $limit = 10): array
    {
        $providerIds = $this->resolveExternalIds($providerId);
        if (empty($providerIds)) {
            return [];
        }

        $start = $filters['start_date'];
        $end = $filters['end_date'];
        $storeId = $filters['store_id'] ?? null;

        $params = [
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $end->format('Y-m-d H:i:s'),
            'limit' => $limit,
        ];

        $providerCondition = $this->buildInCondition('v.id_proveedor', $providerIds, $params, 'prov');
        $storeCondition = $this->optionalEqualsCondition('v.id_tienda', $storeId, $params, 'store');
        $articleCondition = $this->buildArticleFilter('a', $filters, $params, 'store');

        $sql = "
            SELECT
                v.id_tienda AS id_tienda,
                SUM(CASE WHEN {$storeCondition}
                         THEN COALESCE(v.venta_bruta,0) - COALESCE(v.descuento,0) - COALESCE(v.devolucion,0)
                         ELSE 0 END) AS value_total,
                SUM(CASE WHEN {$storeCondition}
                         THEN COALESCE(v.venta_bruta_pza,0) - COALESCE(v.devolucion_pza,0)
                         ELSE 0 END) AS units_total
            FROM public.ventas v
            JOIN proveedores.providers pr ON pr.external_id = v.id_proveedor
            JOIN public.articulo a ON a.id_articulo = v.id_articulo\n            LEFT JOIN public.tienda t ON t.id_tienda = v.id_tienda
            WHERE {$providerCondition}
              AND {$articleCondition}
              AND v.fecha BETWEEN :start AND :end
            GROUP BY v.id_tienda
            ORDER BY value_total DESC
            LIMIT :limit
        ";

        $stmt = $this->db->prepare($sql);
        $this->bindAll($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function listStores(int $providerId, array $filters = []): array
    {
        $providerIds = $this->resolveExternalIds($providerId);
        if (empty($providerIds)) {
            return [];
        }

        $params = [];
        $providerConditionVentas = $this->buildInCondition('v.id_proveedor', $providerIds, $params, 'vp');
        $providerConditionMov = $this->buildInCondition('m.id_proveedor', $providerIds, $params, 'mp');
        $articleFilterVentas = $this->buildArticleFilter('av', $filters, $params, 'lsv');
        $articleFilterMov = $this->buildArticleFilter('am', $filters, $params, 'lsm');

        $sql = "
            SELECT DISTINCT store_id
            FROM (
                SELECT v.id_tienda AS store_id
                FROM public.ventas v
                JOIN public.articulo av ON av.id_articulo = v.id_articulo
                WHERE {$providerConditionVentas}
                  AND {$articleFilterVentas}
                  AND v.id_tienda IS NOT NULL

                UNION

                SELECT m.id_tienda AS store_id
                FROM public.movimientos m
                JOIN public.articulo am ON am.id_articulo = m.id_articulo
                WHERE {$providerConditionMov}
                  AND {$articleFilterMov}
                  AND m.id_tienda IS NOT NULL
            ) t
            ORDER BY store_id
            LIMIT 200
        ";

        $stmt = $this->db->prepare($sql);
        $this->bindAll($stmt, $params);
        $stmt->execute();

        $stores = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $id = (int)($row['store_id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $label = trim((string)($row['store_name'] ?? ''));
            if ($label === '') {
                $label = 'Tienda #' . $id;
            }
            $stores[] = [
                'id' => $id,
                'label' => $label,
            ];
        }

        return $stores;
    }

    public function productSuggestions(
        int $providerId,
        ?string $term = null,
        int $limit = 50,
        array $filters = []
    ): array {
        $providerIds = $this->resolveExternalIds($providerId);
        if (empty($providerIds)) {
            return [];
        }

        $params = [
            'limit' => $limit,
        ];

        $providerCondition = $this->buildInCondition('a.id_proveedor', $providerIds, $params, 'prov');

        $sql = "
            SELECT
                a.id_articulo,
                a.codigo,
                a.sku,
                a.codigo_barras,
                a.descripcion
            FROM public.articulo a
            WHERE {$providerCondition}
        ";

        if (empty($filters['include_inactive'])) {
            $sql .= " AND a.descontinuado = false";
        }

        if ($term !== null && $term !== '') {
            $params['term_like'] = '%' . str_replace(' ', '%', trim($term)) . '%';
            $sql .= "
              AND (
                    TRIM(a.codigo) ILIKE :term_like
                 OR TRIM(a.sku) ILIKE :term_like
                 OR TRIM(a.codigo_barras) ILIKE :term_like
                 OR TRIM(a.descripcion) ILIKE :term_like
              )";
        }

        $sql .= "
            ORDER BY a.descripcion
            LIMIT :limit
        ";

        $stmt = $this->db->prepare($sql);
        $this->bindAll($stmt, $params);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    protected function resolveExternalIds(int $providerId): array
    {
        $stmt = $this->db->prepare("
            SELECT DISTINCT external_id
            FROM proveedores.providers
            WHERE id = :id AND external_id IS NOT NULL
        ");
        $stmt->execute(['id' => $providerId]);
        $ids = array_filter(array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN)));

        return array_values(array_unique(array_filter($ids, static fn($value) => $value > 0)));
    }

    protected function buildInCondition(string $column, array $values, array &$params, string $prefix): string
    {
        $placeholders = [];
        foreach (array_values($values) as $index => $value) {
            $key = $prefix . $index;
            $params[$key] = $value;
            $placeholders[] = ':' . $key;
        }

        return $placeholders ? $column . ' IN (' . implode(',', $placeholders) . ')' : '1=0';
    }

    protected function optionalEqualsCondition(string $column, $value, array &$params, string $key): string
    {
        if ($value === null || $value === '' || $value === 'all') {
            return 'TRUE';
        }
        $params[$key] = $value;
        return $column . ' = :' . $key;
    }

    protected function buildArticleFilter(string $alias, array $filters, array &$params, string $prefix): string
    {
        $clauses = [];

        if (empty($filters['include_inactive'])) {
            $clauses[] = "{$alias}.descontinuado = false";
        }

        if (!empty($filters['query'])) {
            $key = $prefix . '_query';
            $params[$key] = '%' . str_replace(' ', '%', trim((string)$filters['query'])) . '%';
            $clauses[] = "(TRIM({$alias}.codigo) ILIKE :{$key}
                OR TRIM({$alias}.sku) ILIKE :{$key}
                OR TRIM({$alias}.codigo_barras) ILIKE :{$key}
                OR TRIM({$alias}.descripcion) ILIKE :{$key})";
        }

        return $clauses ? implode(' AND ', $clauses) : 'TRUE';
    }

    protected function resolveGroupBy(string $column, string $groupBy): array
    {
        return match ($groupBy) {
            'day' => ["date_trunc('day', {$column})", 'YYYY-MM-DD'],
            'week' => ["date_trunc('week', {$column})", 'IYYY-\"W\"IW'],
            default => ["date_trunc('month', {$column})", 'YYYY-MM'],
        };
    }

    protected function fetchRow(string $sql, array $params): array
    {
        $stmt = $this->db->prepare($sql);
        $this->bindAll($stmt, $params);
        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row !== false ? $row : [];
    }

    protected function bindAll(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $param = ':' . ltrim($key, ':');
            if ($value === null) {
                $stmt->bindValue($param, null, \PDO::PARAM_NULL);
            } elseif (is_int($value) || (is_string($value) && ctype_digit($value))) {
                $stmt->bindValue($param, (int)$value, \PDO::PARAM_INT);
            } elseif (is_float($value)) {
                $stmt->bindValue($param, $value);
            } else {
                $stmt->bindValue($param, $value);
            }
        }
    }

    protected function emptySelloutAggregate(): array
    {
        return [
            'value' => 0.0,
            'units' => 0.0,
            'cogs' => 0.0,
            'returns_value' => 0.0,
            'returns_count' => 0,
            'rows' => 0,
        ];
    }
}
