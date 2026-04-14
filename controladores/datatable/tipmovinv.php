<?php
/**
 * Endpoint DataTables (server-side) para TIPMOVINV.DBF
 *
 * Parámetros POST estándar de DataTables:
 *   draw, start, length, search[value]
 */
require_once '../EnvLoader.php';
require_once '../DbfReader.php';

header('Content-Type: application/json; charset=utf-8');

$draw = (int)($_POST['draw'] ?? 1);

try {
    $path = EnvLoader::resolvePath('DBF_TIPMOVINV');

    if (!$path || !file_exists($path)) {
        echo json_encode([
            'draw'            => $draw,
            'recordsTotal'    => 0,
            'recordsFiltered' => 0,
            'data'            => [],
            'error'           => 'Archivo TIPMOVINV.DBF no encontrado. Verifique la ruta en el archivo .env',
        ]);
        exit;
    }

    $reader  = new DbfReader($path, 'CP1252');
    $total   = $reader->getNumRecords();
    $start   = max(0, (int)($_POST['start']  ?? 0));
    $length  = max(1, (int)($_POST['length'] ?? 50));
    $search  = trim($_POST['search']['value'] ?? '');

    // Todos los campos del archivo
    $columns = [
        'TMICOD', 'TMINOM', 'TMITIPO',
        'TMIINTERNO', 'TMIULTIMO', 'TMICONSE',
        'PLNCOD', 'TMINOCONTA', 'TMICTO', 'TMINOAFECT',
        'TMIPRODUC', 'TMIMERMA', 'TMITER', 'TMIINACT',
        'TMIGENIVA', 'TMIGENIC', 'PLNCODIVA', 'TMIPORC', 'PLNCODNIIF',
    ];

    if ($search !== '') {
        $filtered = 0;
        $rows     = $reader->searchRecords($search, $start, $length, $filtered);
    } else {
        $filtered = $total;
        $rows     = $reader->readRecords($start, $length);
    }

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
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    echo json_encode([
        'draw'            => $draw,
        'recordsTotal'    => 0,
        'recordsFiltered' => 0,
        'data'            => [],
        'error'           => $e->getMessage(),
    ]);
}
