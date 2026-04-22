<?php
/**
 * Endpoint de datos para KARMOVI.DBF — sin paginación server-side.
 * Devuelve TODOS los registros en un único JSON (la tabla se pagina en cliente).
 */
require_once '../EnvLoader.php';
require_once '../DbfReader.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $path = EnvLoader::resolveFileInDir('DBF_DIR', 'DBF_KARMOVI');

    if (!$path || !file_exists($path)) {
        echo json_encode([
            'data'  => [],
            'error' => 'Archivo KARMOVI.DBF no encontrado. Verifique la ruta en el archivo .env',
        ]);
        exit;
    }

    $reader = new DbfReader($path, 'CP1252');
    $total  = $reader->getNumRecords();

    // Columnas a mostrar (subconjunto de los 40 campos del archivo)
    $columns = [
        'KARID', 'PROCOD', 'KARTIPO', 'KARDOCU', 'PRFCOD', 'SEDE',
        'KARFECHA', 'KARHORA', 'BODCOD',
        'KARCANT', 'KARVALOR', 'KARCOSTO',
        'KARCONSE', 'KARESTADO', 'EMPCOD', 'PROUND', 'TMIAUX',
    ];

    // Leer TODO el archivo de una vez
    $rows = $reader->readRecords(0, $total);

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
        'data'  => $data,
        'total' => count($data),
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    echo json_encode([
        'data'  => [],
        'error' => $e->getMessage(),
    ]);
}
