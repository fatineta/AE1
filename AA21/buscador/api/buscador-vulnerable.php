<?php
require_once '../config/conexion.php';

$q = $_GET['q'] ?? '';


$sql = "SELECT * FROM productos WHERE nombre LIKE '%" . $q . "%'";
$resultado = $pdo->query($sql);

$resultado=$resultado->fetchAll();
 http_response_code(200);
    echo json_encode([
        "estado" => "exito",
        "total"  => count($resultado),
        "data"   => $resultado
    ], JSON_UNESCAPED_UNICODE);
