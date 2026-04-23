<?php
/**
 * Endpoint de diagnóstico — valida si el desfase entre el informe
 * consolidado y el kardex tiene origen temporal o de formato de fecha.
 *
 * Para un PROCOD dado, recorre KARMOVI y entrega:
 *   - existencia total (sin ningún filtro de fecha — es lo que hacen
 *     hoy tanto el consolidado como el kardex).
 *   - existencia acumulada hasta una fecha de corte opcional
 *     (parámetro fechaCorte, formato YYYY-MM-DD, inclusive).
 *   - existencia restringida a un rango [fechaInicio, fechaFin]
 *     (ambos opcionales, inclusive).
 *   - desglose por año y por año-mes de KARFECHA (cantidad firmada
 *     según la regla "Ignora TMINOAFECT · desc. RESTAN").
 *   - muestra cruda de los primeros 10 registros con HEX de los
 *     campos KARFECHA tal como vienen del DBF, para detectar
 *     formatos raros (blancos, 00000000, AAAAMMDD, etc.).
 *
 * Uso:
 *   GET /controladores/datatable/compararExistenciasFecha.php
 *       ?procod=18 * 18 * 18 * X 50
 *       [&fechaCorte=2026-04-22]
 *       [&fechaInicio=2026-01-01&fechaFin=2026-04-22]
 */

require_once __DIR__ . '/../EnvLoader.php';
require_once __DIR__ . '/../DbfReader.php';
require_once __DIR__ . '/../TipMovInvLoader.php';

header('Content-Type: application/json; charset=utf-8');

$procod      = trim((string)($_REQUEST['procod']      ?? ''));
$fechaCorte  = trim((string)($_REQUEST['fechaCorte']  ?? ''));
$fechaInicio = trim((string)($_REQUEST['fechaInicio'] ?? ''));
$fechaFin    = trim((string)($_REQUEST['fechaFin']    ?? ''));

if ($procod === '') {
    echo json_encode(['error' => 'Falta el parámetro procod']);
    exit;
}

// Normaliza una fecha de corte a "YYYY-MM-DD" o devuelve null si el
// string no es parseable.
function toYmd(string $s): ?string {
    $s = trim($s);
    if ($s === '') return null;
    // Acepta YYYY-MM-DD, YYYY/MM/DD, YYYYMMDD.
    if (preg_match('/^(\d{4})\D?(\d{2})\D?(\d{2})$/', $s, $m)) {
        return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
    }
    $ts = strtotime($s);
    return $ts ? date('Y-m-d', $ts) : null;
}

