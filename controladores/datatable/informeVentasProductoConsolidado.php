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

/**
 * Cachea en memoria la existencia por PROCOD EXACTO,
 * usando exactamente la misma lógica del kardex:
 *   - findAllByField('PROCOD', $procod)
 *   - ignora TMINOAFECT
 *   - KARTIPO desconocido => -1
 */
function obtenerExistenciaKardexPorProcod(string $procod, array $mapSinNoAfecta, DbfReader $reader): array
{
    static $cache = [];

    if (array_key_exists($procod, $cache)) {
        return $cache[$procod];
    }

    $allMatched = $reader->findAllByField('PROCOD', $procod);
    $filtered   = count($allMatched);

    $existencia   = 0.0;
    $desconocidos = [];

    foreach ($allMatched as $row) {
        $cant    = (float)($row['KARCANT'] ?? 0);
        $kartipo = trim((string)($row['KARTIPO'] ?? ''));

        $s = $mapSinNoAfecta[$kartipo] ?? -1;
        $existencia += $s * $cant;

        if (!isset($mapSinNoAfecta[$kartipo])) {
            $key = $kartipo === '' ? '(VACIO)' : $kartipo;
            $desconocidos[$key] = ($desconocidos[$key] ?? 0) + 1;
        }
    }

    $cache[$procod] = [
        'existencia' => round($existencia, 4),
        'recordsFiltered' => $filtered,
        'kartipos_desconocidos' => $desconocidos,
    ];

    return $cache[$procod];
}

try {
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
        AND TRIM(prod.procod) <> ''

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
    while ($row = $result->fetch_assoc()) {
        $row['cantidad'] = (float)($row['cantidad'] ?? 0);
        $row['valor_unitario'] = (float)($row['valor_unitario'] ?? 0);
        $row['subtotal'] = (float)($row['subtotal'] ?? 0);
        $row['existencias'] = 0.0;
        $data[] = $row;
    }

    $query->close();

    $pathTipo = EnvLoader::resolveFileInDir('DBF_DIR', 'DBF_TIPMOVINV');
    if (!$pathTipo || !file_exists($pathTipo)) {
        echo json_encode([
            'data' => [],
            'error' => 'TIPMOVINV.DBF no encontrado. Verifique el .env. Path buscado: ' . $pathTipo
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pathKar = EnvLoader::resolveFileInDir('DBF_DIR', 'DBF_KARMOVI');
    if (!$pathKar || !file_exists($pathKar)) {
        echo json_encode([
            'data' => [],
            'error' => 'Archivo KARMOVI.DBF no encontrado. Verifique la ruta en el archivo .env'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Misma regla del kardex:
    // ignora TMINOAFECT y desconocidos restan
    $mapSinNoAfecta = TipMovInvLoader::loadSignMapVariant($pathTipo, false);
    $reader = new DbfReader($pathKar, 'CP1252');

    $debugExistencias = [];

    foreach ($data as &$row) {
        $procod = trim((string)($row['codigo_producto'] ?? ''));

        if ($procod === '') {
            $row['existencias'] = 0.0;
            continue;
        }

        $calc = obtenerExistenciaKardexPorProcod($procod, $mapSinNoAfecta, $reader);
        $row['existencias'] = (float)$calc['existencia'];

        $debugExistencias[$procod] = [
            'recordsFiltered' => $calc['recordsFiltered'],
            'kartipos_desconocidos' => $calc['kartipos_desconocidos'],
        ];
    }
    unset($row);

    echo json_encode([
        'data' => $data,
        'debug' => [
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'productos_resultado' => count($data),
            'existencias_calculadas_como_kardex' => true,
            'detalle_por_procod' => $debugExistencias,
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    echo json_encode([
        'data' => [],
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}