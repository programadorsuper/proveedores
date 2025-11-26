<?php

class Orders
{
    protected \PDO $db;

    public function __construct()
    {
        // Usa la conexión Firebird centralizada
        $this->db = Database::firebird();
    }

    /**
     * Obtener una orden por ID (una sola).
     *
     * @param int $idCompra
     * @return array|null
     */
    public function getOrdenById(int $idCompra): ?array
    {
        $sql = "
            SELECT 
                C.ID_COMPRA,
                C.SERIE,
                C.ESTADO,
                C.ID_TEMPORADA,
                C.ID_PROVEEDOR,
                TEM.ALIAS,
                TEM.TEMPORADA AS TEMPORADA,
                C.FOLIO,
                C.FECHA,
                C.IMPORTE,
                C.IMPUESTOS_TRASLADADOS,
                C.DESCUENTO_1,
                C.DESCUENTO_2,
                C.DESCUENTO_3,
                C.TOTAL,
                C.DIAS_CREDITO,
                C.CONDICIONES,
                C.COMENTARIO,
                C.ID_TIENDA_CONSIGNADA,

                CASE 
                    WHEN C.ID_TIENDA_CONSIGNADA = T.ID_TIENDA THEN
                        TRIM(T.NOMBRE_CORTO) || ' - ' ||
                        TRIM(T.CALLE)        || ', ' ||
                        TRIM(T.COLONIA)      || ', ' ||
                        TRIM(T.MUNICIPIO)    || ', ' ||
                        TRIM(T.CODIGO_POSTAL)
                    ELSE
                        'CENDIS - AV.TEJOCOTES, COL SAN MARTIN OBISPO, 54769'
                END AS LUGAR_ENTREGA,

                CP.RAZON_SOCIAL,
                CP.NUMERO_PROVEEDOR,
                CP.CALLE,
                CP.NUMERO_EXTERIOR,
                CP.NUMERO_INTERIOR,
                CP.COLONIA,
                CP.MUNICIPIO,
                CP.CIUDAD,
                CP.CODIGO_POSTAL,
                CP.TELEFONO_OFICINA,
                CP.ATENCION,
                T.NOMBRE_CORTO,
                U.NOMBRE || ' ' || U.PATERNO || ' ' || U.MATERNO  AS CAPTURA,
                UA.NOMBRE || ' ' || UA.PATERNO || ' ' || UA.MATERNO AS AUTORIZA
            FROM TBL_COMPRAS C
            INNER JOIN TBL_USUARIO U
                ON C.ID_USUARIO_CAPTURA = U.ID_USUARIO
            INNER JOIN TBL_USUARIO UA
                ON C.ID_USUARIO_AUTORIZA = UA.ID_USUARIO
            INNER JOIN TBL_COMPRAS_PROVEEDORES CP
                ON C.ID_PROVEEDOR = CP.ID_PROVEEDOR
            INNER JOIN TBL_TIENDA T
                ON C.ID_TIENDA = T.ID_TIENDA
            LEFT JOIN TBL_TEMPORADA TEM
                ON C.ID_TEMPORADA = TEM.ID_TEMPORADA
            WHERE C.ID_COMPRA = ?
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idCompra]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Obtener varias órdenes por un arreglo de IDs (IN (...)).
     *
     * @param int[] $idsCompra
     * @return array
     */
    public function getOrdenesByIds(array $idsCompra): array
    {
        if (empty($idsCompra)) {
            return [];
        }

        // Placeholders para el IN
        $placeholders = implode(', ', array_fill(0, count($idsCompra), '?'));

        $sql = "
            SELECT 
                C.ID_COMPRA,
                C.SERIE,
                C.ESTADO,
                C.FOLIO,
                C.FECHA,
                C.IMPORTE,
                C.DIAS_CREDITO,
                C.ID_TIENDA_CONSIGNADA,
                CASE 
                    WHEN C.ID_TIENDA_CONSIGNADA = T.ID_TIENDA THEN
                        TRIM(T.NOMBRE_CORTO) || ' - ' ||
                        TRIM(T.CALLE)        || ', ' ||
                        TRIM(T.COLONIA)      || ', ' ||
                        TRIM(T.MUNICIPIO)    || ', ' ||
                        TRIM(T.CODIGO_POSTAL)
                    ELSE
                        'CENDIS - AV.TEJOCOTES, COL SAN MARTIN OBISPO, 54769'
                END AS LUGAR_ENTREGA,
                CP.RAZON_SOCIAL,
                CP.NUMERO_PROVEEDOR,
                CP.CALLE,
                CP.NUMERO_EXTERIOR,
                CP.NUMERO_INTERIOR,
                CP.COLONIA,
                CP.MUNICIPIO,
                CP.CIUDAD,
                CP.CODIGO_POSTAL,
                CP.TELEFONO_OFICINA,
                CP.ATENCION,
                T.NOMBRE_CORTO,
                U.NOMBRE || ' ' || U.PATERNO || ' ' || U.MATERNO  AS CAPTURA,
                UA.NOMBRE || ' ' || UA.PATERNO || ' ' || UA.MATERNO AS AUTORIZA
            FROM TBL_COMPRAS C
            INNER JOIN TBL_USUARIO U
                ON C.ID_USUARIO_CAPTURA = U.ID_USUARIO
            INNER JOIN TBL_USUARIO UA
                ON C.ID_USUARIO_AUTORIZA = UA.ID_USUARIO
            INNER JOIN TBL_COMPRAS_PROVEEDORES CP
                ON C.ID_PROVEEDOR = CP.ID_PROVEEDOR
            INNER JOIN TBL_TIENDA T
                ON C.ID_TIENDA = T.ID_TIENDA
            WHERE C.ID_COMPRA IN ($placeholders)
        ";

        $stmt = $this->db->prepare($sql);

        // Bindeamos todos los IDs como INT
        foreach ($idsCompra as $i => $id) {
            $stmt->bindValue($i + 1, (int)$id, \PDO::PARAM_INT);
        }

        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getNewPurchaseOrders(array $providerIds = [], array $options = []): array
    {
        $page = max(1, (int)($options['page'] ?? 1));
        $perPage = (int)($options['per_page'] ?? 25);
        $perPage = max(5, min($perPage, 100));

        $search = trim((string)($options['search'] ?? ''));
        $days = (int)($options['days'] ?? 30);
        $days = max(1, min($days, 120));

        $fromDate = (new \DateTimeImmutable())
            ->modify(sprintf('-%d days', $days))
            ->format('Y-m-d 00:00:00');

        $offset = ($page - 1) * $perPage;

        // Filtros base (incluyendo lo que tenía la función vieja)
        $filters = [
            "C.FECHA >= :from_date",                 // rango de fechas
            "C.ID_TIPO_DOCUMENTO IN (20,21,12,13)",  // tipos de documento
            "C.CANCELADA = 0",                       // no canceladas
            "C.ID_USUARIO_AUTORIZA IS NOT NULL",     // autorizadas
            "C.ID_USUARIO_AUTORIZA <> 0",            // autorizadas (no 0)
            "ENT.ID_ORDEN_ENTRADA IS NULL"          // SIN entrada de almacén = ORDEN NUEVA
        ];

        $params = [
            ':from_date' => $fromDate,
        ];

        // Normalizar IDs de proveedor
        $providerIds = array_values(array_unique(array_filter(
            array_map('intval', $providerIds),
            static fn($id) => $id > 0
        )));

        if (!empty($providerIds)) {
            $placeholders = [];
            foreach ($providerIds as $index => $providerId) {
                $key = ':provider_' . $index;
                $placeholders[] = $key;
                $params[$key] = $providerId;
            }
            $filters[] = 'C.ID_PROVEEDOR IN (' . implode(', ', $placeholders) . ')';
        }

        // Búsqueda SUPER por LIKE
        // 🔍 Búsqueda SUPER amplia
        // Búsqueda SUPER amplia
        if ($search !== '') {
            $searchClauses = [];

            // 1) Normalizar y limitar longitud para evitar desmadres con Firebird
            $upperSearch = strtoupper($search);
            if (strlen($upperSearch) > 90) {
                $upperSearch = substr($upperSearch, 0, 90);
            }
            $needle = '%' . $upperSearch . '%';

            $i = 1;

            $addLike = function (string $expressionBase) use (&$i, &$params, $needle, &$searchClauses) {
                $paramName = ':search_text_' . $i++;
                $params[$paramName] = $needle;
                $searchClauses[] = str_replace(':search_text', $paramName, $expressionBase);
            };

            // 🔢 Campos numéricos como texto (largo cómodo)
            $addLike("CAST(C.FOLIO AS VARCHAR(120)) LIKE :search_text");
            $addLike("CAST(C.ID_COMPRA AS VARCHAR(120)) LIKE :search_text");

            // 👤 Proveedor
            $addLike("UPPER(CAST(CP.RAZON_SOCIAL AS VARCHAR(120))) LIKE :search_text");
            $addLike("UPPER(CAST(CP.NUMERO_PROVEEDOR AS VARCHAR(120))) LIKE :search_text");

            // 🏬 Tienda
            $addLike("UPPER(CAST(T.NOMBRE_CORTO AS VARCHAR(120))) LIKE :search_text");

            // 🗓 Temporada / alias
            $addLike("UPPER(CAST(TEM.ALIAS AS VARCHAR(120))) LIKE :search_text");
            $addLike("UPPER(CAST(TEM.TEMPORADA AS VARCHAR(120))) LIKE :search_text");

            // Serie
            $addLike("UPPER(CAST(C.SERIE AS VARCHAR(120))) LIKE :search_text");

            // 💡 Combinaciones tipo “folio visual” que ve el proveedor
            // ALIAS-FOLIO
            $addLike("UPPER(CAST(TEM.ALIAS || '-' || CAST(C.FOLIO AS VARCHAR(120)) AS VARCHAR(240))) LIKE :search_text");
            // ALIAS FOLIO
            $addLike("UPPER(CAST(TEM.ALIAS || ' ' || CAST(C.FOLIO AS VARCHAR(120)) AS VARCHAR(240))) LIKE :search_text");
            // ALIAS-FOLIO TEMPORADA
            $addLike("UPPER(CAST(TEM.ALIAS || '-' || CAST(C.FOLIO AS VARCHAR(120)) || ' ' || COALESCE(TEM.TEMPORADA, '') AS VARCHAR(360))) LIKE :search_text");

            // 📍 Partes de lugar de entrega (tienda)
            $addLike("UPPER(CAST(T.CALLE AS VARCHAR(120))) LIKE :search_text");
            $addLike("UPPER(CAST(T.COLONIA AS VARCHAR(120))) LIKE :search_text");
            $addLike("UPPER(CAST(T.MUNICIPIO AS VARCHAR(120))) LIKE :search_text");
            $addLike("UPPER(CAST(T.CODIGO_POSTAL AS VARCHAR(120))) LIKE :search_text");

            // 🏢 Partes de dirección del proveedor
            $addLike("UPPER(CAST(CP.CALLE AS VARCHAR(120))) LIKE :search_text");
            $addLike("UPPER(CAST(CP.COLONIA AS VARCHAR(120))) LIKE :search_text");
            $addLike("UPPER(CAST(CP.MUNICIPIO AS VARCHAR(120))) LIKE :search_text");
            $addLike("UPPER(CAST(CP.CIUDAD AS VARCHAR(120))) LIKE :search_text");
            $addLike("UPPER(CAST(CP.CODIGO_POSTAL AS VARCHAR(120))) LIKE :search_text");

            $filters[] = '(' . implode(' OR ', $searchClauses) . ')';
        }

        $filterSql = $filters ? 'WHERE ' . implode(' AND ', $filters) : '';

        // IMPORTANTE: incluir TBL_ENTRADA_ALMACEN ENT como en la función vieja
        $baseFrom = "
                FROM TBL_COMPRAS C
                LEFT JOIN TBL_ENTRADA_ALMACEN ENT
                    ON C.ID_COMPRA = ENT.ID_COMPRA
                INNER JOIN TBL_USUARIO U
                    ON C.ID_USUARIO_CAPTURA = U.ID_USUARIO
                INNER JOIN TBL_USUARIO UA
                    ON C.ID_USUARIO_AUTORIZA = UA.ID_USUARIO
                INNER JOIN TBL_COMPRAS_PROVEEDORES CP
                    ON C.ID_PROVEEDOR = CP.ID_PROVEEDOR
                LEFT JOIN TBL_TIENDA T
                    ON C.ID_TIENDA = T.ID_TIENDA
                LEFT JOIN TBL_TEMPORADA TEM
                    ON C.ID_TEMPORADA = TEM.ID_TEMPORADA
            ";

        $countSql = "SELECT COUNT(*) AS TOTAL {$baseFrom} {$filterSql}";

        $sql = "
            SELECT FIRST {$perPage} SKIP {$offset}
                C.ID_COMPRA,
                C.SERIE,
                C.ESTADO,
                C.ID_TEMPORADA,
                TEM.ALIAS,
                TEM.TEMPORADA,
                C.FOLIO,
                C.FECHA,
                C.IMPORTE,
                C.IMPUESTOS_TRASLADADOS,
                C.IMPUESTOS_TRASLADADOS_2,
                C.DESCUENTO_1,
                C.DESCUENTO_2,
                C.DESCUENTO_3,
                C.TOTAL,
                C.DIAS_CREDITO,
                C.ID_TIENDA_CONSIGNADA,
                C.ID_PROVEEDOR,
                CASE 
                    WHEN C.ID_TIENDA_CONSIGNADA = T.ID_TIENDA THEN
                        TRIM(T.NOMBRE_CORTO) || ' - ' ||
                        TRIM(T.CALLE)        || ', ' ||
                        TRIM(T.COLONIA)      || ', ' ||
                        TRIM(T.MUNICIPIO)    || ', ' ||
                        TRIM(T.CODIGO_POSTAL)
                    ELSE
                        'CENDIS - AV.TEJOCOTES, COL SAN MARTIN OBISPO, 54769'
                END AS LUGAR_ENTREGA,
                CP.RAZON_SOCIAL,
                CP.NUMERO_PROVEEDOR,
                CP.CALLE,
                CP.NUMERO_EXTERIOR,
                CP.NUMERO_INTERIOR,
                CP.COLONIA,
                CP.MUNICIPIO,
                CP.CIUDAD,
                CP.CODIGO_POSTAL,
                CP.TELEFONO_OFICINA,
                CP.ATENCION,
                T.NOMBRE_CORTO,
                U.NOMBRE || ' ' || U.PATERNO || ' ' || U.MATERNO  AS CAPTURA,
                UA.NOMBRE || ' ' || UA.PATERNO || ' ' || UA.MATERNO AS AUTORIZA
            {$baseFrom}
            {$filterSql}
            ORDER BY C.FECHA DESC, C.ID_COMPRA DESC
        ";

        // Conteo total
        $countStmt = $this->db->prepare($countSql);
        $this->bindFilterParams($countStmt, $params);
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        // Datos paginados
        $stmt = $this->db->prepare($sql);
        $this->bindFilterParams($stmt, $params);
        $stmt->execute();

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Normalización
        $normalized = array_map(function (array $row): array {
            $fecha = $row['FECHA'] ?? null;
            if ($fecha instanceof \DateTimeInterface) {
                $fecha = $fecha->format('Y-m-d H:i:s');
            } elseif (is_string($fecha)) {
                $fecha = trim($fecha);
            } else {
                $fecha = null;
            }

            $mapFloat = static function ($value): float {
                return $value !== null ? (float)$value : 0.0;
            };

            return [
                'ID_COMPRA' => (int)$row['ID_COMPRA'],
                'SERIE' => trim((string)$row['SERIE']),
                'ESTADO' => trim((string)$row['ESTADO']),
                'ID_TEMPORADA' => (int)($row['ID_TEMPORADA'] ?? 0),
                'ALIAS' => $row['ALIAS'] ?? '',
                'TEMPORADA' => $row['TEMPORADA'] ?? '',
                'FOLIO' => (int)$row['FOLIO'],
                'FECHA' => $fecha,
                'IMPORTE' => $mapFloat($row['IMPORTE'] ?? null),
                'IMPUESTOS_TRASLADADOS' => $mapFloat($row['IMPUESTOS_TRASLADADOS'] ?? null),
                'IMPUESTOS_TRASLADADOS_2' => $mapFloat($row['IMPUESTOS_TRASLADADOS_2'] ?? null),
                'DESCUENTO_1' => $mapFloat($row['DESCUENTO_1'] ?? null),
                'DESCUENTO_2' => $mapFloat($row['DESCUENTO_2'] ?? null),
                'DESCUENTO_3' => $mapFloat($row['DESCUENTO_3'] ?? null),
                'TOTAL' => $mapFloat($row['TOTAL'] ?? null),
                'DIAS_CREDITO' => (int)$row['DIAS_CREDITO'],
                'ID_TIENDA_CONSIGNADA' => (int)($row['ID_TIENDA_CONSIGNADA'] ?? 0),
                'ID_PROVEEDOR' => (int)$row['ID_PROVEEDOR'],
                'LUGAR_ENTREGA' => trim((string)$row['LUGAR_ENTREGA']),
                'RAZON_SOCIAL' => trim((string)$row['RAZON_SOCIAL']),
                'NUMERO_PROVEEDOR' => trim((string)$row['NUMERO_PROVEEDOR']),
                'CALLE' => trim((string)$row['CALLE']),
                'NUMERO_EXTERIOR' => trim((string)$row['NUMERO_EXTERIOR']),
                'NUMERO_INTERIOR' => trim((string)$row['NUMERO_INTERIOR']),
                'COLONIA' => trim((string)$row['COLONIA']),
                'MUNICIPIO' => trim((string)$row['MUNICIPIO']),
                'CIUDAD' => trim((string)$row['CIUDAD']),
                'CODIGO_POSTAL' => trim((string)$row['CODIGO_POSTAL']),
                'TELEFONO_OFICINA' => trim((string)$row['TELEFONO_OFICINA']),
                'ATENCION' => trim((string)$row['ATENCION']),
                'NOMBRE_CORTO' => trim((string)$row['NOMBRE_CORTO']),
                'CAPTURA' => trim((string)$row['CAPTURA']),
                'AUTORIZA' => trim((string)$row['AUTORIZA']),
            ];
        }, $rows);

        return [
            'data' => $normalized,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => (int)ceil($total / $perPage),
                'from_date' => $fromDate,
            ],
        ];
    }

