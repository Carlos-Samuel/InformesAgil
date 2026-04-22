<?php
/**
 * DbfReader — lector de archivos .DBF compatibles con dBase III/IV y
 * Visual FoxPro (versión 0x30).
 *
 * Tipos de campo soportados:
 *   C  – Character
 *   N  – Numeric
 *   F  – Float
 *   L  – Logical
 *   D  – Date
 *   T  – DateTime  (VFP: 4 bytes Julian Day + 4 bytes milisegundos)
 *   I  – Integer   (VFP: 4 bytes little-endian con signo)
 *   Y  – Currency  (VFP: 8 bytes little-endian / 10 000)
 *   M  – Memo      (se devuelve el número de bloque)
 */
class DbfReader {

    private string $path;
    private string $encoding;
    private array  $fields      = [];
    private int    $numRecords  = 0;
    private int    $headerSize  = 0;
    private int    $recordSize  = 0;

    // ----------------------------------------------------------------
    // Constructor
    // ----------------------------------------------------------------

    /**
     * @param string $path     Ruta absoluta al archivo .DBF.
     * @param string $encoding Codificación de origen (por defecto CP1252).
     */
    public function __construct(string $path, string $encoding = 'CP1252') {
        if (!is_readable($path)) {
            throw new RuntimeException("No se puede leer el archivo DBF: $path");
        }
        $this->path     = $path;
        $this->encoding = $encoding;
        $this->parseHeader();
    }

    // ----------------------------------------------------------------
    // Interfaz pública
    // ----------------------------------------------------------------

    /** Devuelve la lista de descriptores de campo. */
    public function getFields(): array {
        return $this->fields;
    }

    /** Devuelve los nombres de los campos. */
    public function getFieldNames(): array {
        return array_column($this->fields, 'name');
    }

    /** Devuelve el número total de registros (incluye eliminados). */
    public function getNumRecords(): int {
        return $this->numRecords;
    }

    /**
     * Lee un rango de registros usando fseek (O(1) para la posición inicial).
     *
     * @param int $start  Índice 0-based del primer registro a leer.
     * @param int $length Máximo de registros a devolver.
     * @return array[]
     */
    public function readRecords(int $start = 0, int $length = 50): array {
        $fh = $this->openFile();

        $start  = max(0, $start);
        $length = max(1, $length);

        fseek($fh, $this->headerSize + $start * $this->recordSize);

        $rows  = [];
        $read  = 0;
        $index = $start;

        while ($read < $length && $index < $this->numRecords) {
            $raw = fread($fh, $this->recordSize);
            if (strlen($raw) < $this->recordSize) break;
            $index++;

            if ($raw[0] === '*') continue; // registro marcado como eliminado

            $row = $this->decodeRecord($raw);
            if ($row !== null) {
                $rows[] = $row;
                $read++;
            }
        }

        fclose($fh);
        return $rows;
    }

    /**
     * Escanea todo el archivo filtrando por un término de búsqueda.
     * Devuelve la página solicitada y actualiza $totalFiltered por referencia.
     *
     * Nota: para archivos grandes (> 100 000 registros) esta operación puede
     * tardar varios segundos porque recorre todos los registros.
     *
     * @param string $search        Texto a buscar (case-insensitive).
     * @param int    $start         Índice 0-based dentro de los resultados filtrados.
     * @param int    $length        Máximo de registros a devolver.
     * @param int    $totalFiltered [out] Total de registros que coinciden con la búsqueda.
     * @return array[]
     */
    public function searchRecords(
        string $search,
        int    $start,
        int    $length,
        int    &$totalFiltered
    ): array {
        $fh     = $this->openFile();
        $search = mb_strtolower($search, 'UTF-8');

        fseek($fh, $this->headerSize);

        $allMatched = [];

        for ($i = 0; $i < $this->numRecords; $i++) {
            $raw = fread($fh, $this->recordSize);
            if (strlen($raw) < $this->recordSize) break;
            if ($raw[0] === '*') continue;

            $row = $this->decodeRecord($raw);
            if ($row === null) continue;

            foreach ($row as $val) {
                if ($val !== null && mb_stripos((string)$val, $search, 0, 'UTF-8') !== false) {
                    $allMatched[] = $row;
                    break;
                }
            }
        }

        fclose($fh);

        $totalFiltered = count($allMatched);
        return array_slice($allMatched, $start, $length);
    }

