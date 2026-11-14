<?php
require_once __DIR__ . '/../includes/sesion.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');

// Solo administradores: 401 sin sesión, 403 si no es administrador
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "No autenticado."]);
    exit;
}
if (($_SESSION['usuario_rol'] ?? '') !== 'administrador') {
    http_response_code(403);
    echo json_encode(["error" => "Acceso restringido a administradores."]);
    exit;
}

require_once '../includes/db.php';

try {
    // Solo cuentan los tickets confirmados o utilizados (no los cancelados)
    $entradas = $pdo->query(
        "SELECT p.titulo AS pelicula,
                COUNT(c.id_compra)   AS cantidad,
                SUM(c.precio_cobrado) AS total
         FROM Compra c
         JOIN Ticket t              ON t.id_ticket = c.id_ticket
                                   AND t.situacion_boleto IN ('confirmado','utilizado')
         JOIN Asiento_Itinerario ai ON ai.id_asiento_itinerario = c.id_asiento_itinerario
         JOIN Itinerario i          ON i.id_itinerario = ai.id_itinerario
         JOIN Pelicula p            ON p.id_pelicula = i.id_pelicula
         GROUP BY p.id_pelicula, p.titulo
         ORDER BY total DESC, p.titulo"
    )->fetchAll(PDO::FETCH_ASSOC);

    $entradas = array_map(function ($f) {
        $cant  = (int)$f['cantidad'];
        $total = (float)$f['total'];
        return [
            "pelicula" => $f['pelicula'],
            "cantidad" => $cant,
            "precio"   => $cant > 0 ? round($total / $cant, 2) : 0,   // promedio por entrada
            "total"    => $total
        ];
    }, $entradas);

    // Combos: se listan los activos (aunque no tengan ventas) y los que alguna vez se vendieron
    $combos = $pdo->query(
        "SELECT cb.nombre,
                COALESCE(SUM(d.cantidad), 0)                    AS cantidad,
                COALESCE(SUM(d.cantidad * d.precio_unitario), 0) AS total
         FROM Comida_Bebida cb
         LEFT JOIN Detalle_Orden d
                ON d.id_comida_bebida = cb.id_comida_bebida
               AND EXISTS (SELECT 1
                           FROM Orden_CyB o
                           JOIN Ticket t ON t.id_ticket = o.id_ticket
                           WHERE o.id_orden_cyb = d.id_orden_cyb
                             AND t.situacion_boleto IN ('confirmado','utilizado'))
         GROUP BY cb.id_comida_bebida, cb.nombre, cb.activo
         HAVING cb.activo = 1 OR cantidad > 0
         ORDER BY total DESC, cb.nombre"
    )->fetchAll(PDO::FETCH_ASSOC);

    $combos = array_map(function ($f) {
        $cant  = (int)$f['cantidad'];
        $total = (float)$f['total'];
        return [
            "nombre"   => $f['nombre'],
            "cantidad" => $cant,
            "precio"   => $cant > 0 ? round($total / $cant, 2) : 0,
            "total"    => $total
        ];
    }, $combos);

    // Visitas: si todavía no se creó la tabla Visita, el resto de las métricas igual funciona
    $visitas = [];
    try {
        $filas = $pdo->query(
            "SELECT dia, cantidad FROM Visita ORDER BY dia DESC LIMIT 14"
        )->fetchAll(PDO::FETCH_ASSOC);
        foreach ($filas as $f) {
            $visitas[] = [
                "dia"      => date('d/m/Y', strtotime($f['dia'])),
                "cantidad" => (int)$f['cantidad']
            ];
        }
    } catch (PDOException $e) {
        $visitas = [];
    }

    echo json_encode([
        "entradas" => $entradas,
        "combos"   => $combos,
        "visitas"  => $visitas
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error interno en el servidor."]);
}