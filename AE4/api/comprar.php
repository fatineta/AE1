<?php
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

require_once '../includes/db.php';

$data         = json_decode(file_get_contents('php://input'), true) ?? [];
$idItinerario = (int)($data['id_itinerario'] ?? 0);
$asientos     = $data['asientos'] ?? [];
$combosIn     = $data['combos'] ?? [];
$cupon        = strtoupper(trim((string)($data['cupon'] ?? '')));

// Normaliza el medio de pago a los valores permitidos por el CHECK
$pagoCrudo = strtolower(trim((string)($data['medio_de_pago'] ?? '')));
$medio = 'tarjeta';
foreach (['mercadopago' => 'qr', 'tarjeta' => 'tarjeta', 'qr' => 'qr', 'transfer' => 'transferencia', 'efectivo' => 'efectivo'] as $clave => $valor) {
    if (strpos($pagoCrudo, $clave) !== false) { $medio = $valor; break; }
}

// Validación de butacas
$validos = is_array($asientos) && count($asientos) >= 1 && count($asientos) <= 10;
if ($validos) {
    foreach ($asientos as $a) {
        if (!is_string($a) || !preg_match('/^[A-Z][0-9]{1,2}$/', $a)) { $validos = false; break; }
    }
    $asientos = array_values(array_unique($asientos));
}
if ($idItinerario <= 0 || !$validos) {
    http_response_code(400);
    echo json_encode(["error" => "Datos de compra inválidos."]);
    exit;
}

// Validación de combos
$combos = [];
if (!is_array($combosIn) || count($combosIn) > 20) {
    http_response_code(400);
    echo json_encode(["error" => "Combos inválidos."]);
    exit;
}
foreach ($combosIn as $c) {
    $id  = is_array($c) ? (int)($c['id'] ?? 0) : 0;
    $cant = is_array($c) ? (int)($c['cantidad'] ?? 0) : 0;
    if ($id <= 0 || $cant < 1 || $cant > 20) {
        http_response_code(400);
        echo json_encode(["error" => "Combos inválidos."]);
        exit;
    }
    $combos[$id] = ($combos[$id] ?? 0) + $cant;
}

// El cupón se valida SIEMPRE acá
$descuento = 0.0;
if ($cupon !== '') {
    if ($cupon === 'UCP10') {
        $descuento = 0.10;
    } else {
        http_response_code(400);
        echo json_encode(["error" => "Código de cupón inválido o vencido."]);
        exit;
    }
}
$aplicar = function (float $precio) use ($descuento): float {
    return round($precio * (1 - $descuento), 2);
};

try {
    $pdo->beginTransaction();

    // 1) Ticket del cliente logueado
    $stmt = $pdo->prepare(
        "INSERT INTO Ticket (fecha_boleto, situacion_boleto, medio_de_pago, id_cliente)
         VALUES (NOW(), 'confirmado', :m, :c)"
    );
    $stmt->execute(['m' => $medio, 'c' => $_SESSION['usuario_id']]);
    $idTicket = (int)$pdo->lastInsertId();

    // 2) Una Compra por butaca
    $buscar = $pdo->prepare(
        "SELECT ai.id_asiento_itinerario, ai.estado,
                COALESCE(ai.precio_asiento, i.precio) AS precio
         FROM Asiento_Itinerario ai
         JOIN Asiento a    ON a.id_asiento = ai.id_asiento
         JOIN Itinerario i ON i.id_itinerario = ai.id_itinerario
         WHERE ai.id_itinerario = :it AND a.numero_asiento = :n
         FOR UPDATE"
    );
    $insCompra = $pdo->prepare(
        "INSERT INTO Compra (id_ticket, id_asiento_itinerario, precio_cobrado)
         VALUES (:t, :ai, :p)"
    );
    $marcar = $pdo->prepare(
        "UPDATE Asiento_Itinerario SET estado = 'ocupado'
         WHERE id_asiento_itinerario = :ai"
    );

    $totalEntradas = 0.0;
    foreach ($asientos as $nombre) {
        $buscar->execute(['it' => $idItinerario, 'n' => $nombre]);
        $fila = $buscar->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            throw new RuntimeException("La butaca $nombre no existe en esa función.", 409);
        }
        if ($fila['estado'] !== 'disponible') {
            throw new RuntimeException("La butaca $nombre ya no está disponible.", 409);
        }
        $precio = $aplicar((float)$fila['precio']);
        $insCompra->execute(['t' => $idTicket, 'ai' => $fila['id_asiento_itinerario'], 'p' => $precio]);
        $marcar->execute(['ai' => $fila['id_asiento_itinerario']]);
        $totalEntradas += $precio;
    }

    // 3) Combos
    $totalCombos = 0.0;
    if (count($combos) > 0) {
        $pdo->prepare("INSERT INTO Orden_CyB (id_ticket) VALUES (:t)")->execute(['t' => $idTicket]);
        $idOrden = (int)$pdo->lastInsertId();

        $buscarProd = $pdo->prepare("SELECT precio FROM Comida_Bebida WHERE id_comida_bebida = :id");
        $insDetalle = $pdo->prepare(
            "INSERT INTO Detalle_Orden (id_orden_cyb, id_comida_bebida, cantidad, precio_unitario)
             VALUES (:o, :p, :c, :pu)"
        );
        foreach ($combos as $idProd => $cant) {
            $buscarProd->execute(['id' => $idProd]);
            $prod = $buscarProd->fetch(PDO::FETCH_ASSOC);
            if (!$prod) {
                throw new RuntimeException("Uno de los productos elegidos ya no existe.", 400);
            }
            $pu = $aplicar((float)$prod['precio']);
            $insDetalle->execute(['o' => $idOrden, 'p' => $idProd, 'c' => $cant, 'pu' => $pu]);
            $totalCombos += $pu * $cant;
        }
    }

    $pdo->commit();

    // ---- Generar QR y enviar correo ----
    require_once __DIR__ . '/../includes/mailer.php';

    $datos   = qr_datos_ticket($pdo, $idTicket);
    $textoQR = qr_texto($datos);
    $pngB64  = base64_encode(qr_png($textoQR));

    $emailCliente  = (string)($_SESSION['usuario_email'] ?? '');
    $nombreCliente = (string)($_SESSION['usuario_nombre'] ?? 'Cliente');

    $envio = enviar_qr_por_correo($emailCliente, $nombreCliente, $datos, $textoQR);

    echo json_encode([
        "success"        => true,
        "id_ticket"      => $idTicket,
        "codigo"         => $datos['codigo'],
        "qr_base64"      => $pngB64,
        "total_entradas" => $totalEntradas,
        "total_combos"   => $totalCombos,
        "total"          => $totalEntradas + $totalCombos,
        "correo"         => [
            "enviado" => $envio['enviado'],
            "destino" => $envio['destino'] ?? null,
            "motivo"  => $envio['motivo']  ?? null
        ]
    ]);

} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $codigo = in_array($e->getCode(), [400, 409], true) ? $e->getCode() : 409;
    http_response_code($codigo);
    echo json_encode(["error" => $e->getMessage()]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(["error" => "Error interno en el servidor."]);
}
