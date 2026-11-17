<?php
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../models/QrModel.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
date_default_timezone_set('America/Argentina/Buenos_Aires');

function responder(int $codigo, array $cuerpo): void
{
    http_response_code($codigo);
    echo json_encode($cuerpo);
    exit;
}

try {
    $pdo = Conexion::getInstance();
} catch (PDOException $e) {
    responder(500, ["error" => "Error interno en el servidor."]);
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

// La firma se comprueba ANTES de tocar la base.
$id = QrModel::idDesdeCodigo($r);
if (!$id || !preg_match('/^[0-9a-f]{20}$/i', $f) || !QrModel::firmaValida($id, $f)) {
    responder(404, ["error" => "QR inválido o alterado."]);
}

try {
    $d = QrModel::datosTicket($pdo, $id);
    if (!$d) {
        responder(404, ["error" => "QR inválido o alterado."]);
    }

    if ($metodo === 'POST') {
        if (!isset($_SESSION['usuario_id'])) {
            responder(401, ["error" => "No autenticado."]);
        }
        if (!$esAdmin) {
            responder(403, ["error" => "Solo el personal puede marcar una entrada como utilizada."]);
        }
        if ($d['estado'] === 'utilizado') {
            responder(409, ["error" => "Esta entrada ya fue utilizada."]);
        }
        if ($d['estado'] !== 'confirmado') {
            responder(409, ["error" => "Solo se puede canjear una entrada confirmada (estado: " . $d['estado'] . ")."]);
        }
        $st = $pdo->prepare("UPDATE Ticket SET situacion_boleto = 'utilizado'
                             WHERE id_ticket = :id AND situacion_boleto = 'confirmado'");
        $st->execute(['id' => $id]);
        if ($st->rowCount() !== 1) {
            responder(409, ["error" => "Esta entrada ya fue utilizada."]);
        }
        responder(200, ["success" => true]);
    }

    echo json_encode([
        "codigo"        => $d['codigo'],
        "estado"        => $d['estado'],
        "pelicula"      => $d['pelicula'],
        "fecha"         => $d['fecha'],
        "hora"          => substr((string) $d['hora'], 0, 5),
        "sala"          => $d['sala'],
        "butacas"       => $d['butacas'],
        "combos"        => $d['combos'],
        "total"         => $d['total'],
        "es_hoy"        => ($d['fecha'] === date('Y-m-d')),
        "puede_canjear" => ($esAdmin && $d['estado'] === 'confirmado')
    ]);
} catch (PDOException $e) {
    error_log('ver_ticket.php: ' . $e->getMessage());
    responder(500, ["error" => "Error interno en el servidor."]);
}
