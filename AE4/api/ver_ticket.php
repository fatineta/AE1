<?php
// GET  api/ver_ticket.php?r=CL-00034&f=<firma>  -> datos del pedido, leídos de la base.
//        No pide sesión: el QR es la entrada. Pero la firma tiene que ser válida (HMAC del servidor),
//        así que nadie puede consultar un ticket ajeno adivinando códigos.
// POST api/ver_ticket.php  {"r":"CL-00034","f":"..."}  -> marca el ticket como "utilizado".
//        Solo administradores logueados.
require_once __DIR__ . '/../includes/sesion.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/qr.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
date_default_timezone_set('America/Argentina/Buenos_Aires');

function responder(int $codigo, array $cuerpo): void
{
    http_response_code($codigo);
    echo json_encode($cuerpo);
    exit;
}

$esAdmin = (($_SESSION['usuario_rol'] ?? '') === 'administrador');
$metodo  = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'POST') {
    if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === false) {
        responder(415, ["error" => "Formato no soportado."]);
    }
    $in = json_decode(file_get_contents('php://input'), true) ?: [];
    $r = (string) ($in['r'] ?? '');
    $f = (string) ($in['f'] ?? '');
} elseif ($metodo === 'GET') {
    $r = (string) ($_GET['r'] ?? '');
    $f = (string) ($_GET['f'] ?? '');
} else {
    responder(405, ["error" => "Método no permitido."]);
}

// La firma se comprueba ANTES de tocar la base. Mismo mensaje si es inválida o no existe.
$id = qr_id_desde_codigo($r);
if (!$id || !preg_match('/^[0-9a-f]{20}$/i', $f) || !qr_firma_valida($id, $f)) {
    responder(404, ["error" => "QR inválido o alterado."]);
}

try {
    $d = qr_datos_ticket($pdo, $id);
    if (!$d) {
        responder(404, ["error" => "QR inválido o alterado."]);
    }

    if ($metodo === 'POST') {
        if (!$esAdmin) {
            responder(403, ["error" => "Solo el personal puede marcar una entrada como utilizada."]);
        }
        if ($d['estado'] === 'utilizado') {
            responder(409, ["error" => "Esta entrada ya fue utilizada."]);
        }
        if ($d['estado'] !== 'confirmado') {
            responder(409, ["error" => "Solo se puede canjear una entrada confirmada (estado: " . $d['estado'] . ")."]);
        }
        // Atómico: si dos personas lo intentan a la vez, solo una lo logra
        $st = $pdo->prepare("UPDATE Ticket SET situacion_boleto = 'utilizado'
                             WHERE id_ticket = :id AND situacion_boleto = 'confirmado'");
        $st->execute(['id' => $id]);
        if ($st->rowCount() !== 1) {
            responder(409, ["error" => "Esta entrada ya fue utilizada."]);
        }
        responder(200, ["success" => true]);
    }

    // GET: solo lo necesario para mostrar la entrada (sin datos del cliente)
    echo json_encode([
        "codigo"         => $d['codigo'],
        "estado"         => $d['estado'],
        "pelicula"       => $d['pelicula'],
        "fecha"          => $d['fecha'],
        "hora"           => substr((string) $d['hora'], 0, 5),
        "sala"           => $d['sala'],
        "butacas"        => $d['butacas'],
        "combos"         => $d['combos'],
        "total"          => $d['total'],
        "es_hoy"         => ($d['fecha'] === date('Y-m-d')),
        "puede_canjear"  => ($esAdmin && $d['estado'] === 'confirmado')
    ]);
} catch (PDOException $e) {
    error_log('ver_ticket.php: ' . $e->getMessage());
    responder(500, ["error" => "Error interno en el servidor."]);
}
