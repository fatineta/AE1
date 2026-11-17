<?php
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../models/QrModel.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$metodo = $_SERVER['REQUEST_METHOD'];
if ($metodo !== 'GET' && $metodo !== 'POST') {
    http_response_code(405);
    echo json_encode(["valido" => false, "motivo" => "Método no permitido."]);
    exit;
}

$entrada = $metodo === 'POST' ? (json_decode(file_get_contents('php://input'), true) ?? []) : $_GET;
$codigo = (string) ($entrada['codigo'] ?? '');
$firma  = (string) ($entrada['firma'] ?? '');

$idTicket = QrModel::idDesdeCodigo($codigo);
if ($idTicket === null || !QrModel::firmaValida($idTicket, $firma)) {
    http_response_code(400);
    echo json_encode(["valido" => false, "motivo" => "Código o firma inválidos."]);
    exit;
}

try {
    $pdo = Conexion::getInstance();
    $d = QrModel::datosTicket($pdo, $idTicket);
    if (!$d) {
        http_response_code(404);
        echo json_encode(["valido" => false, "motivo" => "El ticket no existe."]);
        exit;
    }

    $pedido = [
        "codigo" => $d['codigo'], "pelicula" => $d['pelicula'], "fecha" => $d['fecha'],
        "hora" => substr((string) $d['hora'], 0, 5), "sala" => $d['sala'],
        "butacas" => $d['butacas'], "combos" => $d['combos'], "total" => $d['total']
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

        $st = $pdo->prepare("UPDATE Ticket SET situacion_boleto = 'utilizado' WHERE id_ticket = :id AND situacion_boleto = 'confirmado'");
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
        'confirmado' => null, 'utilizado' => 'Este ticket ya fue utilizado.',
        'cancelado' => 'Este ticket fue cancelado.', 'pendiente' => 'Este ticket todavía no está confirmado.'
    ];
    $aviso = $mensajes[$d['estado']] ?? 'Estado del ticket desconocido.';
    echo json_encode(["valido" => $aviso === null, "estado" => $d['estado'], "motivo" => $aviso, "pedido" => $pedido]);

} catch (PDOException $e) {
    error_log('validar_ticket.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(["valido" => false, "motivo" => "Error interno en el servidor."]);
}
