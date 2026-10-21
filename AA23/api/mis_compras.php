<?php
session_start();
header('Content-Type: application/json');

// Guardián de sesión
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "No autenticado."]);
    exit;
}

require_once '../includes/db.php';

try {
    // El id sale SIEMPRE de la sesión, nunca de la petición
    $stmt = $pdo->prepare(
        "SELECT t.id_ticket, t.fecha_boleto, t.situacion_boleto, t.medio_de_pago,
                p.titulo, i.fecha AS fecha_funcion, i.horario_inicio,
                s.nombre_sala, a.numero_asiento, c.precio_cobrado
         FROM Ticket t
         JOIN Compra c              ON c.id_ticket = t.id_ticket
         JOIN Asiento_Itinerario ai ON ai.id_asiento_itinerario = c.id_asiento_itinerario
         JOIN Asiento a             ON a.id_asiento = ai.id_asiento
         JOIN Itinerario i          ON i.id_itinerario = ai.id_itinerario
         JOIN Pelicula p            ON p.id_pelicula = i.id_pelicula
         JOIN Sala s                ON s.id_sala = i.id_sala
         WHERE t.id_cliente = :id
         ORDER BY t.fecha_boleto DESC, t.id_ticket, a.numero_asiento"
    );
    $stmt->execute(['id' => $_SESSION['usuario_id']]);

    // Agrupa las butacas por ticket
    $tickets = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
        $id = $f['id_ticket'];
        if (!isset($tickets[$id])) {
            $tickets[$id] = [
                "id_ticket"        => (int)$id,
                "fecha_boleto"     => $f['fecha_boleto'],
                "situacion_boleto" => $f['situacion_boleto'],
                "medio_de_pago"    => $f['medio_de_pago'],
                "pelicula"         => $f['titulo'],
                "fecha_funcion"    => $f['fecha_funcion'],
                "horario_inicio"   => $f['horario_inicio'],
                "sala"             => $f['nombre_sala'],
                "butacas"          => [],
                "total"            => 0.0
            ];
        }
        $tickets[$id]['butacas'][] = $f['numero_asiento'];
        $tickets[$id]['total']    += (float)$f['precio_cobrado'];
    }

    echo json_encode([
        "cliente" => [
            "nombre" => $_SESSION['usuario_nombre'],
            "email"  => $_SESSION['usuario_email'],
            "rol"    => $_SESSION['usuario_rol']
        ],
        "compras" => array_values($tickets)
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error interno en el servidor."]);
}
