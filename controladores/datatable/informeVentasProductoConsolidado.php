<?php
require_once '../Connection.php';
require_once '../EnvLoader.php';
require_once '../DbfReader.php';

header('Content-Type: application/json');

$fechaInicio = $_POST['fechaInicio'] ?? null;
$fechaFin    = $_POST['fechaFin']    ?? null;

if (!$fechaInicio || !$fechaFin) {
    echo json_encode(['data' => [], 'error' => 'Fechas obligatorias']);
    exit;
}

$vendedor = $_POST['vendedor'] ?? [];
$empresa  = $_POST['empresa']  ?? [];
$grupo    = $_POST['grupo']    ?? [];
$subgrupo = $_POST['subgrupo'] ?? [];
$marca    = $_POST['marca']    ?? [];
$prefijo  = $_POST['prefijo']  ?? [];

$con = Connection::getInstance()->getConnection();

function agregarFiltroMultiple(&$where, &$params, &$types, $col, $vals) {
    if (is_array($vals) && count($vals) > 0) {
        $placeholders = implode(',', array_fill(0, count($vals), '?'));
        $where .= " AND $col IN ($placeholders)";
        foreach ($vals as $v) { $params[] = $v; $types .= 's'; }
    }
}

/* ============================================================
   PASO 1 — Leer TIPMOVINV desde el DBF
   Construye un lookup: TMICOD (string, 3 chars) → TMITIPO ('+' o '-')
   Este archivo tiene ~98 000 registros, se carga completo en memoria.
   ============================================================ */
$pathTipMovInv = EnvLoader::resolvePath('DBF_TIPMOVINV');
if (!$pathTipMovInv || !file_exists($pathTipMovInv)) {
    echo json_encode(['data' => [], 'error' => 'TIPMOVINV.DBF no encontrado. Verifique el .env']);
    exit;
}

$tipReader  = new DbfReader($pathTipMovInv, 'CP1252');
$tipTotal   = $tipReader->getNumRecords();
$tipoLookup = []; // ['FAV' => '-', 'COM' => '+', ...]

$batchSize = 2000;
for ($i = 0; $i < $tipTotal; $i += $batchSize) {
    foreach ($tipReader->readRecords($i, $batchSize) as $row) {
        $cod  = trim($row['TMICOD']  ?? '');
        $tipo = trim($row['TMITIPO'] ?? '');
        if ($cod !== '') {
            $tipoLookup[$cod] = $tipo;
        }
    }
}

/* ============================================================
   PASO 2 — Escanear KARMOVI desde el DBF en batches
   Filtra por KARFECHA <= $fechaFin y acumula existencias por PROCOD.

   Nota de rendimiento: este archivo tiene ~638 000 registros (~187 MB).
   El escaneo completo tarda entre 5 y 20 segundos según el disco.
   La lógica replica exactamente la subconsulta original:
     TMITIPO = '+' → suma cantidad
     TMITIPO = '-' → resta cantidad
     TMITIPO null  → resta (igual que el CASE original con tmi.tmiid IS NULL)
   ============================================================ */
$pathKarmovi = EnvLoader::resolvePath('DBF_KARMOVI');
if (!$pathKarmovi || !file_exists($pathKarmovi)) {
    echo json_encode(['data' => [], 'error' => 'KARMOVI.DBF no encontrado. Verifique el .env']);
    exit;
}

$karReader   = new DbfReader($pathKarmovi, 'CP1252');
$karTotal    = $karReader->getNumRecords();
$existencias = []; // ['PROCOD' => float]

for ($i = 0; $i < $karTotal; $i += $batchSize) {
    $rows = $karReader->readRecords($i, min($batchSize, $karTotal - $i));

    foreach ($rows as $row) {
        // Filtrar por fecha (KARFECHA llega como 'YYYY-MM-DD HH:MM:SS' o null)
        $fecha = $row['KARFECHA'] ?? null;
        if ($fecha === null) continue;
        if (substr((string)$fecha, 0, 10) > $fechaFin) continue;

        // Normalizar: mayúsculas + sin espacios, para que PHP y MySQL
        // coincidan aunque el DBF tenga variaciones de casing o padding.
        $procod  = strtoupper(trim($row['PROCOD'] ?? ''));
        if ($procod === '') continue;

        $kartipo = trim($row['KARTIPO'] ?? '');
        $tipo    = $tipoLookup[$kartipo] ?? null;  // '+', '-', o null
        $cant    = (float)($row['KARCANT'] ?? 0);

        if (!isset($existencias[$procod])) {
            $existencias[$procod] = 0.0;
        }

        if ($tipo === '+') {
            $existencias[$procod] += $cant;
        } else {
            // tipo '-' o tipo null (desconocido) → resta, igual que el CASE original
            $existencias[$procod] -= $cant;
        }
    }
}

