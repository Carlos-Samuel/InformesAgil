<?php
/**
 * Endpoint para el informe de Kardex por Producto.
 *
 * Modos:
 *   - Por defecto (POST/GET sin ?format): devuelve JSON con la Existencia Total
 *     calculada bajo la regla "Ignora TMINOAFECT · desc. RESTAN" —
 *     NO se envían los registros individuales.
 *   - ?format=csv : streaming de CSV con TODOS los registros del producto
 *     (KARMOVI.DBF filtrado por PROCOD).
 *
 * Regla de cálculo:
 *   - Se IGNORA el campo TMINOAFECT (los movimientos marcados como "no afectan"
 *     sí se suman/restan).
 *   - KARTIPO desconocido (no presente en TIPMOVINV) → se RESTA (-1).
 *
 * Parámetros:
 *   procod  — código de producto (obligatorio)
 *   format  — 'csv' para descarga CSV; cualquier otro valor = JSON con agregado
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

    // Cargar mapa de signos desde TIPMOVINV IGNORANDO TMINOAFECT.
    // El "default" para KARTIPO desconocido se aplica por columna (abajo).
    $pathTipo   = EnvLoader::resolveFileInDir('DBF_DIR', 'DBF_TIPMOVINV');
    $tipoSignos = TipMovInvLoader::loadSignMapVariant($pathTipo, false);

    $baseColumns = [
        'KARID', 'PROCOD', 'KARTIPO', 'KARDOCU', 'PRFCOD', 'SEDE',
        'KARFECHA', 'KARHORA', 'BODCOD',
        'KARCANT', 'KARVALOR', 'KARCOSTO',
        'KARCONSE', 'KARESTADO', 'EMPCOD', 'PROUND', 'TMIAUX',
    ];
    // Columna nueva con la cantidad con signo aplicado bajo la regla vigente:
    //   - CANT_DESC_NEG: KARTIPO desconocido → asume -1 (resta)
    //   (se ignora TMINOAFECT; sólo cuenta TMITIPO)
    $extraColumns = ['CANT_DESC_NEG'];
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

        // Calcular la columna con cantidad signada
        $cant    = (float)($row['KARCANT'] ?? 0);
        $kartipo = trim((string)($row['KARTIPO'] ?? ''));

        $signNeg = $tipoSignos[$kartipo] ?? -1;  // desconocidos → restan

        $line[] = round($signNeg * $cant, 4);

        fputcsv($out, $line);
    }

    fclose($out);
    exit;
}

// ============================================================
// Modo JSON — Existencia Total (regla: ignora TMINOAFECT, desc. RESTAN)
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
    // --- TIPMOVINV: mapa de signos ignorando TMINOAFECT ---
    $pathTipo       = EnvLoader::resolveFileInDir('DBF_DIR', 'DBF_TIPMOVINV');
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

    // --- Calcular la existencia: ignora TMINOAFECT + desconocidos RESTAN ---
    $existencia   = 0.0;
    $desconocidos = [];

    foreach ($allMatched as $row) {
        $cant    = (float)($row['KARCANT'] ?? 0);
        $kartipo = trim((string)($row['KARTIPO'] ?? ''));

        $s = $mapSinNoAfecta[$kartipo] ?? -1;
        $existencia += $s * $cant;

        if (!isset($mapSinNoAfecta[$kartipo])) {
            $desconocidos[$kartipo] = ($desconocidos[$kartipo] ?? 0) + 1;
        }
    }

    echo json_encode([
        'recordsFiltered' => $filtered,
        'existencia'      => round($existencia, 4),
        'debug' => [
            'registros_total'       => $filtered,
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
