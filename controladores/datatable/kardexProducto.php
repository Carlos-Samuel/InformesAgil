<?php
/**
 * Endpoint para el informe de Kardex por Producto.
 *
 * Modos:
 *   - Por defecto (POST/GET sin ?format): devuelve JSON con las 4 variantes
 *     de Existencia Total y diagnóstico — NO se envían los registros.
 *   - ?format=csv : streaming de CSV con TODOS los registros del producto
 *     (KARMOVI.DBF filtrado por PROCOD).
 *
 * Parámetros:
 *   procod  — código de producto (obligatorio)
 *   format  — 'csv' para descarga CSV; cualquier otro valor = JSON con agregados
 */
require_once '../EnvLoader.php';
require_once '../DbfReader.php';
require_once '../TipMovInvLoader.php';

$format = strtolower(trim($_REQUEST['format'] ?? ''));
$procod = trim($_REQUEST['procod'] ?? '');

// ============================================================
// Modo CSV — descarga de TODOS los registros del producto
// ============================================================
if ($format === 'csv') {
    if ($procod === '') {
        http_response_code(400);
        echo "Falta el parámetro 'procod'.";
        exit;
    }

    $pathKar = EnvLoader::resolveFileInDir('DBF_DIR', 'DBF_KARMOVI');
    if (!$pathKar || !file_exists($pathKar)) {
        http_response_code(500);
        echo "Archivo KARMOVI.DBF no encontrado. Verifique el .env";
        exit;
    }

    $fileName = 'kardex_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $procod) . '_'
              . date('Ymd_His') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    // BOM para que Excel reconozca UTF-8
    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');

    // Cargar mapa de signos desde TIPMOVINV (respetando TMINOAFECT).
    // El "default" de KARTIPO desconocido se aplica en cada variante de columna.
    $pathTipo   = EnvLoader::resolveFileInDir('DBF_DIR', 'DBF_TIPMOVINV');
    $tipoSignos = TipMovInvLoader::loadSignMapVariant($pathTipo, true);

    $baseColumns = [
        'KARID', 'PROCOD', 'KARTIPO', 'KARDOCU', 'PRFCOD', 'SEDE',
        'KARFECHA', 'KARHORA', 'BODCOD',
        'KARCANT', 'KARVALOR', 'KARCOSTO',
        'KARCONSE', 'KARESTADO', 'EMPCOD', 'PROUND', 'TMIAUX',
    ];
    // Dos columnas nuevas con la cantidad con signo aplicado:
    //   - CANT_DESC_POS: KARTIPO desconocido → asume +1 (suma)
    //   - CANT_DESC_NEG: KARTIPO desconocido → asume -1 (resta)
    // En ambas, TMINOAFECT=true produce 0.
    $extraColumns = ['CANT_DESC_POS', 'CANT_DESC_NEG'];
    fputcsv($out, array_merge($baseColumns, $extraColumns));

    $reader     = new DbfReader($pathKar, 'CP1252');
    $allMatched = $reader->findAllByField('PROCOD', $procod);

    foreach ($allMatched as $row) {
        $line = [];
        foreach ($baseColumns as $col) {
            $val = $row[$col] ?? null;
            if (is_bool($val))       $line[] = $val ? 'Si' : 'No';
            elseif ($val === null)   $line[] = '';
            else                     $line[] = $val;
        }

        // Calcular las dos columnas con cantidad signada
        $cant    = (float)($row['KARCANT'] ?? 0);
        $kartipo = trim((string)($row['KARTIPO'] ?? ''));

        $signPos = $tipoSignos[$kartipo] ?? 1;   // desconocidos → suman
        $signNeg = $tipoSignos[$kartipo] ?? -1;  // desconocidos → restan

        $line[] = round($signPos * $cant, 4);
        $line[] = round($signNeg * $cant, 4);

        fputcsv($out, $line);
    }

    fclose($out);
    exit;
}

