<?php
/**
 * Endpoint que escanea KARMOVI.DBF COMPLETO una sola vez y calcula
 * las existencias de TODOS los productos agrupadas por PROCOD.
 *
 * Regla de cálculo (misma del informe de Kardex por Producto):
 *   - Se IGNORA el campo TMINOAFECT (los movimientos marcados como
 *     "no afectan" también se suman/restan).
 *   - KARTIPO desconocido (no presente en TIPMOVINV) → se RESTA (-1).
 *
 * Respuesta JSON:
 *   {
 *     "data": [
 *        { "procod": "000001", "existencia": 12.5, "movimientos": 34 },
 *        ...
 *     ],
 *     "debug": { registros_karmovi, productos_total,
 *                kartipos_desconocidos_global }
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
    // 1. Cargar mapa de signos desde TIPMOVINV (ignorando TMINOAFECT)
    // ------------------------------------------------------------
    $pathTipo = EnvLoader::resolveFileInDir('DBF_DIR', 'DBF_TIPMOVINV');
    if (!$pathTipo || !file_exists($pathTipo)) {
        echo json_encode([
            'data'  => [],
            'error' => 'TIPMOVINV.DBF no encontrado. Verifique el .env',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $tipoSignos = TipMovInvLoader::loadSignMapVariant($pathTipo, false);

    // ------------------------------------------------------------
    // 2. Abrir KARMOVI.DBF y recorrerlo en lotes
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

    // Acumuladores por PROCOD normalizado (misma normalización que
    // DbfReader::findAllByField usada por el Kardex por Producto:
    // mb_strtolower + trim). Sin esto, registros con distinta
    // capitalización del mismo código quedarían en filas separadas
    // y los totales no cuadrarían con el Kardex.
    $existencias          = []; // [procodKey => float existencia acumulada]
    $movimientosPorProd   = []; // [procodKey => int registros sumados]
    $procodDisplay        = []; // [procodKey => PROCOD tal como se mostrará (trim del primer raw visto)]
    $kartiposDesconocidos = []; // [kartipo => cantidad global]

    $t0 = microtime(true);

    for ($i = 0; $i < $total; $i += $batchSize) {
        $rows = $reader->readRecords($i, min($batchSize, $total - $i));

        foreach ($rows as $row) {
            $procodRaw = trim((string)($row['PROCOD'] ?? ''));
            if ($procodRaw === '') continue;

            // Clave de agrupación idéntica a la que usa el Kardex:
            $procodKey = mb_strtolower($procodRaw, 'UTF-8');

            $cant    = (float)($row['KARCANT'] ?? 0);
            $kartipo = trim((string)($row['KARTIPO'] ?? ''));

            // Regla: KARTIPO desconocido → resta (-1). Se ignora TMINOAFECT.
            $sign = $tipoSignos[$kartipo] ?? -1;

            if (!isset($existencias[$procodKey])) {
                $existencias[$procodKey]        = 0.0;
                $movimientosPorProd[$procodKey] = 0;
                $procodDisplay[$procodKey]      = $procodRaw;
            }
            $existencias[$procodKey]        += $sign * $cant;
            $movimientosPorProd[$procodKey] += 1;

            if (!isset($tipoSignos[$kartipo])) {
                $key = $kartipo === '' ? '(VACIO)' : $kartipo;
                $kartiposDesconocidos[$key] =
                    ($kartiposDesconocidos[$key] ?? 0) + 1;
            }
        }
    }

    // ------------------------------------------------------------
    // 3. Pasar a arreglo plano y ordenar ASC por PROCOD
    // ------------------------------------------------------------
    $salida = [];
    foreach ($existencias as $procodKey => $valor) {
        $salida[] = [
            'procod'      => $procodDisplay[$procodKey],
            'existencia'  => round($valor, 4),
            'movimientos' => $movimientosPorProd[$procodKey],
        ];
    }

    // Orden natural (maneja bien códigos alfanuméricos: "9" < "10")
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
