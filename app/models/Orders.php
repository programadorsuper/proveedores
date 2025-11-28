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
                TEM.ALIAS,
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
    /**
     * Obtener varias órdenes por un arreglo de IDs (IN (...)),
     * validando proveedor si aplica.
     *
     * @param int[] $idsCompra
     * @param int[] $providerIds
     * @param bool  $isSuperAdmin
     * @return array
     */
    public function getOrdenesByIds2(
        array $idsCompra,
        array $providerIds = [],
        bool $isSuperAdmin = false
    ): array {
        if (empty($idsCompra)) {
            return [];
        }

        // Placeholders para el IN de ID_COMPRA
        $placeholders = implode(', ', array_fill(0, count($idsCompra), '?'));

        $conditions = [];
        $bindValues = [];

        // IDs de compra obligatorios
        $conditions[] = "C.ID_COMPRA IN ($placeholders)";
        foreach ($idsCompra as $id) {
            $bindValues[] = (int)$id;
        }

        // Si NO es super admin, filtramos por proveedores
        $providerIds = array_values(array_unique(array_map('intval', $providerIds)));
        if (!$isSuperAdmin && !empty($providerIds)) {
            $provPlaceholders = implode(', ', array_fill(0, count($providerIds), '?'));
            $conditions[] = "C.ID_PROVEEDOR IN ($provPlaceholders)";
            foreach ($providerIds as $pid) {
                $bindValues[] = (int)$pid;
            }
        }

        $where = 'WHERE ' . implode(' AND ', $conditions);

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
            TEM.ALIAS,
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
        {$where}
    ";

        $stmt = $this->db->prepare($sql);

        // Bind posicional
        foreach ($bindValues as $i => $value) {
            $stmt->bindValue($i + 1, $value, \PDO::PARAM_INT);
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
            "CAST(LEFT(C.FECHA, 10) AS DATE) >= :from_date",
            "E.ID_ORDEN_ENTRADA IS NOT NULL"
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

    public function getBackordersPaginated(array $providerIds = [], array $options = [], int $page = 1, int $perPage = 25): array
    {
        // Siempre 2 meses hacia atrás (fecha inicial)
        $months = 2;
        $fromDate = (new \DateTimeImmutable())
            ->modify(sprintf('-%d months', $months))
            ->format('Y-m-d');

        // Si en tu base FECHA es TIMESTAMP, es común incluir hora
        $fromDateTime = $fromDate . ' 00:00:00';

        $search = trim((string)($options['search'] ?? ''));

        // WHERE dinámico con parámetros POSICIONALES (?)
        $conditions = [];
        $bindValues = [];

        // Condiciones base (sin parámetros)
        $conditions[] = "C.ID_TIPO_DOCUMENTO IN (20,21,12,13)";
        $conditions[] = "C.CANCELADA = 0";

        // Fecha (considerando que C.FECHA es DATE/TIMESTAMP)
        $conditions[] = "C.FECHA >= ?";
        $bindValues[] = $fromDateTime;

        // Debe existir orden de entrada
        $conditions[] = "E.ID_ORDEN_ENTRADA IS NOT NULL";

        // Filtro por proveedores (IN con ? ? ?)
        if (!empty($providerIds)) {
            $placeholders = array_fill(0, count($providerIds), '?');
            $conditions[] = 'C.ID_PROVEEDOR IN (' . implode(', ', $placeholders) . ')';

            foreach ($providerIds as $providerId) {
                $bindValues[] = (int)$providerId;
            }
        }

        // ===========================
        // BUSCADOR SUPER AVANZADO
        // ===========================
        if ($search !== '') {
            // Partimos el texto en palabras (por espacios)
            $terms = preg_split('/\s+/', $search);
            $terms = array_filter($terms, static fn($t) => $t !== '');

            foreach ($terms as $term) {
                $term = trim($term);
                if ($term === '') {
                    continue;
                }

                $orParts = [];

                // FOLIO: buscamos que contenga el término
                $orParts[]  = "C.FOLIO LIKE ?";
                $bindValues[] = '%' . $term . '%';

                // Nombre proveedor
                $orParts[]  = "CP.RAZON_SOCIAL LIKE ?";
                $bindValues[] = '%' . $term . '%';

                // // Nombre tienda
                $orParts[]  = "T.NOMBRE_CORTO LIKE ?";
                $bindValues[] = '%' . $term . '%';

                // Temporada y alias
                $orParts[]  = "TEM.TEMPORADA LIKE ?";
                $bindValues[] = '%' . $term . '%';

                // $orParts[]  = "TEM.ALIAS LIKE ?";
                // $bindValues[] = '%'.$term.'%';

                $orParts[]   = "CP.NUMERO_PROVEEDOR LIKE ?";
                $bindValues[] = '%' . $term . '%';

                // Cada palabra genera un grupo ( ... ) y todos los grupos se AND-ean
                $conditions[] = '(' . implode(' OR ', $orParts) . ')';
            }
        }

        $where = '';
        if (!empty($conditions)) {
            $where = 'WHERE ' . implode(' AND ', $conditions);
        }

        // ---------------------------
        // Paginación
        // ---------------------------
        $page    = max(1, (int)$page);
        $perPage = max(1, (int)$perPage);
        $offset  = ($page - 1) * $perPage;
        $rowStart = $offset + 1;
        $rowEnd   = $offset + $perPage;

        $rowStart = max(1, (int)$rowStart);
        $rowEnd   = max($rowStart, (int)$rowEnd);

        // Parte común FROM + GROUP BY + HAVING
        $fromGroupHaving = "
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
            T.NOMBRE_CORTO,
            C.ID_TIENDA_CONSIGNADA,
            T.ID_TIENDA,
            T.CALLE,
            T.COLONIA,
            T.MUNICIPIO,
            T.CODIGO_POSTAL
        HAVING
            SUM(COALESCE(EDET.CANTIDAD_RECIBIDA, 0)) < SUM(CD.CANTIDAD_SOLICITADA)
            -- Si quieres que solo salgan compras con algo recibido, descomenta la siguiente línea:
            -- AND SUM(COALESCE(EDET.CANTIDAD_RECIBIDA, 0)) > 0
        ";

        // ===========================
        // 1) TOTAL DE REGISTROS
        // ===========================
        $sqlTotal = "
            SELECT COUNT(*) AS TOTAL
            FROM (
                SELECT C.ID_COMPRA
                {$fromGroupHaving}
            ) X
        ";

        $stmtTotal = $this->db->prepare($sqlTotal);

        // Bind POSICIONAL (1,2,3,...) con los mismos valores de $bindValues
        foreach ($bindValues as $index => $value) {
            $stmtTotal->bindValue($index + 1, $value);
        }

        $stmtTotal->execute();
        $total = (int)($stmtTotal->fetchColumn() ?: 0);

        // ===========================
        // 2) LISTA PAGINADA
        // ===========================
        $sqlList = "
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
                SUM(COALESCE(EDET.CANTIDAD_RECIBIDA, 0)) AS TOTAL_RECIBIDO,
                CASE 
                    WHEN C.ID_TIENDA_CONSIGNADA = T.ID_TIENDA THEN
                        TRIM(T.NOMBRE_CORTO) || ' - ' ||
                        TRIM(T.CALLE)        || ', ' ||
                        TRIM(T.COLONIA)      || ', ' ||
                        TRIM(T.MUNICIPIO)    || ', ' ||
                        TRIM(T.CODIGO_POSTAL)
                    ELSE
                        'CENDIS - AV.TEJOCOTES, COL SAN MARTIN OBISPO, 54769'
                END AS LUGAR_ENTREGA
            {$fromGroupHaving}
            ORDER BY C.FECHA DESC
            ROWS {$rowStart} TO {$rowEnd}
        ";

        $stmt = $this->db->prepare($sqlList);

        // Misma secuencia de parámetros
        foreach ($bindValues as $index => $value) {
            $stmt->bindValue($index + 1, $value);
        }

        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Post-proceso: totales y porcentajes
        $items = array_map(static function (array $row): array {
            $ordered  = (float)($row['TOTAL_SOLICITADO'] ?? 0);
            $received = (float)($row['TOTAL_RECIBIDO'] ?? 0);
            $pending  = max($ordered - $received, 0);
            $percent  = $ordered > 0 ? round(($received / $ordered) * 100, 2) : 0;

            $row['TOTAL_SOLICITADO'] = $ordered;
            $row['TOTAL_RECIBIDO']   = $received;
            $row['PENDING_TOTAL']    = $pending;
            $row['PERCENT_RECEIVED'] = $percent;

            return $row;
        }, $rows);

        return [
            'items'    => $items,
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
        ];
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
    /**
     * Detalle de una orden para la CITA:
     * - Si la orden NO tiene entradas → orden NUEVA → devuelve TODOS los renglones.
     * - Si YA tiene entradas → BACKORDER → devuelve SOLO lo pendiente.
     *
     * Cada renglón incluye:
     *  - QTY_REQUESTED  (solicitada)
     *  - QTY_RECEIVED   (recibida en almacén)
     *  - QTY_PENDING    (faltante)
     *  - QTY_TO_DELIVER (a entregar en esta cita, por defecto = faltante)
     */
    public function detallesParaCita(int $idCompra): array
    {
        // Detalle original de la compra
        $items = $this->detallesByOrden($idCompra);

        // Cantidades recibidas por artículo (todas las entradas)
        $receivedMap = $this->getReceivedQuantities($idCompra);

        // Entradas de almacén: si hay → es backorder
        $entries = $this->getEntradasAlmacen($idCompra);
        $isNewOrder = empty($entries);

        $lines = [];
        foreach ($items as $row) {
            $idArt     = (int)($row['ID_ARTICULO'] ?? 0);
            $requested = (float)($row['CANTIDAD_SOLICITADA'] ?? 0);
            $received  = (float)($receivedMap[$idArt] ?? 0);
            $pending   = max($requested - $received, 0);

            // Si es BACKORDER y ya no hay nada pendiente de este artículo → no lo incluimos
            if (!$isNewOrder && $pending <= 0) {
                continue;
            }

            $row['QTY_REQUESTED']  = $requested;
            $row['QTY_RECEIVED']   = $received;
            $row['QTY_PENDING']    = $pending;
            $row['QTY_TO_DELIVER'] = $pending; // por defecto, se propone entregar lo pendiente

            $lines[] = $row;
        }

        return $lines;
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
        if ($storeId < 0) {
            return [];
        }

        // 2 meses atrás
        $fromDate = (new \DateTimeImmutable('-2 months'))->format('Y-m-d');

        $conditions = [
            "COM.ID_TIPO_DOCUMENTO IN (20,21,12,13)",
            "COM.CANCELADA = 0",
            "COM.ID_USUARIO_AUTORIZA IS NOT NULL",
            "COM.ID_USUARIO_AUTORIZA <> 0",
            "CAST(LEFT(COM.FECHA, 10) AS DATE) >= :from_date",
            "COALESCE(COM.ID_TIENDA_CONSIGNADA, 0) = :store_id",
            // Nuevas (sin entrada) o backorders (entrada parcial)
            "(ENT.ID_ORDEN_ENTRADA IS NULL
          OR (ENT.ID_ORDEN_ENTRADA IS NOT NULL
              AND COALESCE(BO.TOTAL_RECIBIDO, 0) < COALESCE(BO.TOTAL_SOLICITADO, 0)))",
        ];

        $params = [
            ':from_date' => $fromDate,
            ':store_id'  => $storeId,
        ];

        // --- Filtros opcionales por alias, folio y serie --- //
        $alias = isset($filters['alias']) ? trim((string)$filters['alias']) : '';
        if ($alias !== '') {
            $conditions[] = 'COALESCE(TEM.ALIAS, \'\') = :alias';
            $params[':alias'] = $alias;
        }

        $folio = isset($filters['folio']) ? (int)$filters['folio'] : 0;
        if ($folio > 0) {
            $conditions[] = 'COM.FOLIO = :folio';
            $params[':folio'] = $folio;
        }

        $serie = isset($filters['serie']) ? trim((string)$filters['serie']) : '';
        if ($serie !== '') {
            $conditions[] = 'COM.SERIE = :serie';
            $params[':serie'] = $serie;
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
            ENT.ID_ORDEN_ENTRADA,
            -- Totales de backorder (pueden ser 0 para órdenes nuevas sin entrada)
            COALESCE(BO.TOTAL_SOLICITADO, 0) AS TOTAL_SOLICITADO,
            COALESCE(BO.TOTAL_RECIBIDO, 0)   AS TOTAL_RECIBIDO,
            CASE
                WHEN COALESCE(BO.TOTAL_SOLICITADO, 0) > COALESCE(BO.TOTAL_RECIBIDO, 0)
                    THEN COALESCE(BO.TOTAL_SOLICITADO, 0) - COALESCE(BO.TOTAL_RECIBIDO, 0)
                ELSE 0
            END AS PENDING_TOTAL,
            CASE
                WHEN COALESCE(BO.TOTAL_SOLICITADO, 0) > 0
                    THEN ROUND((COALESCE(BO.TOTAL_RECIBIDO, 0) / BO.TOTAL_SOLICITADO) * 100, 2)
                ELSE 0
            END AS PERCENT_RECEIVED,
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
            U.NOMBRE  || ' ' || U.PATERNO  || ' ' || U.MATERNO  AS CAPTURA,
            UA.NOMBRE || ' ' || UA.PATERNO || ' ' || UA.MATERNO AS AUTORIZA
        FROM TBL_COMPRAS COM

        -- 🔹 AQUÍ VIENE EL CAMBIO IMPORTANTE: agregamos ENT como subconsulta agrupada
        LEFT JOIN (
            SELECT
                ID_COMPRA,
                MIN(ID_ORDEN_ENTRADA) AS ID_ORDEN_ENTRADA
            FROM TBL_ENTRADA_ALMACEN
            GROUP BY ID_COMPRA
        ) ENT
            ON COM.ID_COMPRA = ENT.ID_COMPRA

        -- Subconsulta de backorder por compra (totales solicitados/recibidos)
        LEFT JOIN (
            SELECT
                CD.ID_COMPRA,
                SUM(CD.CANTIDAD_SOLICITADA) AS TOTAL_SOLICITADO,
                SUM(COALESCE(EDET.CANTIDAD_RECIBIDA, 0)) AS TOTAL_RECIBIDO
            FROM TBL_COMPRAS_DETALLE CD
            LEFT JOIN TBL_ENTRADA_ALMACEN E
                ON E.ID_COMPRA = CD.ID_COMPRA
            LEFT JOIN TBL_ENTRADA_ALMACEN_DETALLE EDET
                ON EDET.ID_ORDEN_ENTRADA = E.ID_ORDEN_ENTRADA
               AND EDET.ID_ARTICULO = CD.ID_ARTICULO
            GROUP BY CD.ID_COMPRA
        ) BO
            ON BO.ID_COMPRA = COM.ID_COMPRA

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

        // Clasificar cada orden: NUEVA / BACKORDER + porcentajes “que faltan”
        foreach ($rows as &$row) {
            $ordered  = (float)($row['TOTAL_SOLICITADO'] ?? 0);
            $received = (float)($row['TOTAL_RECIBIDO'] ?? 0);
            $pending  = max($ordered - $received, 0.0);

            $row['TOTAL_SOLICITADO'] = $ordered;
            $row['TOTAL_RECIBIDO']   = $received;
            $row['PENDING_TOTAL']    = $pending;

            if (empty($row['ID_ORDEN_ENTRADA'])) {
                $row['ORDER_KIND']        = 'NUEVA';
                $row['PERCENT_RECEIVED']  = 0.0;
                $row['PERCENT_PENDING']   = $ordered > 0 ? 100.0 : 0.0;
            } else {
                $row['ORDER_KIND'] = 'BACKORDER';
                if ($ordered > 0) {
                    $percentReceived = round(($received / $ordered) * 100, 2);
                    $row['PERCENT_RECEIVED'] = $percentReceived;
                    $row['PERCENT_PENDING']  = max(100.0 - $percentReceived, 0.0);
                } else {
                    $row['PERCENT_RECEIVED'] = 0.0;
                    $row['PERCENT_PENDING']  = 0.0;
                }
            }
        }
        unset($row);

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
        $sql  = "SELECT 
                    ID_TIENDA,
                    TRIM(NOMBRE_CORTO) AS NOMBRE_CORTO
                FROM TBL_TIENDA
                WHERE ID_TIENDA NOT IN (9, 11)
                ORDER BY TRIM(NOMBRE_CORTO);
                ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }
}
