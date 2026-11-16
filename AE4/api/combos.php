<?php
// api/combos.php
// Endpoint PÚBLICO: cualquiera puede ver los combos disponibles.
// No requiere sesión, por eso NO incluye sesion.php.

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/conexion.php';

try {
    $pdo = Conexion::getInstance();

    // Solo los productos activos (los de prueba viejos quedan archivados)
    $stmt = $pdo->prepare(
        "SELECT id_comida_bebida, nombre, descripcion, precio, imagen
         FROM Comida_Bebida
         WHERE activo = 1
         ORDER BY id_comida_bebida"
    );
    $stmt->execute();
    $filas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $combos = array_map(function ($f) {
        return [
            "id"          => (int) $f['id_comida_bebida'],
            "titulo"      => $f['nombre'],
            "descripcion" => $f['descripcion'] ?? '',
            "precio"      => (float) $f['precio'],
            "imagen"      => $f['imagen'] ?? '',
            "icono"       => '🍿'   // se usa solo si el combo no tiene imagen
        ];
    }, $filas);

    echo json_encode($combos);
} catch (PDOException $e) {
    error_log('combos.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Error interno en el servidor."]);
}
