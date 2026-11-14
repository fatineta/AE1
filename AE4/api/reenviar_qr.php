<?php
// Reenvía por correo el QR de un ticket propio (para cuando el primer envío falló).
require_once __DIR__ . '/../includes/sesion.php';
header('Content-Type: application/json');

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(["error" => "No autenticado."]);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Método no permitido."]);
    exit;
}

$data     = json_decode(file_get_contents('php://input'), true) ?? [];
$idTicket = (int) ($data['id_ticket'] ?? 0);
$idCliente = (int) $_SESSION['usuario_id'];
$emailCliente  = (string) ($_SESSION['usuario_email'] ?? '');
$nombreCliente = (string) ($_SESSION['usuario_nombre'] ?? '');

require_once '../includes/db.php';
require_once __DIR__ . '/../includes/mailer.php';

try {
    $datos = $idTicket > 0 ? qr_datos_ticket($pdo, $idTicket) : null;

    // Si el ticket no es del usuario logueado se responde igual que si no existiera
    if (!$datos || $datos['id_cliente'] !== $idCliente) {
        session_write_close();
        http_response_code(404);
        echo json_encode(["error" => "Ticket no encontrado."]);
        exit;
    }
    if ($datos['estado'] !== 'confirmado') {
        session_write_close();
        http_response_code(409);
        echo json_encode(["error" => "Solo se puede reenviar el QR de un ticket confirmado."]);
        exit;
    }

    // Freno básico: un reenvío cada 30 segundos por ticket
    $ahora = time();
    $ultimo = (int) ($_SESSION['qr_reenvio'][$idTicket] ?? 0);
    if ($ahora - $ultimo < 30) {
        session_write_close();
        http_response_code(429);
        echo json_encode(["error" => "Esperá unos segundos antes de pedir otro envío."]);
        exit;
    }
    $_SESSION['qr_reenvio'][$idTicket] = $ahora;
    session_write_close();

    $res = enviar_qr_por_correo($emailCliente, $nombreCliente, $datos, qr_texto($datos));
    if ($res['enviado']) {
        echo json_encode(["success" => true, "destino" => $res['destino']]);
    } else {
        http_response_code(502);
        echo json_encode([
            "error"  => "No se pudo enviar el correo. Probá de nuevo en un rato.",
            "motivo" => $res['motivo'] ?? 'error_envio'
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error interno en el servidor."]);
}