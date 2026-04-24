<?php
/**
 * Endpoint que devuelve el contenido COMPLETO de KARMOVI.DBF
 * "tal cual", registro por registro, sin cálculos, sin filtros,
 * sin paginación — como si fuera el volcado de un CSV.
 *
 * Respuesta JSON:
 *   {
 *     "columns": ["KARID","PROCOD","KARTIPO",...],  // nombres tal cual del DBF
 *     "data":    [ [v1,v2,v3,...], [v1,v2,v3,...], ... ],
 *     "debug":   { registros_karmovi, tiempo_ms }
 *   }
 *
 * Se devuelven los registros en el orden FÍSICO del archivo (el mismo
 * orden en que están almacenados en KARMOVI.DBF). Los registros
 * marcados como eliminados (primer byte '*') se omiten.
 */

require_once __DIR__ . '/../EnvLoader.php';
require_once __DIR__ . '/../DbfReader.php';

// El archivo puede ser grande — subir límites
@set_time_limit(600);
@ini_set('memory_limit', '2048M');

header('Content-Type: application/json; charset=utf-8');

try {
    $pathKar = EnvLoader::resolveFileInDir('DBF_DIR', 'DBF_KARMOVI');
    if (!$pathKar || !file_exists($pathKar)) {
        echo json_encode([
            'columns' => [],
            'data'    => [],
            'error'   => 'KARMOVI.DBF no encontrado. Verifique el .env',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $reader    = new DbfReader($pathKar, 'CP1252');
    $total     = $reader->getNumRecords();
    $batchSize = 5000;

    // Nombres de los campos tal como están en la cabecera del DBF
    $columns = $reader->getFieldNames();

    $t0 = microtime(true);

    // Acumular filas como arreglos indexados (más liviano en JSON que
    // un arreglo de objetos cuando son miles de registros).
    $data = [];

    for ($i = 0; $i < $total; $i += $batchSize) {
        $rows = $reader->readRecords($i, min($batchSize, $total - $i));
        foreach ($rows as $row) {
            $fila = [];
            foreach ($columns as $col) {
                $v = $row[$col] ?? null;
                if (is_bool($v))     $v = $v ? 'Si' : 'No';
                elseif ($v === null) $v = '';
                $fila[] = $v;
            }
            $data[] = $fila;
        }
    }

    $t1 = microtime(true);

    echo json_encode([
        'columns' => $columns,
        'data'    => $data,
        'debug'   => [
            'registros_karmovi' => $total,
            'filas_devueltas'   => count($data),
            'tiempo_ms'         => (int)(($t1 - $t0) * 1000),
        ],
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    echo json_encode([
        'columns' => [],
        'data'    => [],
        'error'   => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