    // GET /api/orders-nuevas/check
    // En tu modelo de órdenes/compras
    public function checkNewPurchaseOrders(
        array $providerIds = [],
        int $sinceId = 0,
        int $days = 30
    ): array {
        // Limitar days
        $days = max(1, min($days, 120));

        $fromDate = (new \DateTimeImmutable())
            ->modify(sprintf('-%d days', $days))
            ->format('Y-m-d 00:00:00');

        // Normalizar proveedores
        $providerIds = array_values(array_unique(array_filter(
            array_map('intval', $providerIds),
            static fn($id) => $id > 0
        )));

        $filters = [
            "C.FECHA >= :from_date",
            "C.ID_TIPO_DOCUMENTO IN (20,21,12,13)",
            "C.CANCELADA = 0",
            "C.ID_USUARIO_AUTORIZA IS NOT NULL",
            "C.ID_USUARIO_AUTORIZA <> 0",
            "ENT.ID_ORDEN_ENTRADA IS NULL",
        ];

        $params = [
            ':from_date' => $fromDate,
        ];

        if (!empty($providerIds)) {
            $placeholders = [];
            foreach ($providerIds as $index => $providerId) {
                $key = ':provider_' . $index;
                $placeholders[] = $key;
                $params[$key] = $providerId;
            }
            $filters[] = 'C.ID_PROVEEDOR IN (' . implode(', ', $placeholders) . ')';
        }

        $whereSql = $filters ? 'WHERE ' . implode(' AND ', $filters) : '';

        // 👇 SIEMPRE tomamos la compra MAS RECIENTE según los mismos filtros
        $sql = "
        SELECT FIRST 1
            C.ID_COMPRA,
            C.FECHA
        FROM TBL_COMPRAS C
        LEFT JOIN TBL_ENTRADA_ALMACEN ENT
            ON C.ID_COMPRA = ENT.ID_COMPRA
        {$whereSql}
        ORDER BY C.FECHA DESC, C.ID_COMPRA DESC
    ";

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();
        $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;

        // Si NO hay ninguna orden en ese rango → no hay nuevas
        if ($row === null) {
            return [
                'has_new'   => false,
                'latest_id' => $sinceId,
                'row'       => null,
            ];
        }

        $latestIdDb = (int)$row['ID_COMPRA'];

        // Primera vez (sinceId = 0): podemos decidir que hay nuevas
        if ($sinceId === 0) {
            return [
                'has_new'   => true,
                'latest_id' => $latestIdDb,
                'row'       => $row,
            ];
        }

        // 👇 AQUÍ LA REGLA CLARA:
        // - si el último ID en BD es MAYOR que el que tengo → hay nuevas
        // - si es igual → NO hay nuevas
        $hasNew = $latestIdDb !== $sinceId;

        return [
            'has_new'   => $hasNew,
            'latest_id' => $latestIdDb,
            'row'       => $row,
        ];
    }

