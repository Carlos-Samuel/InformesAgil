<?php
/**
 * Endpoint de diagnóstico para encontrar diferencias entre
 * productos de MySQL y movimientos en KARMOVI.DBF.
 *
 * Input:
 *   q — término de búsqueda (puede ser parte del PROCOD o del nombre).
 *
 * Output (JSON):
 *   mysql[]   → filas de `productos` que matchean pronom o procod
 *   dbf[]     → procods distintos encontrados en KARMOVI.DBF
 *               (con count de movimientos y existencia acumulada)
 *   resumen   → totales
 *
 * La comparación DBF ↔ MySQL se hace normalizando
 * (trim + minúsculas) pero también reportamos HEX + longitud
 * para ayudar a detectar espacios invisibles, NBSP, etc.
 */
require_once '../Connection.php';
require_once '../EnvLoader.php';
require_once '../DbfReader.php';
require_once '../TipMovInvLoader.php';

header('Content-Type: application/json; charset=utf-8');

$q = trim($_REQUEST['q'] ?? '');
if ($q === '') {
    echo json_encode(['error' => 'Ingrese un término de búsqueda.']);
    exit;
}

try {
    // -------------------------------------------------------------
    // 1. Buscar en MySQL (productos)
    // -------------------------------------------------------------
    $con  = Connection::getInstance()->getConnection();
    $like = '%' . $q . '%';

    $stmt = $con->prepare("
        SELECT proid, procod, pronom
        FROM productos
        WHERE procod LIKE ? OR pronom LIKE ?
        ORDER BY pronom, procod
        LIMIT 500
    ");
    $stmt->bind_param('ss', $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();

    $mysqlRows       = [];
    $procodsCandidatos = [];   // set normalizado → true
    while ($r = $result->fetch_assoc()) {
        $procodRaw        = (string)$r['procod'];
        $norm             = mb_strtolower(trim($procodRaw), 'UTF-8');
        $r['procod_hex']  = strtoupper(bin2hex($procodRaw));
        $r['procod_len']  = strlen($procodRaw);         // bytes
        $r['procod_chars']= mb_strlen($procodRaw, 'UTF-8');
        $mysqlRows[]      = $r;
        if ($norm !== '') $procodsCandidatos[$norm] = true;
    }
    $stmt->close();

    // -------------------------------------------------------------
    // 2. Cargar mapa de signos de TIPMOVINV (con regla oficial)
    // -------------------------------------------------------------
    $pathTipo   = EnvLoader::resolveFileInDir('DBF_DIR', 'DBF_TIPMOVINV');
    $tipoSignos = TipMovInvLoader::loadSignMapVariant($pathTipo, true);

    // -------------------------------------------------------------
    // 3. Recorrer KARMOVI buscando PROCODs que:
    //    (a) existen en $procodsCandidatos (match normalizado), o
    //    (b) contienen $q literalmente (mb_stripos)
    //    Agrupamos por el PROCOD tal-cual del DBF para preservar
    //    diferencias de casing / espacios.
    // -------------------------------------------------------------
    $pathKar = EnvLoader::resolveFileInDir('DBF_DIR', 'DBF_KARMOVI');
    if (!$pathKar || !file_exists($pathKar)) {
        echo json_encode(['error' => 'KARMOVI.DBF no encontrado. Verifique .env']);
        exit;
    }

    $reader   = new DbfReader($pathKar, 'CP1252');
    $totalKar = $reader->getNumRecords();
    $qLower   = mb_strtolower($q, 'UTF-8');

    $dbfAgg    = [];   // key: PROCOD tal cual → ['count', 'exist', 'tipos'=>[]]
    $batchSize = 5000;

    for ($i = 0; $i < $totalKar; $i += $batchSize) {
        $chunk = $reader->readRecords($i, min($batchSize, $totalKar - $i));
        foreach ($chunk as $row) {
            $rawProcod = (string)($row['PROCOD'] ?? '');
            $rawTrim   = trim($rawProcod);
            if ($rawTrim === '') continue;

            $norm = mb_strtolower($rawTrim, 'UTF-8');

            $match = isset($procodsCandidatos[$norm])
                  || mb_stripos($rawProcod, $qLower, 0, 'UTF-8') !== false;
            if (!$match) continue;

            // Usamos el PROCOD tal como viene (preservando casing/espacios)
            if (!isset($dbfAgg[$rawProcod])) {
                $dbfAgg[$rawProcod] = [
                    'count' => 0,
                    'exist' => 0.0,
                    'tipos' => [],
                ];
            }

            $cant    = (float)($row['KARCANT'] ?? 0);
            $kartipo = trim((string)($row['KARTIPO'] ?? ''));
            $sign    = $tipoSignos[$kartipo] ?? 1;

            $dbfAgg[$rawProcod]['count']++;
            $dbfAgg[$rawProcod]['exist'] += $sign * $cant;
            $dbfAgg[$rawProcod]['tipos'][$kartipo] =
                ($dbfAgg[$rawProcod]['tipos'][$kartipo] ?? 0) + 1;
        }
    }

    // -------------------------------------------------------------
    // 4. Armar tabla DBF + flag "en_mysql"
    // -------------------------------------------------------------
    $dbfRows       = [];
    $totalDbfExist = 0.0;
    foreach ($dbfAgg as $procod => $agg) {
        $norm = mb_strtolower(trim($procod), 'UTF-8');
        $dbfRows[] = [
            'procod'         => $procod,
            'procod_hex'     => strtoupper(bin2hex($procod)),
            'procod_len'     => strlen($procod),
            'procod_chars'   => mb_strlen($procod, 'UTF-8'),
            'count'          => $agg['count'],
            'existencia'     => round($agg['exist'], 4),
            'en_mysql'       => isset($procodsCandidatos[$norm]),
            'tipos'          => $agg['tipos'],
        ];
        $totalDbfExist += $agg['exist'];
    }

    // Ordenar DBF por existencia absoluta desc
    usort($dbfRows, function ($a, $b) {
        return abs($b['existencia']) <=> abs($a['existencia']);
    });

    // -------------------------------------------------------------
    // 5. Enriquecer filas MySQL con la existencia que resultaría
    //    del JOIN contra tmp_existencias_dbf (mismo criterio del informe)
    // -------------------------------------------------------------
    $dbfByNorm = [];   // norm → suma existencia
    foreach ($dbfAgg as $procod => $agg) {
        $norm = mb_strtolower(trim($procod), 'UTF-8');
        $dbfByNorm[$norm] = ($dbfByNorm[$norm] ?? 0.0) + $agg['exist'];
    }
    foreach ($mysqlRows as &$r) {
        $norm = mb_strtolower(trim((string)$r['procod']), 'UTF-8');
        $r['existencia_dbf'] = isset($dbfByNorm[$norm])
            ? round($dbfByNorm[$norm], 4)
            : null;
    }
    unset($r);

    echo json_encode([
        'query'   => $q,
        'mysql'   => $mysqlRows,
        'dbf'     => $dbfRows,
        'resumen' => [
            'productos_en_mysql'       => count($mysqlRows),
            'procods_distintos_en_dbf' => count($dbfRows),
            'suma_total_dbf'           => round($totalDbfExist, 4),
        ],
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    echo json_encode([
        'error' => $e->getMessage(),
    ]);
}
