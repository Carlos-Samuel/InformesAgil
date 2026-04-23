<?php
require_once '../Connection.php';
require_once '../EnvLoader.php';
require_once '../DbfReader.php';
require_once '../TipMovInvLoader.php';

header('Content-Type: application/json; charset=utf-8');

$fechaInicio = $_POST['fechaInicio'] ?? null;
$fechaFin    = $_POST['fechaFin'] ?? null;

if (!$fechaInicio || !$fechaFin) {
    echo json_encode([
        'data' => [],
        'error' => 'Fechas obligatorias'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$vendedor = $_POST['vendedor'] ?? [];
$empresa  = $_POST['empresa'] ?? [];
$grupo    = $_POST['grupo'] ?? [];
$subgrupo = $_POST['subgrupo'] ?? [];
$marca    = $_POST['marca'] ?? [];
$prefijo  = $_POST['prefijo'] ?? [];

$con = Connection::getInstance()->getConnection();

function agregarFiltroMultiple(&$where, &$params, &$types, $col, $vals)
{
    if (is_array($vals) && count($vals) > 0) {
        $placeholders = implode(',', array_fill(0, count($vals), '?'));
        $where .= " AND $col IN ($placeholders)";
        foreach ($vals as $v) {
            $params[] = $v;
            $types .= 's';
        }
    }
}

function normalizarCodigoProducto($codigo)
{
    return strtoupper(trim((string)$codigo));
}

/**
 * Cache en memoria por request.
 * Estructura:
 * [
 *   'existencias' => ['PROCOD' => float],
 *   'debug' => [
 *      'kartipos_desconocidos' => ['XX' => 15],
 *      'registros_karmovi' => int,
 *      'productos_con_existencia' => int
 *   ]
 * ]
 */
function construirCacheExistenciasDesdeDbf()
{
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $batchSize = 2000;

    $pathTipMovInv = EnvLoader::resolveFileInDir('DBF_DIR', 'DBF_TIPMOVINV');
    if (!$pathTipMovInv || !file_exists($pathTipMovInv)) {
        throw new RuntimeException(
            'TIPMOVINV.DBF no encontrado. Verifique el .env. Path buscado: ' . $pathTipMovInv
        );
    }

    $pathKarmovi = EnvLoader::resolveFileInDir('DBF_DIR', 'DBF_KARMOVI');
    if (!$pathKarmovi || !file_exists($pathKarmovi)) {
        throw new RuntimeException('KARMOVI.DBF no encontrado. Verifique el .env');
    }

    // MISMA regla del kardex:
    // - ignora TMINOAFECT
    // - KARTIPO desconocido => resta (-1)
    $tipoSignos = TipMovInvLoader::loadSignMapVariant($pathTipMovInv, false, $batchSize);

    $karReader = new DbfReader($pathKarmovi, 'CP1252');
    $karTotal  = $karReader->getNumRecords();

    $existencias = [];
    $desconocidos = [];

    for ($i = 0; $i < $karTotal; $i += $batchSize) {
        $rows = $karReader->readRecords($i, min($batchSize, $karTotal - $i));

        foreach ($rows as $row) {
            $procod = normalizarCodigoProducto($row['PROCOD'] ?? '');
            if ($procod === '') {
                continue;
            }

            $kartipo = trim((string)($row['KARTIPO'] ?? ''));
            $cant    = (float)($row['KARCANT'] ?? 0);

            $sign = $tipoSignos[$kartipo] ?? -1;

            if (!isset($existencias[$procod])) {
                $existencias[$procod] = 0.0;
            }

            $existencias[$procod] += ($sign * $cant);

            if (!isset($tipoSignos[$kartipo])) {
                $key = $kartipo === '' ? '(VACIO)' : $kartipo;
                $desconocidos[$key] = ($desconocidos[$key] ?? 0) + 1;
            }
        }
    }

    $cache = [
        'existencias' => $existencias,
        'debug' => [
            'kartipos_desconocidos' => $desconocidos,
            'registros_karmovi' => $karTotal,
            'productos_con_existencia' => count($existencias),
        ],
    ];

    return $cache;
}

try {
    /* ============================================================
       PASO 1 — Consulta principal SOLO contra MySQL
       Aquí ya NO se hace JOIN contra tabla temporal de existencias.
       Solo se trae el consolidado comercial.
       ============================================================ */
    $whereVentas  = "WHERE ve.vtafec BETWEEN ? AND ?";
    $paramsVentas = [$fechaInicio, $fechaFin];
    $typesVentas  = "ss";

    agregarFiltroMultiple($whereVentas, $paramsVentas, $typesVentas, 'ven.venid', $vendedor);
    agregarFiltroMultiple($whereVentas, $paramsVentas, $typesVentas, 'ven.empid', $empresa);
    agregarFiltroMultiple($whereVentas, $paramsVentas, $typesVentas, 'prod.grpid', $grupo);
    agregarFiltroMultiple($whereVentas, $paramsVentas, $typesVentas, 'prod.subid', $subgrupo);
    agregarFiltroMultiple($whereVentas, $paramsVentas, $typesVentas, 'mar.marid', $marca);
    agregarFiltroMultiple($whereVentas, $paramsVentas, $typesVentas, 'pre.prfid', $prefijo);

    $sql = "
        SELECT
            prod.proid     AS producto_id,
            prod.procod    AS codigo_producto,
            prod.prorefer  AS registro_ica,
            prod.pronom    AS nombre_producto,

            SUM(vedet.vtacant) AS cantidad,

            CASE
                WHEN SUM(vedet.vtacant) = 0 THEN 0
                ELSE ROUND(SUM(vedet.vtacant * vedet.vtavlruni) / SUM(vedet.vtacant), 2)
            END AS valor_unitario,

            SUM(vedet.vtacant * vedet.vtavlruni) AS subtotal,

            gru.grpnom     AS grupo,
            subgru.subnom  AS subgrupo,
            mar.marnom     AS marca

        FROM ventas AS ve
        LEFT JOIN ventasdet  AS vedet   ON ve.vtaid = vedet.vtaid
        LEFT JOIN productos  AS prod    ON vedet.proid = prod.proid
        LEFT JOIN vendedor   AS ven     ON ve.venid = ven.venid
        LEFT JOIN inmarca    AS mar     ON prod.marid = mar.marid
        LEFT JOIN ingrupos   AS gru     ON prod.grpid = gru.grpid
        LEFT JOIN insubgrupo AS subgru  ON prod.subid = subgru.subid
        LEFT JOIN prefijo    AS pre     ON ve.prfid = pre.prfid

        $whereVentas
        AND prod.procod IS NOT NULL

        GROUP BY
            prod.proid,
            prod.procod,
            prod.prorefer,
            prod.pronom,
            gru.grpnom,
            subgru.subnom,
            mar.marnom

        ORDER BY prod.pronom
    ";

    $query = $con->prepare($sql);

    if (!$query) {
        echo json_encode([
            'data' => [],
            'error' => 'Error al preparar la consulta: ' . $con->error
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $query->bind_param($typesVentas, ...$paramsVentas);
    $query->execute();
    $result = $query->get_result();

    $data = [];
    $codigosNecesarios = [];

    while ($row = $result->fetch_assoc()) {
        $codigoNormalizado = normalizarCodigoProducto($row['codigo_producto'] ?? '');

        $row['cantidad'] = (float)($row['cantidad'] ?? 0);
        $row['valor_unitario'] = (float)($row['valor_unitario'] ?? 0);
        $row['subtotal'] = (float)($row['subtotal'] ?? 0);

        $row['_codigo_normalizado'] = $codigoNormalizado;
        $row['existencias'] = 0.0;

        if ($codigoNormalizado !== '') {
            $codigosNecesarios[$codigoNormalizado] = true;
        }

        $data[] = $row;
    }

    $query->close();

    /* ============================================================
       PASO 2 — Construir cache de existencias en memoria
       Se recorre KARMOVI una sola vez por request.
       NO se guarda en MySQL.
       ============================================================ */
    $cacheExistencias = construirCacheExistenciasDesdeDbf();
    $mapExistencias   = $cacheExistencias['existencias'];

    /* ============================================================
       PASO 3 — Agregar existencias al arreglo final antes de responder
       ============================================================ */
    foreach ($data as &$row) {
        $codigoNormalizado = $row['_codigo_normalizado'] ?? '';
        $row['existencias'] = (float)($mapExistencias[$codigoNormalizado] ?? 0);

        unset($row['_codigo_normalizado']);
    }
    unset($row);

    echo json_encode([
        'data' => $data,
        'debug' => [
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'productos_resultado' => count($data),
            'productos_en_cache_existencias' => $cacheExistencias['debug']['productos_con_existencia'],
            'registros_karmovi' => $cacheExistencias['debug']['registros_karmovi'],
            'kartipos_desconocidos' => $cacheExistencias['debug']['kartipos_desconocidos'],
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    echo json_encode([
        'data' => [],
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}