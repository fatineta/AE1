<?php
// Valida un QR. La máquina (o el personal) manda SOLO el código de reserva y la firma;
// el pedido que se devuelve sale de la base de datos, nunca del texto del QR.
//   GET  ?codigo=CL-00013&firma=...                 -> valida y devuelve el pedido
//   POST {"codigo":"...","firma":"...","accion":"canjear"}  -> marca el ticket como utilizado (solo administradores)
require_once __DIR__ . '/../includes/sesion.php';
header('Content-Type: application/json');
header('Cache-Control: no-store');

require_once '../includes/db.php';
require_once __DIR__ . '/../includes/qr.php';

$metodo = $_SERVER['REQUEST_METHOD'];
if ($metodo !== 'GET' && $metodo !== 'POST') {
    http_response_code(405);
    echo json_encode(["valido" => false, "motivo" => "Método no permitido."]);
    exit;
}

$entrada = $metodo === 'POST'
    ? (json_decode(file_get_contents('php://input'), true) ?? [])
    : $_GET;
$codigo = (string) ($entrada['codigo'] ?? '');
$firma  = (string) ($entrada['firma'] ?? '');

// Mismo mensaje para código mal escrito y firma inválida: no se le da pistas a quien prueba códigos
$idTicket = qr_id_desde_codigo($codigo);
if ($idTicket === null || !qr_firma_valida($idTicket, $firma)) {
    http_response_code(400);
    echo json_encode(["valido" => false, "motivo" => "Código o firma inválidos."]);
    exit;
}

try {
    $d = qr_datos_ticket($pdo, $idTicket);
    if (!$d) {
        http_response_code(404);
        echo json_encode(["valido" => false, "motivo" => "El ticket no existe."]);
        exit;
    }

    $pedido = [
        "codigo"   => $d['codigo'],
        "pelicula" => $d['pelicula'],
        "fecha"    => $d['fecha'],
        "hora"     => substr((string) $d['hora'], 0, 5),
        "sala"     => $d['sala'],
        "butacas"  => $d['butacas'],
        "combos"   => $d['combos'],
        "total"    => $d['total']
    ];

    if ($metodo === 'POST') {
        if (!isset($_SESSION['usuario_id'])) {
            http_response_code(401);
            echo json_encode(["valido" => false, "motivo" => "No autenticado."]);
            exit;
        }
        if (($_SESSION['usuario_rol'] ?? '') !== 'administrador') {
            http_response_code(403);
            echo json_encode(["valido" => false, "motivo" => "Solo un administrador puede canjear tickets."]);
            exit;
        }
        if (($entrada['accion'] ?? '') !== 'canjear') {
            http_response_code(400);
            echo json_encode(["valido" => false, "motivo" => "Acción inválida."]);
            exit;
        }

        // El UPDATE condicional hace que un mismo QR no se pueda canjear dos veces
        $st = $pdo->prepare(
            "UPDATE Ticket SET situacion_boleto = 'utilizado'
             WHERE id_ticket = :id AND situacion_boleto = 'confirmado'"
        );
        $st->execute(['id' => $idTicket]);
        if ($st->rowCount() === 0) {
            http_response_code(409);
            echo json_encode(["valido" => false, "estado" => $d['estado'], "motivo" => "El ticket no está confirmado o ya fue utilizado.", "pedido" => $pedido]);
            exit;
        }
        echo json_encode(["valido" => true, "canjeado" => true, "estado" => "utilizado", "pedido" => $pedido]);
        exit;
    }

    $mensajes = [
        'confirmado' => null,
        'utilizado'  => 'Este ticket ya fue utilizado.',
        'cancelado'  => 'Este ticket fue cancelado.',
        'pendiente'  => 'Este ticket todavía no está confirmado.'
    ];
    $aviso = $mensajes[$d['estado']] ?? 'Estado del ticket desconocido.';
    echo json_encode([
        "valido" => $aviso === null,
        "estado" => $d['estado'],
        "motivo" => $aviso,
        "pedido" => $pedido
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["valido" => false, "motivo" => "Error interno en el servidor."]);
}
