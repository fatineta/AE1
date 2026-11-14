<?php
// Código de reserva, firma anti-falsificación y QR del pedido.
//
// La firma es un HMAC con una clave que solo conoce el servidor: sin esa clave nadie puede
// fabricar un "Reserva: CL-xxxxx / Firma: ..." válido. Quien valida (api/validar_ticket.php)
// NO confía en el resto del texto del QR: toma solo el código y la firma, y trae el pedido real
// desde la base de datos.

// Clave secreta. Se toma de includes/config.local.php ('qr_secret') y, si no está, se crea una
// sola vez en privado/qr_secret.key (esa carpeta tiene .htaccess y no se sirve por web).
function qr_secreto(): string
{
    static $secreto = null;
    if ($secreto !== null) {
        return $secreto;
    }

    $archivoCfg = __DIR__ . '/config.local.php';
    if (is_file($archivoCfg)) {
        $cfg = require $archivoCfg;
        if (is_array($cfg) && !empty($cfg['qr_secret'])) {
            return $secreto = (string) $cfg['qr_secret'];
        }
    }

    $archivo = __DIR__ . '/../privado/qr_secret.key';
    if (!is_file($archivo)) {
        $dir = dirname($archivo);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }
        file_put_contents($archivo, bin2hex(random_bytes(32)), LOCK_EX);
    }
    return $secreto = trim((string) file_get_contents($archivo));
}

function qr_codigo(int $idTicket): string
{
    return 'CL-' . str_pad((string) $idTicket, 5, '0', STR_PAD_LEFT);
}

function qr_id_desde_codigo(string $codigo): ?int
{
    if (preg_match('/^CL-(\d{5,9})$/i', trim($codigo), $m)) {
        return (int) $m[1];
    }
    return null;
}

function qr_firma(int $idTicket): string
{
    return substr(hash_hmac('sha256', 'ticket:' . $idTicket, qr_secreto()), 0, 20);
}

function qr_firma_valida(int $idTicket, string $firma): bool
{
    return hash_equals(qr_firma($idTicket), strtolower(trim($firma)));
}

// Pedido completo leído de la base (lo que realmente quedó guardado). null si el ticket no existe.
function qr_datos_ticket(PDO $pdo, int $idTicket): ?array
{
    $st = $pdo->prepare("SELECT id_ticket, id_cliente, situacion_boleto FROM Ticket WHERE id_ticket = :id");
    $st->execute(['id' => $idTicket]);
    $t = $st->fetch(PDO::FETCH_ASSOC);
    if (!$t) {
        return null;
    }

    $st = $pdo->prepare(
        "SELECT p.titulo, i.fecha, i.horario_inicio, s.nombre_sala, a.numero_asiento, c.precio_cobrado
         FROM Compra c
         JOIN Asiento_Itinerario ai ON ai.id_asiento_itinerario = c.id_asiento_itinerario
         JOIN Asiento a             ON a.id_asiento = ai.id_asiento
         JOIN Itinerario i          ON i.id_itinerario = ai.id_itinerario
         JOIN Pelicula p            ON p.id_pelicula = i.id_pelicula
         JOIN Sala s                ON s.id_sala = i.id_sala
         WHERE c.id_ticket = :id
         ORDER BY LEFT(a.numero_asiento, 1), CAST(SUBSTRING(a.numero_asiento, 2) AS UNSIGNED)"
    );
    $st->execute(['id' => $idTicket]);
    $filas = $st->fetchAll(PDO::FETCH_ASSOC);

    $total = 0.0;
    $butacas = [];
    foreach ($filas as $f) {
        $butacas[] = $f['numero_asiento'];
        $total += (float) $f['precio_cobrado'];
    }

    $st = $pdo->prepare(
        "SELECT cb.nombre, d.cantidad, d.precio_unitario
         FROM Orden_CyB o
         JOIN Detalle_Orden d  ON d.id_orden_cyb = o.id_orden_cyb
         JOIN Comida_Bebida cb ON cb.id_comida_bebida = d.id_comida_bebida
         WHERE o.id_ticket = :id
         ORDER BY cb.nombre"
    );
    $st->execute(['id' => $idTicket]);
    $combos = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $f) {
        $combos[] = ["nombre" => $f['nombre'], "cantidad" => (int) $f['cantidad']];
        $total += (float) $f['precio_unitario'] * (int) $f['cantidad'];
    }

    return [
        "id_ticket" => (int) $t['id_ticket'],
        "id_cliente" => (int) $t['id_cliente'],
        "estado"    => $t['situacion_boleto'],
        "codigo"    => qr_codigo((int) $t['id_ticket']),
        "firma"     => qr_firma((int) $t['id_ticket']),
        "pelicula"  => $filas[0]['titulo'] ?? '',
        "fecha"     => $filas[0]['fecha'] ?? '',
        "hora"      => $filas[0]['horario_inicio'] ?? '',
        "sala"      => $filas[0]['nombre_sala'] ?? '',
        "butacas"   => $butacas,
        "combos"    => $combos,
        "total"     => $total
    ];
}

// Deja solo ASCII imprimible (sin tildes) para que cualquier lector y las máquinas lo lean igual
function qr_ascii(string $t): string
{
    $t = strtr($t, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N',
        '¿' => '', '¡' => ''
    ]);
    return preg_replace('/[^\x20-\x7E]/', '', $t);
}

// Texto que va dentro del QR (el mismo para la pantalla y para el correo)
function qr_texto(array $d): string
{
    $combos = count($d['combos']) > 0
        ? implode(', ', array_map(fn($c) => $c['cantidad'] . 'x ' . $c['nombre'], $d['combos']))
        : 'Sin combos';

    $lineas = [
        'CINEMA LIGHTS',
        'Reserva: ' . $d['codigo'],
        'Pelicula: ' . $d['pelicula'],
        'Funcion: ' . $d['fecha'] . ' ' . substr((string) $d['hora'], 0, 5) . ' ' . $d['sala'],
        'Butacas: ' . implode(', ', $d['butacas']),
        'Combos: ' . $combos,
        'Total: $' . (int) round($d['total']),
        'Firma: ' . $d['firma']
    ];
    return implode("\n", array_map('qr_ascii', $lineas));
}

// Imagen PNG del QR (requiere la extensión GD, que viene activada en XAMPP)
function qr_png(string $texto, int $celda = 6, int $margen = 24): string
{
    if (!class_exists('QRCode')) {
        require_once __DIR__ . '/lib/qrcode.php';
    }
    $qr = QRCode::getMinimumQRCode($texto, QR_ERROR_CORRECT_LEVEL_M);
    $im = $qr->createImage($celda, $margen);
    ob_start();
    imagepng($im);
    $png = ob_get_clean();
    imagedestroy($im);
    return $png;
}