<?php
require_once __DIR__ . '/../includes/sesion.php';
header('Content-Type: application/json');

require_once '../includes/db.php';

try {
    // Solo los productos activos (los de prueba viejos quedan archivados)
    $filas = $pdo->query(
        "SELECT id_comida_bebida, nombre, descripcion, precio, imagen
         FROM Comida_Bebida
         WHERE activo = 1
         ORDER BY id_comida_bebida"
    )->fetchAll(PDO::FETCH_ASSOC);

    $combos = array_map(function ($f) {
        return [
            "id"          => (int)$f['id_comida_bebida'],
            "titulo"      => $f['nombre'],
            "descripcion" => $f['descripcion'] ?? '',
            "precio"      => (float)$f['precio'],
            "imagen"      => $f['imagen'] ?? '',
            "icono"       => '🍿'   // se usa solo si el combo no tiene imagen
        ];
    }, $filas);

    echo json_encode($combos);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error interno en el servidor."]);
}