// ============================================================
// Modo JSON — sólo agregados (4 variantes de Existencia Total)
// ============================================================
header('Content-Type: application/json; charset=utf-8');

if ($procod === '') {
    echo json_encode([
        'existencia'      => 0,
        'recordsFiltered' => 0,
        'error'           => 'Ingrese un código de producto.',
    ]);
    exit;
}

try {
    // --- TIPMOVINV: dos variantes del mapa de signos ---
    $pathTipo       = EnvLoader::resolveFileInDir('DBF_DIR', 'DBF_TIPMOVINV');
    $mapConNoAfecta = TipMovInvLoader::loadSignMapVariant($pathTipo, true);
    $mapSinNoAfecta = TipMovInvLoader::loadSignMapVariant($pathTipo, false);

    // --- KARMOVI: traer todas las coincidencias ---
    $pathKar = EnvLoader::resolveFileInDir('DBF_DIR', 'DBF_KARMOVI');
    if (!$pathKar || !file_exists($pathKar)) {
        echo json_encode([
            'existencia'      => 0,
            'recordsFiltered' => 0,
            'error'           => 'Archivo KARMOVI.DBF no encontrado. Verifique la ruta en el archivo .env',
        ]);
        exit;
    }

    $reader     = new DbfReader($pathKar, 'CP1252');
    $allMatched = $reader->findAllByField('PROCOD', $procod);
    $filtered   = count($allMatched);

    // --- Calcular las 4 variantes ---
    $existNoafSumaDesc    = 0.0; // respeta TMINOAFECT + desconocidos SUMAN
    $existNoafRestaDesc   = 0.0; // respeta TMINOAFECT + desconocidos RESTAN
    $existNoNoafSumaDesc  = 0.0; // ignora  TMINOAFECT + desconocidos SUMAN
    $existNoNoafRestaDesc = 0.0; // ignora  TMINOAFECT + desconocidos RESTAN

    $desconocidos  = [];
    $totalNoAfecta = 0;

    foreach ($allMatched as $row) {
        $cant    = (float)($row['KARCANT'] ?? 0);
        $kartipo = trim((string)($row['KARTIPO'] ?? ''));

        $s = $mapConNoAfecta[$kartipo] ?? 1;
        $existNoafSumaDesc    += $s * $cant;

        $s = $mapConNoAfecta[$kartipo] ?? -1;
        $existNoafRestaDesc   += $s * $cant;

        $s = $mapSinNoAfecta[$kartipo] ?? 1;
        $existNoNoafSumaDesc  += $s * $cant;

        $s = $mapSinNoAfecta[$kartipo] ?? -1;
        $existNoNoafRestaDesc += $s * $cant;

        if (!isset($mapConNoAfecta[$kartipo])) {
            $desconocidos[$kartipo] = ($desconocidos[$kartipo] ?? 0) + 1;
        } elseif (($mapConNoAfecta[$kartipo] ?? null) === 0) {
            $totalNoAfecta++;
        }
    }

    echo json_encode([
        'recordsFiltered' => $filtered,
        // Existencia principal (regla oficial: respeta TMINOAFECT, desconocidos suman)
        'existencia'      => round($existNoafSumaDesc, 4),
        'existencias' => [
            'con_noafecta_desc_suma'  => round($existNoafSumaDesc,    4),
            'con_noafecta_desc_resta' => round($existNoafRestaDesc,   4),
            'sin_noafecta_desc_suma'  => round($existNoNoafSumaDesc,  4),
            'sin_noafecta_desc_resta' => round($existNoNoafRestaDesc, 4),
        ],
        'debug' => [
            'registros_total'       => $filtered,
            'registros_no_afecta'   => $totalNoAfecta,
            'kartipos_desconocidos' => $desconocidos,
        ],
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    echo json_encode([
        'existencia'      => 0,
        'recordsFiltered' => 0,
        'error'           => $e->getMessage(),
    ]);
}
