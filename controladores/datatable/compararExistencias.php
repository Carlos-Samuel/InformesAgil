<?php
/**
 * Endpoint de diagnóstico — compara el cálculo de existencias del
 * informe consolidado (normalización strtoupper + trim) con el del
 * kardex (mb_strtolower + trim vía findAllByField) para un mismo PROCOD.
 *
 * Devuelve también el HEX y la longitud en bytes de cada PROCOD
 * encontrado, para detectar caracteres invisibles que expliquen
 * por qué los dos informes no cuadran.
 *
 * Uso:
 *   GET /controladores/datatable/compararExistencias.php?procod=18 * 18 * 18 * X 50
 *
 * Respuesta: JSON con:
 *   input_raw_hex            → HEX de lo que llegó en $_REQUEST['procod']
 *   input_after_trim_hex     → HEX tras trim()
 *   sales                    → { existencia, matched_keys: [{procod, hex, len, count, sum}] }
 *   kardex                   → { existencia, matched: [{procod, hex, len, kartipo, cant, sign}] (hasta 50) }
 *   distintos_sales_vs_kardex → true si los dos totales no coinciden
 */

require_once __DIR__ . '/../EnvLoader.php';
require_once __DIR__ . '/../DbfReader.php';
require_once __DIR__ . '/../TipMovInvLoader.php';

header('Content-Type: application/json; charset=utf-8');

$procodInput = (string)($_REQUEST['procod'] ?? '');
$procod      = trim($procodInput);

if ($procod === '') {
    echo json_encode(['error' => 'Falta el parámetro procod']);
    exit;
}

try {
    $pathTipo = EnvLoader::resolveFileInDir('DBF_DIR', 'DBF_TIPMOVINV');
    $pathKar  = EnvLoader::resolveFileInDir('DBF_DIR', 'DBF_KARMOVI');

    if (!$pathTipo || !file_exists($pathTipo)) {
        echo json_encode(['error' => 'TIPMOVINV.DBF no encontrado']);
        exit;
    }
    if (!$pathKar || !file_exists($pathKar)) {
        echo json_encode(['error' => 'KARMOVI.DBF no encontrado']);
        exit;
    }

    // Mismo mapa que usan los dos informes (ignora TMINOAFECT).
    $tipoSignos = TipMovInvLoader::loadSignMapVariant($pathTipo, false);

    // Claves normalizadas al estilo de cada informe
    $keySales  = strtoupper($procod);                      // igual que informeVentasProductoConsolidado
    $keyKardex = mb_strtolower($procod, 'UTF-8');          // igual que DbfReader::findAllByField

    $reader = new DbfReader($pathKar, 'CP1252');
    $total  = $reader->getNumRecords();
    $batch  = 5000;

    // Acumuladores al estilo "informe consolidado" (agrupado por strtoupper(trim()))
    $salesAgg = []; // key normalized → ['procod'=>raw ejemplo, 'hex'=>, 'count'=>, 'sum'=>]

    // Acumulador al estilo "kardex" (todas las coincidencias case-insensitive con el input)
    $kardexMatches = [];
    $kardexSum     = 0.0;

    for ($i = 0; $i < $total; $i += $batch) {
        $rows = $reader->readRecords($i, min($batch, $total - $i));
        foreach ($rows as $row) {
            $raw  = (string)($row['PROCOD'] ?? '');
            if ($raw === '') continue;

            $kartipo = trim((string)($row['KARTIPO'] ?? ''));
            $cant    = (float)($row['KARCANT'] ?? 0);
            $sign    = $tipoSignos[$kartipo] ?? -1;

            // --- Ruta "ventas consolidado" ---
            $kSales = strtoupper(trim($raw));
            if ($kSales === $keySales) {
                if (!isset($salesAgg[$kSales])) {
                    $salesAgg[$kSales] = [
                        'procod_ejemplo' => $raw,
                        'hex_ejemplo'    => strtoupper(bin2hex($raw)),
                        'len_ejemplo'    => strlen($raw),
                        'count'          => 0,
                        'sum'            => 0.0,
                    ];
                }
                $salesAgg[$kSales]['count']++;
                $salesAgg[$kSales]['sum'] += $sign * $cant;
            }

            // --- Ruta "kardex" (igual que findAllByField) ---
            $kKardex = mb_strtolower(trim($raw), 'UTF-8');
            if ($kKardex === $keyKardex) {
                $kardexSum += $sign * $cant;
                if (count($kardexMatches) < 50) {
                    $kardexMatches[] = [
                        'procod'  => $raw,
                        'hex'     => strtoupper(bin2hex($raw)),
                        'len'     => strlen($raw),
                        'kartipo' => $kartipo,
                        'cant'    => $cant,
                        'sign'    => $sign,
                    ];
                }
            }
        }
    }

    // Resumen ventas
    $salesTotal = 0.0;
    $salesKeys  = [];
    foreach ($salesAgg as $k => $v) {
        $salesKeys[] = [
            'procod_ejemplo' => $v['procod_ejemplo'],
            'hex'            => $v['hex_ejemplo'],
            'len'            => $v['len_ejemplo'],
            'count'          => $v['count'],
            'sum'            => round($v['sum'], 4),
        ];
        $salesTotal += $v['sum'];
    }

    echo json_encode([
        'input_raw'              => $procodInput,
        'input_raw_hex'          => strtoupper(bin2hex($procodInput)),
        'input_raw_len'          => strlen($procodInput),
        'input_after_trim'       => $procod,
        'input_after_trim_hex'   => strtoupper(bin2hex($procod)),
        'input_after_trim_len'   => strlen($procod),
        'key_sales_upper'        => $keySales,
        'key_kardex_lower'       => $keyKardex,
        'sales' => [
            'existencia'     => round($salesTotal, 4),
            'matched_groups' => $salesKeys,
        ],
        'kardex' => [
            'existencia'    => round($kardexSum, 4),
            'match_count'   => count($kardexMatches),
            'primeros_50'   => $kardexMatches,
        ],
        'distintos_sales_vs_kardex' => round($salesTotal, 4) !== round($kardexSum, 4),
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
