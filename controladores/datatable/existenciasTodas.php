<?php
/**
 * Endpoint que calcula las existencias de TODOS los productos presentes
 * en KARMOVI.DBF, usando EXACTAMENTE la misma mecánica de recorrido,
 * agrupación y cálculo que el informe de Kardex por Producto
 * (kardexProducto.php, rama JSON):
 *
 *   1. Se obtiene la lista de PROCODs distintos presentes en KARMOVI.
 *   2. Para cada PROCOD se ejecuta el mismo bloque que kardexProducto:
 *         $allMatched = $reader->findAllByField('PROCOD', $procod);
 *         foreach ($allMatched as $row) {
 *             $cant    = (float)($row['KARCANT'] ?? 0);
 *             $kartipo = trim((string)($row['KARTIPO'] ?? ''));
 *             $s = $mapSinNoAfecta[$kartipo] ?? -1;
 *             $existencia += $s * $cant;
 *             ...
 *         }
 *
 * Regla de cálculo (idéntica a kardexProducto):
 *   - Se IGNORA el campo TMINOAFECT (los movimientos marcados como
 *     "no afectan" también se suman/restan).
 *   - KARTIPO desconocido (no presente en TIPMOVINV) → se RESTA (-1).
 *
 * NOTA DE RENDIMIENTO: como findAllByField recorre el DBF completo en
 * cada llamada, este endpoint hace N+1 escaneos de KARMOVI (uno para
 * recolectar los PROCODs distintos y uno por cada producto). Para
 * catálogos grandes puede ser notablemente más lento que una sola
 * pasada por lotes; se deja así para que la lógica sea 100 % igual a
 * la del Kardex por Producto.
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

    $reader = new DbfReader($pathKar, 'CP1252');
    $total  = $reader->getNumRecords();

    $t0 = microtime(true);

    // ------------------------------------------------------------
    // 3. Recolectar los PROCODs distintos presentes en KARMOVI.
    //    Se conserva la PRIMERA forma "tal cual" vista (trim) para
    //    mostrarla al usuario, y se usa como clave de búsqueda el
    //    PROCOD sin normalizar porque findAllByField ya hace
    //    mb_strtolower + trim internamente para comparar.
    // ------------------------------------------------------------
    $procodDisplay = []; // [procodKey => PROCOD tal como se mostrará]
    $batchSize     = 5000;

    for ($i = 0; $i < $total; $i += $batchSize) {
        $rows = $reader->readRecords($i, min($batchSize, $total - $i));
        foreach ($rows as $row) {
            $procodRaw = trim((string)($row['PROCOD'] ?? ''));
            if ($procodRaw === '') continue;
            $procodKey = mb_strtolower($procodRaw, 'UTF-8');
            if (!isset($procodDisplay[$procodKey])) {
                $procodDisplay[$procodKey] = $procodRaw;
            }
        }
    }

    // ------------------------------------------------------------
    // 4. Para cada PROCOD ejecutar EXACTAMENTE el mismo bloque que
    //    kardexProducto.php (rama JSON).
    // ------------------------------------------------------------
    $salida               = [];
    $kartiposDesconocidos = []; // acumulado global

    foreach ($procodDisplay as $procodKey => $procod) {

        // --- KARMOVI: traer todas las coincidencias ---
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

        $salida[] = [
            'procod'      => $procod,
            'existencia'  => round($existencia, 4),
            'movimientos' => $filtered,
        ];

        // Acumular desconocidos a nivel global para el debug
        foreach ($desconocidos as $kt => $n) {
            $key = $kt === '' ? '(VACIO)' : $kt;
            $kartiposDesconocidos[$key] = ($kartiposDesconocidos[$key] ?? 0) + $n;
        }
    }

    // ------------------------------------------------------------
    // 5. Ordenar ASC por PROCOD (orden natural, maneja "9" < "10")
    // ------------------------------------------------------------
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