    /**
     * Filtra registros por coincidencia exacta en un campo específico.
     * Opcionalmente acumula la suma de otro campo numérico con signo variable.
     *
     * @param string        $fieldName     Campo por el que filtrar (ej. 'PROCOD').
     * @param string        $value         Valor exacto a buscar (case-insensitive).
     * @param int           $start         Índice 0-based dentro de los resultados filtrados.
     * @param int           $length        Máximo de registros a devolver.
     * @param int           $totalFiltered [out] Total de registros que coinciden.
     * @param string|null   $sumField      Campo numérico a sumar (ej. 'KARCANT').
     * @param float         $sumValue      [out] Suma total (con signo aplicado).
     * @param callable|null $signCallback  Función que recibe una fila y devuelve +1, -1 ó 0.
     *                                     Si es null se asume siempre +1.
     * @return array[]
     */
    public function filterByField(
        string   $fieldName,
        string   $value,
        int      $start,
        int      $length,
        int      &$totalFiltered,
        ?string  $sumField = null,
        float    &$sumValue = 0.0,
        ?callable $signCallback = null
    ): array {
        $fh    = $this->openFile();
        $value = mb_strtolower(trim($value), 'UTF-8');

        fseek($fh, $this->headerSize);

        $allMatched = [];
        $sumValue   = 0.0;

        for ($i = 0; $i < $this->numRecords; $i++) {
            $raw = fread($fh, $this->recordSize);
            if (strlen($raw) < $this->recordSize) break;
            if ($raw[0] === '*') continue;

            $row = $this->decodeRecord($raw);
            if ($row === null) continue;

            $fieldVal = mb_strtolower(trim((string)($row[$fieldName] ?? '')), 'UTF-8');
            if ($fieldVal === $value) {
                $allMatched[] = $row;
                if ($sumField !== null && isset($row[$sumField]) && is_numeric($row[$sumField])) {
                    $sign      = $signCallback ? (int)($signCallback($row)) : 1;
                    $sumValue += $sign * (float)$row[$sumField];
                }
            }
        }

        fclose($fh);

        $totalFiltered = count($allMatched);
        return array_slice($allMatched, $start, $length);
    }

    // ----------------------------------------------------------------
    // Métodos privados
    // ----------------------------------------------------------------

    private function openFile() {
        $fh = fopen($this->path, 'rb');
        if (!$fh) {
            throw new RuntimeException("No se puede abrir: {$this->path}");
        }
        return $fh;
    }

    /**
     * Parsea la cabecera del DBF y construye $this->fields.
     */
    private function parseHeader(): void {
        $fh = $this->openFile();

        // Bytes 0-31: cabecera principal
        $header = fread($fh, 32);

        $this->numRecords = unpack('V', substr($header, 4, 4))[1];
        $this->headerSize = unpack('v', substr($header, 8, 2))[1];
        $this->recordSize = unpack('v', substr($header, 10, 2))[1];

        // Descriptores de campo: 32 bytes cada uno
        $numFieldSlots = (int)(($this->headerSize - 32 - 1) / 32);

        for ($i = 0; $i < $numFieldSlots; $i++) {
            $fd = fread($fh, 32);
            if (strlen($fd) < 32) break;

            // Byte 0x0D = terminador de la lista de campos
            if (ord($fd[0]) === 0x0D) break;

            $name    = rtrim(substr($fd, 0, 11), "\x00");
            $type    = $fd[11];
            $length  = ord($fd[16]);
            $decimal = ord($fd[17]);

            // Ignorar entradas inválidas
            if ($name === '' || ord($name[0]) < 32) continue;
            if ($type === "\x00" || $length === 0) continue;

            $this->fields[] = [
                'name'    => $name,
                'type'    => $type,
                'length'  => $length,
                'decimal' => $decimal,
            ];
        }

        fclose($fh);
    }