/* ============================================================
   PASO 3 — Volcar $existencias a una tabla temporal MySQL
   La tabla temporal vive solo durante esta conexión.
   Se une al resto de la consulta por prod.procod.
   ============================================================ */
$con->query("
    CREATE TEMPORARY TABLE IF NOT EXISTS tmp_existencias_dbf (
        procod      VARCHAR(25)    NOT NULL,
        existencias DECIMAL(18,4) NOT NULL DEFAULT 0,
        PRIMARY KEY (procod)
    ) ENGINE=MEMORY
");
$con->query("DELETE FROM tmp_existencias_dbf");

if (!empty($existencias)) {
    $procods    = array_keys($existencias);
    $insertSize = 500;

    for ($i = 0; $i < count($procods); $i += $insertSize) {
        $chunk  = array_slice($procods, $i, $insertSize);
        $values = [];
        foreach ($chunk as $p) {
            $esc = $con->real_escape_string($p);
            $val = round($existencias[$p], 4);
            $values[] = "('$esc', $val)";
        }
        // ON DUPLICATE KEY UPDATE como red de seguridad: si por alguna
        // codificación dos claves PHP distintas colisionan en MySQL,
        // acumula en lugar de fallar.
        $con->query(
            "INSERT INTO tmp_existencias_dbf (procod, existencias) VALUES "
            . implode(',', $values)
            . " ON DUPLICATE KEY UPDATE existencias = existencias + VALUES(existencias)"
        );
    }
}

/* ============================================================
   PASO 4 — Consulta principal contra MySQL
   La subconsulta original de karmovi/tipmovinv se reemplaza
   por el JOIN contra tmp_existencias_dbf.
   ============================================================ */
$whereVentas  = "WHERE ve.vtafec BETWEEN ? AND ?";
$paramsVentas = [$fechaInicio, $fechaFin];
$typesVentas  = "ss";

agregarFiltroMultiple($whereVentas, $paramsVentas, $typesVentas, 'ven.venid',  $vendedor);
agregarFiltroMultiple($whereVentas, $paramsVentas, $typesVentas, 'ven.empid',  $empresa);
agregarFiltroMultiple($whereVentas, $paramsVentas, $typesVentas, 'prod.grpid', $grupo);
agregarFiltroMultiple($whereVentas, $paramsVentas, $typesVentas, 'prod.subid', $subgrupo);
agregarFiltroMultiple($whereVentas, $paramsVentas, $typesVentas, 'mar.marid',  $marca);
agregarFiltroMultiple($whereVentas, $paramsVentas, $typesVentas, 'pre.prfid',  $prefijo);

$sql = "
    SELECT
        prod.procod   AS codigo_producto,
        prod.prorefer AS registro_ica,
        prod.pronom   AS nombre_producto,

        SUM(vedet.vtacant) AS cantidad,

        CASE
            WHEN SUM(vedet.vtacant) = 0 THEN 0
            ELSE ROUND(SUM(vedet.vtacant * vedet.vtavlruni) / SUM(vedet.vtacant), 2)
        END AS valor_unitario,

        SUM(vedet.vtacant * vedet.vtavlruni) AS subtotal,

        COALESCE(inv.existencias, 0) AS existencias,

        gru.grpnom    AS grupo,
        subgru.subnom AS subgrupo,
        mar.marnom    AS marca

    FROM ventas AS ve
    LEFT JOIN ventasdet    AS vedet  ON ve.vtaid   = vedet.vtaid
    LEFT JOIN productos    AS prod   ON vedet.proid = prod.proid
    LEFT JOIN vendedor     AS ven    ON ve.venid    = ven.venid
    LEFT JOIN inmarca      AS mar    ON prod.marid  = mar.marid
    LEFT JOIN ingrupos     AS gru    ON prod.grpid  = gru.grpid
    LEFT JOIN insubgrupo   AS subgru ON prod.subid  = subgru.subid
    LEFT JOIN prefijo      AS pre    ON ve.prfid    = pre.prfid

    -- Existencias calculadas desde los DBF (reemplaza la subconsulta original)
    LEFT JOIN tmp_existencias_dbf AS inv ON prod.procod = inv.procod

    $whereVentas
    AND prod.procod IS NOT NULL

    GROUP BY
        prod.proid,
        prod.procod,
        prod.prorefer,
        prod.pronom,
        inv.existencias,
        gru.grpnom,
        subgru.subnom,
        mar.marnom

    ORDER BY prod.pronom
";

$query = $con->prepare($sql);

if (!$query) {
    echo json_encode(['data' => [], 'error' => 'Error al preparar la consulta: ' . $con->error]);
    exit;
}

$query->bind_param($typesVentas, ...$paramsVentas);
$query->execute();
$result = $query->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode(['data' => $data]);
