<?php
require_once __DIR__ . '/../includes/sesion.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');   // el estado de las butacas cambia: nunca se cachea

require_once '../includes/db.php';

$id = (int)($_GET['funcion'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(["error" => "Función inválida."]);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "SELECT i.id_itinerario, i.fecha, i.horario_inicio, p.titulo, s.nombre_sala
         FROM Itinerario i
         JOIN Pelicula p ON p.id_pelicula = i.id_pelicula
         JOIN Sala s     ON s.id_sala = i.id_sala
         WHERE i.id_itinerario = :id"
    );
    $stmt->execute(['id' => $id]);
    $f = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$f) {
        http_response_code(404);
        echo json_encode(["error" => "La función no existe."]);
        exit;
    }

    // Butacas de la función con su estado y su precio real (fila A = más cara)
    $stmt = $pdo->prepare(
        "SELECT a.numero_asiento, ai.estado,
                COALESCE(ai.precio_asiento, i.precio) AS precio
         FROM Asiento_Itinerario ai
         JOIN Asiento a    ON a.id_asiento = ai.id_asiento
         JOIN Itinerario i ON i.id_itinerario = ai.id_itinerario
         WHERE ai.id_itinerario = :id
         ORDER BY LEFT(a.numero_asiento, 1), CAST(SUBSTRING(a.numero_asiento, 2) AS UNSIGNED)"
    );
    $stmt->execute(['id' => $id]);

    $asientos = array_map(function ($r) {
        return [
            "numero" => $r['numero_asiento'],
            "estado" => $r['estado'],            // disponible | reservado | ocupado
            "precio" => (float)$r['precio']
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));

    echo json_encode([
        "funcion" => [
            "id_itinerario" => (int)$f['id_itinerario'],
            "pelicula"      => $f['titulo'],
            "fecha"         => $f['fecha'],
            "hora"          => $f['horario_inicio'],
            "sala"          => $f['nombre_sala']
        ],
        "asientos" => $asientos
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error interno en el servidor."]);
}