    /**
     * Decodifica un registro crudo (bytes) en un array asociativo.
     * Devuelve null si el registro está eliminado.
     */
    private function decodeRecord(string $raw): ?array {
        if ($raw[0] === '*') return null;

        $row    = [];
        $offset = 1; // el byte 0 es el flag de eliminación

        foreach ($this->fields as $f) {
            $bytes   = substr($raw, $offset, $f['length']);
            $offset += $f['length'];
            $row[$f['name']] = $this->parseField($bytes, $f['type'], $f['length'], $f['decimal']);
        }

        return $row;
    }

    /**
     * Convierte los bytes de un campo al valor PHP correspondiente.
     */
    private function parseField(string $raw, string $type, int $length, int $decimal): mixed {
        switch ($type) {

            // ---- Texto ----
            case 'C':
                return $this->toUtf8(rtrim($raw, ' '));

            // ---- Numérico ----
            case 'N':
            case 'F':
                $s = trim($raw);
                if ($s === '' || $s === '.' || $s === '-' || $s === '*') return null;
                return $decimal > 0 ? (float)$s : (int)$s;

            // ---- Lógico ----
            case 'L':
                $c = strtoupper(trim($raw));
                if ($c === 'T' || $c === 'Y') return true;
                if ($c === 'F' || $c === 'N') return false;
                return null;

            // ---- Fecha (AAAAMMDD) ----
            case 'D':
                $s = trim($raw);
                if (strlen($s) !== 8 || $s === '00000000') return null;
                return substr($s, 0, 4) . '-' . substr($s, 4, 2) . '-' . substr($s, 6, 2);

            // ---- Fecha/Hora VFP (JDN 4 bytes + ms 4 bytes) ----
            case 'T':
                if (strlen($raw) < 8) return null;
                $jdn = unpack('V', substr($raw, 0, 4))[1];
                $ms  = unpack('V', substr($raw, 4, 4))[1];
                if ($jdn === 0) return null;
                // JDN → Unix timestamp (JDN 2440588 = 1970-01-01)
                $unix = ($jdn - 2440588) * 86400 + (int)($ms / 1000);
                return date('Y-m-d H:i:s', $unix);

            // ---- Entero VFP (4 bytes, little-endian con signo) ----
            case 'I':
                if (strlen($raw) < 4) return null;
                $val = unpack('V', substr($raw, 0, 4))[1];
                if ($val > 0x7FFFFFFF) $val -= 0x100000000;
                return $val;

            // ---- Moneda VFP (8 bytes, little-endian / 10 000) ----
            case 'Y':
                if (strlen($raw) < 8) return null;
                $lo = unpack('V', substr($raw, 0, 4))[1];
                $hi = unpack('V', substr($raw, 4, 4))[1];
                // Combinar en un entero de 64 bits
                $val = (float)$hi * 4294967296.0 + (float)$lo;
                // Convertir a signo
                if ($hi >= 0x80000000) {
                    $val -= 18446744073709551616.0;
                }
                return round($val / 10000.0, 4);

            // ---- Memo (devuelve número de bloque) ----
            case 'M':
                $s = trim($raw);
                return $s === '' ? null : (int)$s;

            // ---- Tipos no manejados ----
            default:
                return $this->toUtf8(rtrim($raw, "\x00 "));
        }
    }

    /** Convierte una cadena desde la codificación de origen a UTF-8. */
    private function toUtf8(string $str): string {
        $str = str_replace("\x00", '', $str);
        if ($str === '') return '';
        $converted = @iconv($this->encoding, 'UTF-8//TRANSLIT//IGNORE', $str);
        return $converted !== false ? $converted : $str;
    }
}
