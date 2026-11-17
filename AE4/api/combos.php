<?php
require_once __DIR__ . '/../includes/sesion.php';
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/db.php';

try {
    // Consultamos solo las columnas que sí existen en tu tabla (id_comida_bebida, nombre, descripcion, precio)
    $stmt = $pdo->query(
        "SELECT id_comida_bebida, nombre, descripcion, precio 
         FROM comida_bebida 
         ORDER BY id_comida_bebida"
    );
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $combos = array_map(function ($f) {
        return [
            "id"          => (string)$f['id_comida_bebida'],
            "titulo"      => $f['nombre'],
            "descripcion" => $f['descripcion'] ?? '',
            "precio"      => (float)$f['precio'],
            "imagen"      => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcT6kcloVy7K_XIxmJAeuG8geIMoD4kywzMU_kFcz6YopGrK5X1_Vn6avaJI&s=10', // Imagen por defecto
            "icono"       => '🍿'
        ];
    }, $filas);

    echo json_encode($combos, JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error interno en el servidor: " . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
