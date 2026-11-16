<?php
// /config/conexion.php
// Conexión PDO Singleton a MySQL. Garantiza una sola instancia de PDO por request.
// Uso: $pdo = Conexion::getInstance();

class Conexion
{
    private static ?PDO $instancia = null;

    private function __construct() {}
    private function __clone() {}

    public static function getInstance(): PDO
    {
        if (self::$instancia === null) {
            // Lee la configuración desde config.local.php (fuera del repo)
            $configLocal = __DIR__ . '/config.local.php';
            $host = 'localhost';
            $db   = 'CINE_DB';
            $user = 'root';
            $pass = '';

            if (is_file($configLocal)) {
                $cfg  = require $configLocal;
                $host = $cfg['host'] ?? $host;
                $db   = $cfg['db']   ?? $db;
                $user = $cfg['user'] ?? $user;
                $pass = $cfg['pass'] ?? $pass;
            }

            try {
                self::$instancia = new PDO(
                    "mysql:host=$host;dbname=$db;charset=utf8mb4",
                    $user,
                    $pass,
                    [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES   => false,
                    ]
                );
            } catch (PDOException $e) {
                // No exponer detalles internos al cliente
                error_log('Conexion::getInstance() - ' . $e->getMessage());
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(["error" => "Error de conexión a la base de datos."]);
                exit;
            }
        }

        return self::$instancia;
    }
}