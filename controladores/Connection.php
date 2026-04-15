<?php
    class Connection {
        private static $instance = null;
        private $connection;

        private function __construct() {
            $env = [];
            foreach (file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (str_starts_with(trim($line), '#')) continue;
                [$key, $value] = explode('=', $line, 2);
                $env[trim($key)] = trim($value, " \t\n\r\0\x0B'\"");
            }

            $host     = $env['DB_HOST'];
            $username = $env['DB_USERNAME'];
            $password = $env['DB_PASSWORD'];
            $database = $env['DB_DATABASE'];

            $this->connection = new mysqli($host, $username, $password, $database);

            if ($this->connection->connect_error) {
                die("Error de conexión: " . $this->connection->connect_error);
            }

            // Configurar la codificación de caracteres a UTF-8
            $this->connection->set_charset("utf8");
        }

        public static function getInstance() {
            if (self::$instance == null) {
                self::$instance = new Connection();
            }
            return self::$instance;
        }

        public function getConnection() {
            return $this->connection;
        }
    }

?>