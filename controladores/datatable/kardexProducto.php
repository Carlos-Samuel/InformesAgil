<?php
/**
 * Endpoint DataTables (server-side) para KARMOVI.DBF
 * filtrado por código de producto (PROCOD).
 *
 * La existencia se calcula cruzando KARTIPO con TIPMOVINV:
 *   - TMITIPO = '+' → el movimiento suma al inventario
 *   - TMITIPO = '-' → el movimiento resta del inventario
 *   - TMINOAFECT = true → el movimiento NO afecta inventario (se ignora)
 *
 * Parámetros POST:
 *   draw, start, length  — estándar DataTables
 *   procod               — código de producto a filtrar
 */
require_once '../EnvLoader.php';
require_once '../DbfReader.php';
require_once '../TipMovInvLoader.php';

header('Content-Type: application/json; charset=utf-8');

$draw   = (int)($_POST['draw']   ?? 1);
$procod = trim($_POST['procod']  ?? '');

if ($procod === '') {
    echo json_encode([
        'draw'            => $draw,
        'recordsTotal'    => 0,
        'recordsFiltered' => 0,
        'data'            => [],
        'existencia'      => 0,
        'error'           => 'Ingrese un código de producto.',
    ]);
    exit;
}

try {
    // ----------------------------------------------------------------
    // 1. Cargar TIPMOVINV: dos variantes del mapa de signos
    //    (una respetando TMINOAFECT, otra ignorándolo)
    // ----------------------------------------------------------------
    $pathTipo            = EnvLoader::resolveFileInDir('DBF_DIR', 'DBF_TIPMOVINV');
    $mapConNoAfecta      = TipMovInvLoader::loadSignMapVariant($pathTipo, true);
    $mapSinNoAfecta      = TipMovInvLoader::loadSignMapVariant($pathTipo, false);

    // ----------------------------------------------------------------
    // 2. Leer KARMOVI y traer TODAS las coincidencias (sin paginar)
    // ----------------------------------------------------------------
    $pathKar = EnvLoader::resolveFileInDir('DBF_DIR', 'DBF_KARMOVI');

    if (!$pathKar || !file_exists($pathKar)) {
        echo json_encode([
            'draw'            => $draw,
            'recordsTotal'    => 0,
            'recordsFiltered' => 0,
            'data'            => [],
            'existencia'      => 0,
            'error'           => 'Archivo KARMOVI.DBF no encontrado. Verifique la ruta en el archivo .env',
        ]);
        exit;
    }

    $reader = new DbfReader($pathKar, 'CP1252');
    $total  = $reader->getNumRecords();
    $start  = max(0, (int)($_POST['start']  ?? 0));
    $length = max(1, (int)($_POST['length'] ?? 50));

    $allMatched = $reader->findAllByField('PROCOD', $procod);
    $filtered   = count($allMatched);

    // ----------------------------------------------------------------
    // 3. Calcular las 4 variantes de Existencia Total
    //    Eje A: respetar TMINOAFECT (sí / no)
    //    Eje B: KARTIPO desconocido → suma (+1) / resta (-1)
    // ----------------------------------------------------------------
    $existNoafSumaDesc   = 0.0; // respeta TMINOAFECT + desconocidos SUMAN
    $existNoafRestaDesc  = 0.0; // respeta TMINOAFECT + desconocidos RESTAN
    $existNoNoafSumaDesc = 0.0; // ignora TMINOAFECT + desconocidos SUMAN
    $existNoNoafRestaDesc= 0.0; // ignora TMINOAFECT + desconocidos RESTAN

    $desconocidos        = [];  // KARTIPO vistos que no están en TIPMOVINV
    $totalNoAfecta       = 0;   // # registros cuyo KARTIPO tiene TMINOAFECT=true

    foreach ($allMatched as $row) {
        $cant    = (float)($row['KARCANT'] ?? 0);
        $kartipo = trim((string)($row['KARTIPO'] ?? ''));

        // Variante 1: respeta NoAfecta; desconocidos suman
        $s = $mapConNoAfecta[$kartipo] ?? 1;
        $existNoafSumaDesc   += $s * $cant;

        // Variante 2: respeta NoAfecta; desconocidos restan
        $s = $mapConNoAfecta[$kartipo] ?? -1;
        $existNoafRestaDesc  += $s * $cant;

        // Variante 3: ignora NoAfecta; desconocidos suman
        $s = $mapSinNoAfecta[$kartipo] ?? 1;
        $existNoNoafSumaDesc += $s * $cant;

        // Variante 4: ignora NoAfecta; desconocidos restan
        $s = $mapSinNoAfecta[$kartipo] ?? -1;
        $existNoNoafRestaDesc+= $s * $cant;

        // Diagnósticos
        if (!isset($mapConNoAfecta[$kartipo])) {
            $desconocidos[$kartipo] = ($desconocidos[$kartipo] ?? 0) + 1;
        } elseif (($mapConNoAfecta[$kartipo] ?? null) === 0) {
            $totalNoAfecta++;
        }
    }

    // ----------------------------------------------------------------
    // 4. Paginación para la tabla
    // ----------------------------------------------------------------
    $pageRows = array_slice($allMatched, $start, $length);

    $columns = [
        'KARID', 'PROCOD', 'KARTIPO', 'KARDOCU', 'PRFCOD', 'SEDE',
        'KARFECHA', 'KARHORA', 'BODCOD',
        'KARCANT', 'KARVALOR', 'KARCOSTO',
        'KARCONSE', 'KARESTADO', 'EMPCOD', 'PROUND', 'TMIAUX',
    ];

    $data = [];
    foreach ($pageRows as $row) {
        $item = [];
        foreach ($columns as $col) {
            $val = $row[$col] ?? null;
            if (is_bool($val)) {
                $item[] = $val ? 'Sí' : 'No';
            } elseif ($val === null) {
                $item[] = '';
            } else {
                $item[] = $val;
            }
        }
        $data[] = $item;
    }

    echo json_encode([
        'draw'            => $draw,
        'recordsTotal'    => $total,
        'recordsFiltered' => $filtered,
        'data'            => $data,
        // Existencia principal (regla oficial: respeta TMINOAFECT, desconocidos suman)
        'existencia'      => round($existNoafSumaDesc, 4),
        // Las 4 variantes para diagnóstico / comparación con el informe
        'existencias' => [
            'con_noafecta_desc_suma'  => round($existNoafSumaDesc,    4),
            'con_noafecta_desc_resta' => round($existNoafRestaDesc,   4),
            'sin_noafecta_desc_suma'  => round($existNoNoafSumaDesc,  4),
            'sin_noafecta_desc_resta' => round($existNoNoafRestaDesc, 4),
        ],
        'debug' => [
            'registros_total'       => $filtered,
            'registros_no_afecta'   => $totalNoAfecta,
            'kartipos_desconocidos' => $desconocidos, // ['XYZ' => 4, ...]
        ],
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    echo json_encode([
        'draw'            => $draw,
        'recordsTotal'    => 0,
        'recordsFiltered' => 0,
        'data'            => [],
        'existencia'      => 0,
        'error'           => $e->getMessage(),
    ]);
}
