<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

require_once __DIR__ . '/../config/conexion.php';

try {
    $pdo = Conexion::getInstance();
    $stmt = $pdo->prepare("INSERT INTO Visita (dia, cantidad) VALUES (CURDATE(), 1) ON DUPLICATE KEY UPDATE cantidad = cantidad + 1");
    $stmt->execute();
    http_response_code(204);
} catch (PDOException $e) {
    error_log('visita.php: ' . $e->getMessage());
    http_response_code(500);
}
