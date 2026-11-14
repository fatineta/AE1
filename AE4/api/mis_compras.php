<?php
require_once __DIR__ . '/../includes/sesion.php';
header('Content-Type: application/json');

// Guardián de sesión
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "No autenticado."]);
    exit;
}

require_once '../includes/db.php';
require_once __DIR__ . '/../includes/qr.php';

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
                "codigo"           => qr_codigo((int)$id),
                "firma"            => qr_firma((int)$id),
                "fecha_boleto"     => $f['fecha_boleto'],
                "situacion_boleto" => $f['situacion_boleto'],
                "medio_de_pago"    => $f['medio_de_pago'],
                "pelicula"         => $f['titulo'],
                "fecha_funcion"    => $f['fecha_funcion'],
                "horario_inicio"   => $f['horario_inicio'],
                "sala"             => $f['nombre_sala'],
                "butacas"          => [],
                "combos"           => [],
                "total"            => 0.0
            ];
        }
        $tickets[$id]['butacas'][] = $f['numero_asiento'];
        $tickets[$id]['total']    += (float)$f['precio_cobrado'];
    }

    // Combos y bebidas de cada ticket (el precio es el congelado al comprar)
    $stmtC = $pdo->prepare(
        "SELECT o.id_ticket, cb.nombre, d.cantidad, d.precio_unitario
         FROM Ticket t
         JOIN Orden_CyB o       ON o.id_ticket = t.id_ticket
         JOIN Detalle_Orden d   ON d.id_orden_cyb = o.id_orden_cyb
         JOIN Comida_Bebida cb  ON cb.id_comida_bebida = d.id_comida_bebida
         WHERE t.id_cliente = :id
         ORDER BY o.id_ticket, cb.nombre"
    );
    $stmtC->execute(['id' => $_SESSION['usuario_id']]);
    foreach ($stmtC->fetchAll(PDO::FETCH_ASSOC) as $f) {
        $id = $f['id_ticket'];
        if (!isset($tickets[$id])) continue;
        $subtotal = (float)$f['precio_unitario'] * (int)$f['cantidad'];
        $tickets[$id]['combos'][] = [
            "nombre"   => $f['nombre'],
            "cantidad" => (int)$f['cantidad'],
            "subtotal" => $subtotal
        ];
        $tickets[$id]['total'] += $subtotal;
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