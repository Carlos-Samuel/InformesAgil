<?php
/**
 * TipMovInvLoader — Carga TIPMOVINV.DBF y construye un mapa
 *                   de signos por código (TMICOD), aplicando
 *                   consistentemente la regla de negocio usada
 *                   en todos los informes.
 *
 *   $tipoSignos[TMICOD]:
 *     +1  → el movimiento SUMA al inventario   (TMITIPO = '+')
 *     -1  → el movimiento RESTA del inventario (TMITIPO = '-')
 *      0  → el movimiento NO AFECTA inventario (TMINOAFECT = true)
 *
 *   KARTIPO desconocido (no aparece en TIPMOVINV) → se asume +1
 *   (misma convención que el informe de Kardex).
 */

require_once __DIR__ . '/DbfReader.php';

class TipMovInvLoader {

    /**
     * Construye el mapa [TMICOD => signo].
     *
     * @param string $pathTipMovInv Ruta absoluta al archivo TIPMOVINV.DBF
     * @param int    $batchSize     Tamaño de lote para lectura
     * @return array<string,int>
     */
    public static function loadSignMap(string $pathTipMovInv, int $batchSize = 2000): array {
        $map = [];

        if (!is_readable($pathTipMovInv)) {
            return $map;
        }

        $reader = new DbfReader($pathTipMovInv, 'CP1252');
        $total  = $reader->getNumRecords();

        for ($i = 0; $i < $total; $i += $batchSize) {
            $chunk = $reader->readRecords($i, min($batchSize, $total - $i));
            foreach ($chunk as $row) {
                $cod = trim((string)($row['TMICOD'] ?? ''));
                if ($cod === '') continue;

                $noAfecta = $row['TMINOAFECT'] ?? false; // campo lógico
                if ($noAfecta === true) {
                    $map[$cod] = 0;                      // NO afecta inventario
                    continue;
                }

                $tmitipo = trim((string)($row['TMITIPO'] ?? ''));
                if ($tmitipo === '+') {
                    $map[$cod] = 1;
                } elseif ($tmitipo === '-') {
                    $map[$cod] = -1;
                } else {
                    $map[$cod] = 1;  // TMITIPO desconocido → asumir suma
                }
            }
        }

        return $map;
    }

    /**
     * Devuelve el signo a aplicar a un registro de KARMOVI dado
     * el mapa previamente construido. Si KARTIPO no existe en el
     * mapa se asume +1.
     */
    public static function signFor(array $signMap, array $karmoviRow): int {
        $kartipo = trim((string)($karmoviRow['KARTIPO'] ?? ''));
        return $signMap[$kartipo] ?? 1;
    }

    /**
     * Variante configurable — útil para comparar reglas de negocio.
     *
     * @param bool $respetarNoAfecta Si true, TMINOAFECT=true → signo 0.
     *                               Si false, se ignora ese campo y se usa sólo TMITIPO.
     */
    public static function loadSignMapVariant(
        string $pathTipMovInv,
        bool   $respetarNoAfecta,
        int    $batchSize = 2000
    ): array {
        $map = [];

        if (!is_readable($pathTipMovInv)) {
            return $map;
        }

        $reader = new DbfReader($pathTipMovInv, 'CP1252');
        $total  = $reader->getNumRecords();

        for ($i = 0; $i < $total; $i += $batchSize) {
            $chunk = $reader->readRecords($i, min($batchSize, $total - $i));
            foreach ($chunk as $row) {
                $cod = trim((string)($row['TMICOD'] ?? ''));
                if ($cod === '') continue;

                $noAfecta = $row['TMINOAFECT'] ?? false;
                if ($respetarNoAfecta && $noAfecta === true) {
                    $map[$cod] = 0;
                    continue;
                }

                $tmitipo = trim((string)($row['TMITIPO'] ?? ''));
                if ($tmitipo === '+') {
                    $map[$cod] = 1;
                } elseif ($tmitipo === '-') {
                    $map[$cod] = -1;
                } else {
                    $map[$cod] = 1;
                }
            }
        }

        return $map;
    }
}