    public function getBackorders(array $providerIds = [], array $options = []): array
    {
        $months = (int)($options['months'] ?? 2);
        $months = $months > 0 ? $months : 2;
        $fromDate = (new \DateTimeImmutable())->modify(sprintf('-%d months', $months))->format('Y-m-d');
        $search = trim((string)($options['search'] ?? ''));

        $conditions = [
            "C.ID_TIPO_DOCUMENTO IN (20,21,12,13)",
            "C.CANCELADA = 0",
            "CAST(LEFT(C.FECHA, 10) AS DATE) >= :from_date"
        ];
        $params = [':from_date' => $fromDate];

        if (!empty($providerIds)) {
            $placeholders = [];
            foreach ($providerIds as $index => $providerId) {
                $key = ':prov_' . $index;
                $placeholders[] = $key;
                $params[$key] = (int)$providerId;
            }
            $conditions[] = 'C.ID_PROVEEDOR IN (' . implode(', ', $placeholders) . ')';
        }

        if ($search !== '') {
            $conditions[] = '(CP.RAZON_SOCIAL CONTAINING :search OR CAST(C.FOLIO AS VARCHAR(50)) LIKE :searchExact OR T.NOMBRE_CORTO CONTAINING :search)';
            $params[':search'] = $search;
            $params[':searchExact'] = '%' . $search . '%';
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);

        $sql = "
            SELECT
                C.ID_COMPRA,
                C.ID_TEMPORADA,
                TEM.ALIAS,
                TEM.TEMPORADA,
                C.SERIE,
                C.FOLIO,
                C.FECHA,
                CP.RAZON_SOCIAL,
                CP.NUMERO_PROVEEDOR,
                T.NOMBRE_CORTO,
                SUM(CD.CANTIDAD_SOLICITADA) AS TOTAL_SOLICITADO,
                SUM(COALESCE(EDET.CANTIDAD_RECIBIDA, 0)) AS TOTAL_RECIBIDO
            FROM TBL_COMPRAS C
            INNER JOIN TBL_COMPRAS_DETALLE CD ON CD.ID_COMPRA = C.ID_COMPRA
            INNER JOIN TBL_COMPRAS_PROVEEDORES CP ON CP.ID_PROVEEDOR = C.ID_PROVEEDOR
            INNER JOIN TBL_TIENDA T ON T.ID_TIENDA = C.ID_TIENDA
            LEFT JOIN TBL_TEMPORADA TEM ON TEM.ID_TEMPORADA = C.ID_TEMPORADA
            LEFT JOIN TBL_ENTRADA_ALMACEN E ON E.ID_COMPRA = C.ID_COMPRA
            LEFT JOIN TBL_ENTRADA_ALMACEN_DETALLE EDET ON EDET.ID_ORDEN_ENTRADA = E.ID_ORDEN_ENTRADA
                AND EDET.ID_ARTICULO = CD.ID_ARTICULO
            {$where}
            GROUP BY
                C.ID_COMPRA,
                C.ID_TEMPORADA,
                TEM.ALIAS,
                TEM.TEMPORADA,
                C.SERIE,
                C.FOLIO,
                C.FECHA,
                CP.RAZON_SOCIAL,
                CP.NUMERO_PROVEEDOR,
                T.NOMBRE_CORTO
            HAVING SUM(COALESCE(EDET.CANTIDAD_RECIBIDA, 0)) < SUM(CD.CANTIDAD_SOLICITADA)
            ORDER BY C.FECHA DESC
        ";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return array_map(static function (array $row): array {
            $ordered = (float)($row['TOTAL_SOLICITADO'] ?? 0);
            $received = (float)($row['TOTAL_RECIBIDO'] ?? 0);
            $pending = max($ordered - $received, 0);
            $percent = $ordered > 0 ? round(($received / $ordered) * 100, 2) : 0;

            $row['TOTAL_SOLICITADO'] = $ordered;
            $row['TOTAL_RECIBIDO'] = $received;
            $row['PENDING_TOTAL'] = $pending;
            $row['PERCENT_RECEIVED'] = $percent;
            return $row;
        }, $rows);
    }

    public function getNewOrdersForAppointments(array $providerIds = [], array $filters = [], array $excludedIds = []): array
    {
        $options = [
            'page' => 1,
            'per_page' => (int)($filters['limit'] ?? 250),
            'search' => $filters['search'] ?? '',
            'days' => (int)($filters['days'] ?? 60),
        ];

        $result = $this->getNewPurchaseOrders($providerIds, $options);
        $orders = $result['data'] ?? [];

        if (!empty($excludedIds)) {
            $excludedIds = array_map('intval', $excludedIds);
            $orders = array_filter($orders, static function ($order) use ($excludedIds) {
                return !in_array((int)($order['ID_COMPRA'] ?? 0), $excludedIds, true);
            });
        }

        $deliveryPoint = trim((string)($filters['delivery_point'] ?? ''));
        if ($deliveryPoint !== '') {
            $orders = array_filter($orders, static function ($order) use ($deliveryPoint) {
                $name = strtoupper((string)($order['NOMBRE_CORTO'] ?? ''));
                $needle = strtoupper($deliveryPoint);
                return str_contains($name, $needle);
            });
        }

        $aliasType = strtolower((string)($filters['alias_type'] ?? ''));
        if ($aliasType === 'special') {
            $orders = array_filter($orders, static function ($order) {
                return strtoupper((string)($order['ALIAS'] ?? '')) === 'S';
            });
        } elseif ($aliasType === 'normal') {
            $orders = array_filter($orders, static function ($order) {
                return strtoupper((string)($order['ALIAS'] ?? '')) !== 'S';
            });
        }

        return array_values($orders);
    }

    /**
     * Detalle de una orden (renglones / partidas).
     *
     * @param int $idCompra
     * @return array
     */
    public function detallesByOrden(int $idCompra): array
    {
        $sql = "
            SELECT 
                A.ID_ARTICULO,
                A.CODIGO,
                A.DESCRIPCION,
                A.SKU,
                A.CODIGO_BARRAS,
                A.PESO,
                A.ALTO,
                A.ANCHO,
                A.LARGO,
                CD.COSTO,
                CD.CANTIDAD_SOLICITADA,
                AU.UNIDAD_CORTA
            FROM TBL_COMPRAS_DETALLE CD
            LEFT JOIN TBL_ARTICULO A
                ON CD.ID_ARTICULO = A.ID_ARTICULO
            LEFT JOIN TBL_ARTICULO_UNIDADES AU
                ON CD.ID_UNIDAD_BASE = AU.ID_UNIDAD
            WHERE CD.ID_COMPRA = ?
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idCompra]);

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getEntradasAlmacen(int $idCompra): array
    {
        $sql = "
            SELECT ID_ORDEN_ENTRADA, ID_COMPRA, FECHA, COMENTARIO
            FROM TBL_ENTRADA_ALMACEN
            WHERE ID_COMPRA = ?
            ORDER BY FECHA DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idCompra]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function getEntradasAlmacenDetalles(int $entradaId): array
    {
        $sql = "
            SELECT 
                D.ID_ARTICULO,
                D.CANTIDAD_RECIBIDA,
                A.SKU,
                A.DESCRIPCION
            FROM TBL_ENTRADA_ALMACEN_DETALLE D
            LEFT JOIN TBL_ARTICULO A ON A.ID_ARTICULO = D.ID_ARTICULO
            WHERE D.ID_ORDEN_ENTRADA = ?
            ORDER BY A.DESCRIPCION
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$entradaId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    public function getReceivedQuantities(int $idCompra): array
    {
        $sql = "
            SELECT 
                D.ID_ARTICULO,
                SUM(D.CANTIDAD_RECIBIDA) AS TOTAL_RECIBIDO
            FROM TBL_ENTRADA_ALMACEN_DETALLE D
            INNER JOIN TBL_ENTRADA_ALMACEN E ON E.ID_ORDEN_ENTRADA = D.ID_ORDEN_ENTRADA
            WHERE E.ID_COMPRA = ?
            GROUP BY D.ID_ARTICULO
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idCompra]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $map = [];
        foreach ($rows as $row) {
            $map[(int)$row['ID_ARTICULO']] = (float)$row['TOTAL_RECIBIDO'];
        }

        return $map;
    }

    protected function bindFilterParams(\PDOStatement $stmt, array $params): void
    {
        foreach ($params as $key => $value) {
            $paramType = is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
            $stmt->bindValue($key, $value, $paramType);
        }
    }
    public function getNewOrdersForAppointmentsByStoreSeason(
        array $providerIds,
        array $filters,
        array $excludedIds = [],
        bool $isSuperAdmin = false
    ): array {
        $storeId = (int)($filters['store_id'] ?? 0);
        if ($storeId <= 0) {
            return [];
        }

        $seasonType = (string)($filters['season_type'] ?? '');

        // 2 meses atrás
        $fromDate = (new \DateTimeImmutable('-2 months'))->format('Y-m-d');

        $conditions = [
            "COM.ID_TIPO_DOCUMENTO IN (20,21,12,13)",
            "COM.CANCELADA = 0",
            "COM.ID_USUARIO_AUTORIZA IS NOT NULL",
            "COM.ID_USUARIO_AUTORIZA <> 0",
            "CAST(LEFT(COM.FECHA, 10) AS DATE) >= :from_date",
            "ENT.ID_ORDEN_ENTRADA IS NULL",
            "COALESCE(COM.ID_TIENDA_CONSIGNADA, 0) = :store_id",
        ];
        $params = [
            ':from_date' => $fromDate,
            ':store_id'  => $storeId,
        ];

        // Temporada especial vs normal
        if ($seasonType === 'special') {
            $conditions[] = "COM.ID_TEMPORADA = 3";
        } elseif ($seasonType === 'normal') {
            $conditions[] = "(COM.ID_TEMPORADA IS NULL OR COM.ID_TEMPORADA <> 3)";
        }

        // Filtrar por proveedor si no es super admin
        $providerIds = array_values(array_unique(array_map('intval', $providerIds)));
        if (!$isSuperAdmin && !empty($providerIds)) {
            $provPlaceholders = [];
            foreach ($providerIds as $idx => $provId) {
                $key = ':prov_' . $idx;
                $provPlaceholders[] = $key;
                $params[$key] = $provId;
            }
            $conditions[] = 'COM.ID_PROVEEDOR IN (' . implode(', ', $provPlaceholders) . ')';
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);

        $sql = "
            SELECT
                COM.ID_COMPRA,
                COM.SERIE,
                COM.ESTADO,
                COM.ID_TEMPORADA,
                TEM.ALIAS,
                TEM.TEMPORADA,
                COM.FOLIO,
                COM.FECHA,
                COM.IMPORTE,
                COM.IMPUESTOS_TRASLADADOS,
                COM.IMPUESTOS_TRASLADADOS_2,
                COM.DESCUENTO_1,
                COM.DESCUENTO_2,
                COM.DESCUENTO_3,
                COM.TOTAL,
                COM.DIAS_CREDITO,
                COALESCE(COM.ID_TIENDA_CONSIGNADA, 0) AS ID_TIENDA_CONSIGNADA,
                COM.ID_PROVEEDOR,
                CASE
                    WHEN COM.ID_TIENDA_CONSIGNADA = TIE.ID_TIENDA THEN
                        TRIM(TIE.NOMBRE_CORTO) || ' - ' ||
                        TRIM(TIE.CALLE)        || ', ' ||
                        TRIM(TIE.COLONIA)      || ', ' ||
                        TRIM(TIE.MUNICIPIO)    || ', ' ||
                        TRIM(TIE.CODIGO_POSTAL)
                    ELSE
                        'CENDIS - AV.TEJOCOTES, COL SAN MARTIN OBISPO, 54769'
                END AS LUGAR_ENTREGA,
                CP.RAZON_SOCIAL,
                CP.NUMERO_PROVEEDOR,
                CP.CALLE,
                CP.NUMERO_EXTERIOR,
                CP.NUMERO_INTERIOR,
                CP.COLONIA,
                CP.MUNICIPIO,
                CP.CIUDAD,
                CP.CODIGO_POSTAL,
                CP.TELEFONO_OFICINA,
                CP.ATENCION,
                TIE.NOMBRE_CORTO,
                U.NOMBRE  || ' ' || U.PATERNO  || ' ' || U.MATERNO  AS CAPTURA,
                UA.NOMBRE || ' ' || UA.PATERNO || ' ' || UA.MATERNO AS AUTORIZA
            FROM TBL_COMPRAS COM
            LEFT JOIN TBL_ENTRADA_ALMACEN ENT
                ON COM.ID_COMPRA = ENT.ID_COMPRA
            INNER JOIN TBL_COMPRAS_PROVEEDORES CP
                ON COM.ID_PROVEEDOR = CP.ID_PROVEEDOR
            LEFT JOIN TBL_TIENDA TIE
                ON COM.ID_TIENDA = TIE.ID_TIENDA
            LEFT JOIN TBL_TEMPORADA TEM
                ON COM.ID_TEMPORADA = TEM.ID_TEMPORADA
            INNER JOIN TBL_USUARIO U
                ON COM.ID_USUARIO_CAPTURA = U.ID_USUARIO
            INNER JOIN TBL_USUARIO UA
                ON COM.ID_USUARIO_AUTORIZA = UA.ID_USUARIO
            $where
            ORDER BY COM.FECHA DESC, COM.ID_COMPRA DESC
        ";

        $stmt = $this->db->prepare($sql);
        $this->bindFilterParams($stmt, $params);
        $stmt->execute();

        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Excluir órdenes ya reservadas
        if (!empty($excludedIds)) {
            $excludedIds = array_map('intval', $excludedIds);
            $rows = array_filter($rows, static function (array $row) use ($excludedIds): bool {
                return !in_array((int)($row['ID_COMPRA'] ?? 0), $excludedIds, true);
            });
        }

        return array_values($rows);
    }
    public function getConsignationStores(): array
    {
        $sql  = "SELECT ID_TIENDA, NOMBRE_CORTO FROM TBL_TIENDA ORDER BY NOMBRE_CORTO";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }
}
