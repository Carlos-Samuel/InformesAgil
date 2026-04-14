<?php
/**
 * EnvLoader — carga variables desde el archivo .env
 * ubicado en la raíz del proyecto.
 */
class EnvLoader {
    private static bool $loaded = false;
    private static array $vars = [];

    /**
     * Carga el archivo .env. Se llama automáticamente al usar get().
     *
     * @param string|null $path Ruta absoluta al .env; si es null usa la raíz del proyecto.
     */
    public static function load(?string $path = null): void {
        if ($path === null) {
            // Dos niveles arriba de /controladores/
            $path = dirname(__DIR__) . '/.env';
        }

        if (!is_readable($path)) return;

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;

            $eqPos = strpos($line, '=');
            if ($eqPos === false) continue;

            $key   = trim(substr($line, 0, $eqPos));
            $value = trim(substr($line, $eqPos + 1));
            // Eliminar comillas opcionales
            $value = trim($value, '"\'');

            self::$vars[$key] = $value;
            if (!isset($_ENV[$key])) {
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }

        self::$loaded = true;
    }

    /**
     * Obtiene el valor de una variable de entorno.
     *
     * @param string      $key     Nombre de la variable.
     * @param string|null $default Valor por defecto si no existe.
     * @return string|null
     */
    public static function get(string $key, ?string $default = null): ?string {
        if (!self::$loaded) self::load();

        if (isset(self::$vars[$key]))  return self::$vars[$key];
        if (isset($_ENV[$key]))        return $_ENV[$key];
        $env = getenv($key);
        return $env !== false ? $env : $default;
    }

    /**
     * Resuelve una ruta que puede ser relativa (desde la raíz del proyecto)
     * o absoluta.
     */
    public static function resolvePath(string $key, ?string $default = null): ?string {
        $val = self::get($key, $default);
        if ($val === null) return null;

        // Ya es absoluta (Windows o Unix)
        if (
            str_starts_with($val, '/') ||
            (strlen($val) >= 3 && $val[1] === ':')
        ) {
            return $val;
        }

        // Relativa → combinar con la raíz del proyecto
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $val);
    }
}
