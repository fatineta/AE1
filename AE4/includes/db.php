<?php
// Ajusta usuario/clave a tu XAMPP/phpMyAdmin
$host = 'localhost';
$db   = 'CINE_DB';
$user = 'root';
$pass = '18.05.46.711.314.05';

// H8: si existe includes/config.local.php (no versionado, ver config.local.example.php) se usan esos datos
$configLocal = __DIR__ . '/config.local.php';
if (is_file($configLocal)) {
    $cfg  = require $configLocal;
    $host = $cfg['host'] ?? $host;
    $db   = $cfg['db']   ?? $db;
    $user = $cfg['user'] ?? $user;
    $pass = $cfg['pass'] ?? $pass;
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(["error" => "Error de conexión a la base de datos."]);
    exit;
}
