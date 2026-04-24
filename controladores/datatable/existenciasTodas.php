<?php
/**
 * Endpoint que calcula las existencias de TODOS los productos presentes
 * en KARMOVI.DBF, leyendo el archivo UNA sola vez y acumulando por
 * PROCOD. Para cada registro aplica EXACTAMENTE el mismo bloque de
 * suma/resta que usa el informe de Kardex por Producto
 * (kardexProducto.php, rama JSON):
 *
 *     $cant    = (float)($row['KARCANT'] ?? 0);
 *     $kartipo = trim((string)($row['KARTIPO'] ?? ''));
 *     $s = $mapSinNoAfecta[$kartipo] ?? -1;
 *     $existencia += $s * $cant;
 *
 * Regla de cálculo (idéntica a kardexProducto):
 *   - Se IGNORA el campo TMINOAFECT (los movimientos marcados como
 *     "no afectan" también se suman/restan).
 *   - KARTIPO desconocido (no presente en TIPMOVINV) → se RESTA (-1).
 *
 * Agrupación de PROCOD: se usa la misma normalización que
 * DbfReader::findAllByField (la que emplea el Kardex por Producto),
 * es decir mb_strtolower(trim(...), 'UTF-8'). Así, si el mismo código
 * aparece con distinta capitalización en KARMOVI se acumula en una
 * única fila — exactamente como lo hace el Kardex cuando se consulta
 * ese código.
 *
 * Respuesta JSON:
 *   {
 *     "data": [
 *        { "procod": "000001", "existencia": 12.5, "movimientos": 34 },
 *        ...
 *     ],
 *     "debug": { registros_karmovi, productos_total,
 *                kartipos_desconocidos_global, tiempo_ms }
 *   }
 *
 * Los productos vienen ORDENADOS ASCENDENTEMENTE por PROCOD.
 */

require_once __DIR__ . '/../EnvLoader.php';
require_once __DIR__ . '/../DbfReader.php';
require_once __DIR__ . '/../TipMovInvLoader.php';

// Este endpoint puede tardar varios segundos — asegurar tiempo suficiente
@set_time_limit(600);
@ini_set('memory_limit', '1024M');

header('Content-Type: application/json; charset=utf-8');

try {
    // ------------------------------------------------------------
    // 1. TIPMOVINV: mapa de signos ignorando TMINOAFECT
    //    (idéntico a kardexProducto.php)
    // ------------------------------------------------------------
    $pathTipo = EnvLoader::resolveFileInDir('DBF_DIR', 'DBF_TIPMOVINV');
    if (!$pathTipo || !file_exists($pathTipo)) {
        echo json_encode([
            'data'  => [],
            'error' => 'TIPMOVINV.DBF no encontrado. Verifique el .env',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $mapSinNoAfecta = TipMovInvLoader::loadSignMapVariant($pathTipo, false);

    // ------------------------------------------------------------
    // 2. KARMOVI: abrir el lector
    // ------------------------------------------------------------
    $pathKar = EnvLoader::resolveFileInDir('DBF_DIR', 'DBF_KARMOVI');
    if (!$pathKar || !file_exists($pathKar)) {
        echo json_encode([
            'data'  => [],
            'error' => 'KARMOVI.DBF no encontrado. Verifique el .env',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $reader    = new DbfReader($pathKar, 'CP1252');
    $total     = $reader->getNumRecords();
    $batchSize = 5000;

    // Acumuladores por PROCOD normalizado
    $existencias          = []; // [procodKey => float existencia acumulada]
    $movimientosPorProd   = []; // [procodKey => int registros sumados]
    $procodDisplay        = []; // [procodKey => PROCOD tal como se mostrará]
    $kartiposDesconocidos = []; // [kartipo => cantidad global]

    $t0 = microtime(true);

    // ------------------------------------------------------------
    // 3. UNA sola pasada por KARMOVI, en lotes.
    //    Para cada fila se aplica el MISMO bloque de suma/resta
    //    que usa kardexProducto.php.
    // ------------------------------------------------------------
    for ($i = 0; $i < $total; $i += $batchSize) {
        $rows = $reader->readRecords($i, min($batchSize, $total - $i));

        foreach ($rows as $row) {
            $procodRaw = trim((string)($row['PROCOD'] ?? ''));
            if ($procodRaw === '') continue;

            // Misma normalización que DbfReader::findAllByField
            $procodKey = mb_strtolower($procodRaw, 'UTF-8');

            // --- Bloque idéntico a kardexProducto.php ---
            $cant    = (float)($row['KARCANT'] ?? 0);
            $kartipo = trim((string)($row['KARTIPO'] ?? ''));

            $s = $mapSinNoAfecta[$kartipo] ?? -1;
            // --------------------------------------------

            if (!isset($existencias[$procodKey])) {
                $existencias[$procodKey]        = 0.0;
                $movimientosPorProd[$procodKey] = 0;
                $procodDisplay[$procodKey]      = $procodRaw;
            }
            $existencias[$procodKey]        += $s * $cant;
            $movimientosPorProd[$procodKey] += 1;

            if (!isset($mapSinNoAfecta[$kartipo])) {
                $key = $kartipo === '' ? '(VACIO)' : $kartipo;
                $kartiposDesconocidos[$key] =
                    ($kartiposDesconocidos[$key] ?? 0) + 1;
            }
        }
    }

    // ------------------------------------------------------------
    // 4. Pasar a arreglo plano y ordenar ASC por PROCOD
    //    (orden natural: "9" < "10")
    // ------------------------------------------------------------
    $salida = [];
    foreach ($existencias as $procodKey => $valor) {
        $salida[] = [
            'procod'      => $procodDisplay[$procodKey],
            'existencia'  => round($valor, 4),
            'movimientos' => $movimientosPorProd[$procodKey],
        ];
    }

    usort($salida, function ($a, $b) {
        return strnatcasecmp($a['procod'], $b['procod']);
    });

    $t1 = microtime(true);

    echo json_encode([
        'data' => $salida,
        'debug' => [
            'registros_karmovi'            => $total,
            'productos_total'              => count($salida),
            'kartipos_desconocidos_global' => $kartiposDesconocidos,
            'tiempo_ms'                    => (int)(($t1 - $t0) * 1000),
        ],
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    echo json_encode([
        'data'  => [],
        'error' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}
