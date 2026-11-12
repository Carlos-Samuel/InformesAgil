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

function normalizarProcod($valor): string
{
    return trim((string)$valor);
}

/* ============================================================
   HELPER — Valida que KARFECHA esté dentro del rango pedido.

   DbfReader puede devolver el campo T como:
     · objeto DateTime / DateTimeImmutable
     · string 'YYYY-MM-DD' o 'YYYY-MM-DD HH:MM:SS'
     · string 'YYYYMMDD'
   Si no se puede interpretar la fecha se descarta el registro
   (comportamiento conservador; cámbialo a `return true` si
   prefieres incluir los registros con fecha ilegible).
   ============================================================ */
function esRegistroEnRango(array $row, string $fechaInicio, string $fechaFin): bool
{
    $karfecha = $row['KARFECHA'] ?? null;

    if ($karfecha === null || $karfecha === '') {
        return false;
    }

    // Caso 1: ya es un objeto DateTime / DateTimeImmutable
    if ($karfecha instanceof \DateTimeInterface) {
        $fechaReg = $karfecha->format('Y-m-d');
    }
    // Caso 2: string con separadores  →  '2024-03-15' o '2024-03-15 06:00:00'
    elseif (is_string($karfecha) && preg_match('/^(\d{4}-\d{2}-\d{2})/', $karfecha, $m)) {
        $fechaReg = $m[1];
    }
    // Caso 3: string compacto  →  '20240315'
    elseif (is_string($karfecha) && preg_match('/^(\d{4})(\d{2})(\d{2})/', $karfecha, $m)) {
        $fechaReg = "{$m[1]}-{$m[2]}-{$m[3]}";
    }
    else {
        // Formato desconocido: descartar
        return false;
    }

    return $fechaReg >= $fechaInicio && $fechaReg <= $fechaFin;
}

try {
    /* ============================================================
       PASO 1 — Consulta principal MySQL SIN existencias
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
    $procodsNecesarios = [];

    while ($row = $result->fetch_assoc()) {
        $procod = normalizarProcod($row['codigo_producto'] ?? '');

        $row['cantidad'] = (float)($row['cantidad'] ?? 0);
        $row['valor_unitario'] = (float)($row['valor_unitario'] ?? 0);
        $row['subtotal'] = (float)($row['subtotal'] ?? 0);
        $row['existencias'] = 0.0;
        $row['_procod_match'] = $procod;

        if ($procod !== '') {
            $procodsNecesarios[$procod] = true;
        }

        $data[] = $row;
    }

    $query->close();

    if (empty($data)) {
        echo json_encode([
            'data' => [],
            'debug' => [
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'productos_resultado' => 0
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    /* ============================================================
       PASO 2 — Cargar mapa de signos TIPMOVINV
       Misma regla del kardex:
       - ignora TMINOAFECT
       - KARTIPO desconocido resta (-1)
       ============================================================ */
    $pathTipo = EnvLoader::resolveFileInDir('DBF_DIR', 'DBF_TIPMOVINV');
    if (!$pathTipo || !file_exists($pathTipo)) {
        echo json_encode([
            'data' => [],
            'error' => 'TIPMOVINV.DBF no encontrado. Verifique el .env. Path buscado: ' . $pathTipo
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $tipoSignos = TipMovInvLoader::loadSignMapVariant($pathTipo, false);

    /* ============================================================
       PASO 3 — Leer KARMOVI UNA sola vez y acumular en arreglo
       SOLO para los PROCOD que realmente salen en el informe.
       Se acumulan TODOS los movimientos con KARFECHA <= fechaFin
       (sin límite inferior) para obtener el saldo real en bodega.
       ============================================================ */
    $pathKarmovi = EnvLoader::resolveFileInDir('DBF_DIR', 'DBF_KARMOVI');
    if (!$pathKarmovi || !file_exists($pathKarmovi)) {
        echo json_encode([
            'data' => [],
            'error' => 'KARMOVI.DBF no encontrado. Verifique el .env'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $reader = new DbfReader($pathKarmovi, 'CP1252');
    $karTotal = $reader->getNumRecords();

    $existencias = [];
    $debugExistencias = [];
    $kartiposDesconocidos = [];

    foreach (array_keys($procodsNecesarios) as $procod) {
        $existencias[$procod] = 0.0;
        $debugExistencias[$procod] = [
            'recordsFiltered' => 0,
            'kartipos_desconocidos' => []
        ];
    }

    // Escaneo en streaming: procesa cada registro activo con un callback
    // sin construir un array en memoria. Correcto con registros borrados.
    //
    // Las existencias son el saldo acumulado de TODOS los movimientos
    // hasta fechaFin (sin límite inferior). Filtrar por fechaInicio
    // eliminaría el stock previo al período y produciría valores negativos.
    $reader->scanRecords(function($row) use (
        &$existencias,
        &$debugExistencias,
        &$kartiposDesconocidos,
        $procodsNecesarios,
        $tipoSignos,
        $fechaFin
    ) {
        $procodDbf = normalizarProcod($row['PROCOD'] ?? '');

        if ($procodDbf === '' || !isset($procodsNecesarios[$procodDbf])) {
            return;
        }

        // Incluir todos los movimientos HASTA fechaFin (sin límite inferior).
        // Así se obtiene el saldo real en bodega al cierre del período.
        $karfecha = $row['KARFECHA'] ?? null;
        if ($karfecha !== null && $karfecha !== '') {
            if ($karfecha instanceof \DateTimeInterface) {
                $fechaReg = $karfecha->format('Y-m-d');
            } elseif (is_string($karfecha) && preg_match('/^(\d{4}-\d{2}-\d{2})/', $karfecha, $m)) {
                $fechaReg = $m[1];
            } elseif (is_string($karfecha) && preg_match('/^(\d{4})(\d{2})(\d{2})/', $karfecha, $m)) {
                $fechaReg = "{$m[1]}-{$m[2]}-{$m[3]}";
            } else {
                return; // Fecha ilegible: descartar
            }
            if ($fechaReg > $fechaFin) {
                return; // Movimiento posterior al período: no afecta existencias
            }
        }

        $cant    = (float)($row['KARCANT'] ?? 0);
        $kartipo = trim((string)($row['KARTIPO'] ?? ''));

        $sign = $tipoSignos[$kartipo] ?? -1;
        $existencias[$procodDbf] += ($sign * $cant);
        $debugExistencias[$procodDbf]['recordsFiltered']++;

        if (!isset($tipoSignos[$kartipo])) {
            $key = $kartipo === '' ? '(VACIO)' : $kartipo;
            $debugExistencias[$procodDbf]['kartipos_desconocidos'][$key] =
                ($debugExistencias[$procodDbf]['kartipos_desconocidos'][$key] ?? 0) + 1;

            $kartiposDesconocidos[$key] = ($kartiposDesconocidos[$key] ?? 0) + 1;
        }
    });

    /* ============================================================
       PASO 4 — Agregar existencias al arreglo final
       ============================================================ */
    foreach ($data as &$row) {
        $procod = $row['_procod_match'] ?? '';
        $row['existencias'] = round((float)($existencias[$procod] ?? 0), 4);
        unset($row['_procod_match']);
    }
    unset($row);

    echo json_encode([
        'data' => $data,
        'debug' => [
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'productos_resultado' => count($data),
            'productos_buscados_en_karmovi' => count($procodsNecesarios),
            'registros_karmovi' => $karTotal,
            'kartipos_desconocidos_global' => $kartiposDesconocidos,
            'detalle_por_procod' => $debugExistencias
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    echo json_encode([
        'data' => [],
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}