$fechaCorteN  = toYmd($fechaCorte);
$fechaInicioN = toYmd($fechaInicio);
$fechaFinN    = toYmd($fechaFin);

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

    // Mapa de signos igual que ambos informes hoy (ignora TMINOAFECT).
    $tipoSignos = TipMovInvLoader::loadSignMapVariant($pathTipo, false);

    // Clave normalizada para comparar con el DBF (misma regla que findAllByField).
    $keyLower = mb_strtolower($procod, 'UTF-8');

    $reader = new DbfReader($pathKar, 'CP1252');
    $total  = $reader->getNumRecords();
    $batch  = 5000;

    $existTotal  = 0.0;
    $existCorte  = 0.0;
    $existRango  = 0.0;
    $countTotal  = 0;
    $countCorte  = 0;
    $countRango  = 0;
    $countSinFecha = 0;    // registros cuyo KARFECHA no se pudo parsear

    $porAnio     = [];     // [YYYY => ['count'=>int, 'sum'=>float]]
    $porAnioMes  = [];     // ["YYYY-MM" => ['count'=>int, 'sum'=>float]]

    $muestraRaw  = [];     // primeros 10 para inspeccionar el campo KARFECHA
    $muestraMax  = 10;

    // También leemos una muestra del KARMOVI "tal cual" para ver el tipo
    // de campo KARFECHA (dBase 'D' suele devolverse como AAAA-MM-DD ya formateado,
    // pero a veces viene en bruto).
    $fieldInfo = null;
    foreach ($reader->getFields() as $f) {
        if (strcasecmp($f['name'], 'KARFECHA') === 0) {
            $fieldInfo = $f;
            break;
        }
    }

    for ($i = 0; $i < $total; $i += $batch) {
        $rows = $reader->readRecords($i, min($batch, $total - $i));
        foreach ($rows as $row) {
            $raw = (string)($row['PROCOD'] ?? '');
            if ($raw === '') continue;

            $k = mb_strtolower(trim($raw), 'UTF-8');
            if ($k !== $keyLower) continue;

            $kartipo = trim((string)($row['KARTIPO'] ?? ''));
            $cant    = (float)($row['KARCANT'] ?? 0);
            $sign    = $tipoSignos[$kartipo] ?? -1;
            $signed  = $sign * $cant;

            $karfecha = $row['KARFECHA'] ?? null;   // normalmente YYYY-MM-DD (parseado por DbfReader)
            $karfechaStr = ($karfecha === null) ? '' : (string)$karfecha;

            // Capturar muestra cruda para inspección
            if (count($muestraRaw) < $muestraMax) {
                $muestraRaw[] = [
                    'karfecha_parsed' => $karfechaStr,
                    'karfecha_hex'    => strtoupper(bin2hex($karfechaStr)),
                    'karfecha_len'    => strlen($karfechaStr),
                    'karhora'         => (string)($row['KARHORA'] ?? ''),
                    'kartipo'         => $kartipo,
                    'kardocu'         => (string)($row['KARDOCU'] ?? ''),
                    'karcant'         => $cant,
                    'sign'            => $sign,
                    'signed'          => round($signed, 4),
                ];
            }

            // Acumulados
            $existTotal += $signed;
            $countTotal++;

            $anio = '';
            $mes  = '';
            if (preg_match('/^(\d{4})-(\d{2})-\d{2}$/', $karfechaStr, $m)) {
                $anio = $m[1];
                $mes  = $m[1] . '-' . $m[2];
            } else {
                $countSinFecha++;
            }

            if ($anio !== '') {
                if (!isset($porAnio[$anio]))    $porAnio[$anio]    = ['count' => 0, 'sum' => 0.0];
                if (!isset($porAnioMes[$mes]))  $porAnioMes[$mes]  = ['count' => 0, 'sum' => 0.0];
                $porAnio[$anio]['count']++;
                $porAnio[$anio]['sum']   += $signed;
                $porAnioMes[$mes]['count']++;
                $porAnioMes[$mes]['sum'] += $signed;
            }

            // Corte <= fechaCorte
            if ($fechaCorteN !== null && $karfechaStr !== '' && $karfechaStr <= $fechaCorteN) {
                $existCorte += $signed;
                $countCorte++;
            }

            // Rango fechaInicio..fechaFin (cualquiera de los extremos puede faltar)
            $inRango = true;
            if ($fechaInicioN !== null && ($karfechaStr === '' || $karfechaStr < $fechaInicioN)) $inRango = false;
            if ($fechaFinN    !== null && ($karfechaStr === '' || $karfechaStr > $fechaFinN))    $inRango = false;
            if ($fechaInicioN === null && $fechaFinN === null) $inRango = false; // si no se pide rango, no contamos
            if ($inRango) {
                $existRango += $signed;
                $countRango++;
            }
        }
    }

    // Ordenar los desgloses
    ksort($porAnio);
    ksort($porAnioMes);
    $porAnioArr    = [];
    $porAnioMesArr = [];
    foreach ($porAnio as $k => $v) {
        $porAnioArr[] = ['anio' => $k, 'count' => $v['count'], 'sum' => round($v['sum'], 4)];
    }
    foreach ($porAnioMes as $k => $v) {
        $porAnioMesArr[] = ['anio_mes' => $k, 'count' => $v['count'], 'sum' => round($v['sum'], 4)];
    }

    echo json_encode([
        'procod'          => $procod,
        'karfecha_field'  => $fieldInfo,
        'filtros' => [
            'fechaCorte'  => $fechaCorteN,
            'fechaInicio' => $fechaInicioN,
            'fechaFin'    => $fechaFinN,
        ],
        'totales' => [
            'sin_filtro'   => ['existencia' => round($existTotal, 4), 'registros' => $countTotal],
            'hasta_corte'  => ['existencia' => round($existCorte, 4), 'registros' => $countCorte],
            'en_rango'     => ['existencia' => round($existRango, 4), 'registros' => $countRango],
            'sin_fecha'    => $countSinFecha,
        ],
        'desglose_por_anio'      => $porAnioArr,
        'desglose_por_anio_mes'  => $porAnioMesArr,
        'muestra_primeros_10'    => $muestraRaw,
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
