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
    // 1. Cargar TIPMOVINV y construir mapa de signos
    //    $tipoSignos[TMICOD] = +1 (suma), -1 (resta) ó 0 (no afecta)
    // ----------------------------------------------------------------
    $tipoSignos = [];

    $pathTipo = EnvLoader::resolveFileInDir('DBF_DIR', 'DBF_TIPMOVINV');
    if ($pathTipo && file_exists($pathTipo)) {
        $readerTipo = new DbfReader($pathTipo, 'CP1252');
        $totalTipo  = $readerTipo->getNumRecords();
        $todosTipos = $readerTipo->readRecords(0, $totalTipo);

        foreach ($todosTipos as $t) {
            $cod       = trim((string)($t['TMICOD']     ?? ''));
            $tmitipo   = trim((string)($t['TMITIPO']    ?? ''));
            $noAfecta  = $t['TMINOAFECT'] ?? false; // campo lógico (bool)

            if ($cod === '') continue;

            if ($noAfecta === true) {
                $tipoSignos[$cod] = 0;  // no afecta inventario
            } elseif ($tmitipo === '+') {
                $tipoSignos[$cod] = 1;  // entrada → suma
            } elseif ($tmitipo === '-') {
                $tipoSignos[$cod] = -1; // salida → resta
            } else {
                $tipoSignos[$cod] = 1;  // desconocido → asumir suma
            }
        }
    }

    // Closure que devuelve el signo de un registro de KARMOVI
    $signCallback = function (array $row) use ($tipoSignos): int {
        $kartipo = trim((string)($row['KARTIPO'] ?? ''));
        return $tipoSignos[$kartipo] ?? 1; // si no se encuentra, asumir +1
    };

    // ----------------------------------------------------------------
    // 2. Leer y filtrar KARMOVI
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

    $filtered   = 0;
    $existencia = 0.0;

    $rows = $reader->filterByField(
        'PROCOD',
        $procod,
        $start,
        $length,
        $filtered,
        'KARCANT',
        $existencia,
        $signCallback   // ← aplica el signo correcto por tipo de movimiento
    );

    // ----------------------------------------------------------------
    // 3. Armar respuesta
    // ----------------------------------------------------------------
    $columns = [
        'KARID', 'PROCOD', 'KARTIPO', 'KARDOCU', 'PRFCOD', 'SEDE',
        'KARFECHA', 'KARHORA', 'BODCOD',
        'KARCANT', 'KARVALOR', 'KARCOSTO',
        'KARCONSE', 'KARESTADO', 'EMPCOD', 'PROUND', 'TMIAUX',
    ];

    $data = [];
    foreach ($rows as $row) {
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
        'existencia'      => round($existencia, 4),
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
