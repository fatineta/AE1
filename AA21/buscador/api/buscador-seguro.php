<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(["estado" => "error", "mensaje" => "Método no permitido."]);
    exit;
}

$q = $_GET['q'] ?? '';
if (!is_string($q)) {                       
    http_response_code(400);
    echo json_encode(["estado" => "error", "mensaje" => "Parámetro inválido."]);
    exit;
}
$q = trim($q);
if ($q === '' || mb_strlen($q) > 100) {     
    http_response_code(400);
    echo json_encode(["estado" => "error", "mensaje" => "Ingrese entre 1 y 100 caracteres."]);
    exit;
}

$qLiteral = addcslashes($q, '%_\\');
$patron   = '%' . $qLiteral . '%';

try {
    $sql = "SELECT id, nombre, categoria, precio, stock
            FROM productos
            WHERE nombre LIKE :nombre OR categoria LIKE :categoria
            ORDER BY nombre ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':nombre',    $patron, PDO::PARAM_STR);
    $stmt->bindValue(':categoria', $patron, PDO::PARAM_STR);
    $stmt->execute();

    $productos = $stmt->fetchAll();

    http_response_code(200);
    echo json_encode([
        "estado" => "exito",
        "total"  => count($productos),
        "data"   => $productos
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log('Error en buscador-seguro.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(["estado" => "error", "mensaje" => "Error interno del servidor."]);
}
