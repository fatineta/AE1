<?php
// Envío del QR por correo con PHPMailer (SMTP). La configuración va en includes/config.local.php:
//   'smtp_host' => 'smtp.gmail.com',  'smtp_port' => 587,  'smtp_secure' => 'tls',   // 'ssl' para el puerto 465
//   'smtp_user' => 'tu_cuenta@gmail.com',  'smtp_pass' => 'contraseña de aplicación',
//   'smtp_from' => 'tu_cuenta@gmail.com',  'smtp_from_name' => 'Cinema Lights'   // los dos últimos son opcionales
// Si falta la configuración, la compra igual se registra: solo no se manda el correo.

require_once __DIR__ . '/lib/PHPMailer/Exception.php';
require_once __DIR__ . '/lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/lib/PHPMailer/SMTP.php';
require_once __DIR__ . '/qr.php';

use PHPMailer\PHPMailer\PHPMailer;

function correo_enmascarado(string $email): string
{
    $partes = explode('@', $email, 2);
    if (count($partes) !== 2 || $partes[0] === '') {
        return '';
    }
    return substr($partes[0], 0, 1) . str_repeat('*', max(2, strlen($partes[0]) - 1)) . '@' . $partes[1];
}

// Devuelve ['enviado' => bool, 'destino' => 'a***@dominio', 'motivo' => 'sin_configurar'|'sin_destino'|'error_envio']
function enviar_qr_por_correo(string $destino, string $nombre, array $datos, string $textoQR): array
{
    $resultado = ['enviado' => false, 'destino' => correo_enmascarado($destino)];

    if (!filter_var($destino, FILTER_VALIDATE_EMAIL)) {
        $resultado['motivo'] = 'sin_destino';
        return $resultado;
    }

    $archivoCfg = __DIR__ . '/config.local.php';
    $cfg = is_file($archivoCfg) ? require $archivoCfg : [];
    if (!is_array($cfg) || empty($cfg['smtp_host'])) {
        $resultado['motivo'] = 'sin_configurar';
        return $resultado;
    }

    try {
        $png = qr_png($textoQR);
        $h = fn($t) => htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');

        $combos = count($datos['combos']) > 0
            ? implode(', ', array_map(fn($c) => $c['cantidad'] . 'x ' . $c['nombre'], $datos['combos']))
            : 'Sin combos';
        $funcion = $datos['fecha'] . ' · ' . substr((string) $datos['hora'], 0, 5) . ' · ' . $datos['sala'];
        $butacas = implode(', ', $datos['butacas']);
        $total   = '$' . number_format($datos['total'], 0, ',', '.');

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host     = $cfg['smtp_host'];
        $mail->Port     = (int) ($cfg['smtp_port'] ?? 587);
        $mail->Timeout  = 8;                       // no dejar la compra colgada si el servidor de correo no responde
        $mail->CharSet  = 'UTF-8';
        if (!empty($cfg['smtp_user'])) {
            $mail->SMTPAuth = true;
            $mail->Username = $cfg['smtp_user'];
            $mail->Password = $cfg['smtp_pass'] ?? '';
        } else {
            $mail->SMTPAuth = false;
        }
        $seguridad = strtolower((string) ($cfg['smtp_secure'] ?? 'tls'));
        if ($seguridad === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($seguridad === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure  = '';
            $mail->SMTPAutoTLS = false;
        }
        // En XAMPP/Windows suele faltar el bundle de certificados: se reutiliza el de TMDb si está
        $cacert = $cfg['smtp_cacert'] ?? ($cfg['tmdb_cacert'] ?? null);
        if ($cacert) {
            $mail->SMTPOptions = ['ssl' => ['cafile' => $cacert]];
        }

        $desde = $cfg['smtp_from'] ?? ($cfg['smtp_user'] ?? 'no-reply@cinemalights.local');
        $mail->setFrom($desde, $cfg['smtp_from_name'] ?? 'Cinema Lights');
        $mail->addAddress($destino, $nombre);
        $mail->Subject = 'Tu entrada Cinema Lights · reserva ' . $datos['codigo'];

        $mail->addStringEmbeddedImage($png, 'qrpedido', 'qr-' . $datos['codigo'] . '.png', 'base64', 'image/png');
        $mail->isHTML(true);
        $mail->Body =
            '<div style="font-family:Arial,sans-serif;max-width:520px;margin:auto;color:#1e293b">'
            . '<h2 style="color:#7a1d1e;margin-bottom:4px">¡Compra confirmada!</h2>'
            . '<p style="margin-top:0">Hola ' . $h($nombre) . ', este es el QR de tu pedido. '
            . 'Mostralo en las máquinas de autoatención del cine.</p>'
            . '<p style="text-align:center"><img src="cid:qrpedido" alt="Código QR de la reserva" width="260" height="260"></p>'
            . '<p style="text-align:center;font-weight:bold;color:#7a1d1e">Reserva ' . $h($datos['codigo']) . '</p>'
            . '<table style="width:100%;border-collapse:collapse;font-size:14px">'
            . '<tr><td style="padding:4px 0"><strong>Película</strong></td><td>' . $h($datos['pelicula']) . '</td></tr>'
            . '<tr><td style="padding:4px 0"><strong>Función</strong></td><td>' . $h($funcion) . '</td></tr>'
            . '<tr><td style="padding:4px 0"><strong>Butacas</strong></td><td>' . $h($butacas) . '</td></tr>'
            . '<tr><td style="padding:4px 0"><strong>Combos</strong></td><td>' . $h($combos) . '</td></tr>'
            . '<tr><td style="padding:4px 0"><strong>Total</strong></td><td>' . $h($total) . '</td></tr>'
            . '</table>'
            . '<p style="font-size:12px;color:#64748b">Cinema Lights Posadas · Este correo se generó automáticamente.</p>'
            . '</div>';
        $mail->AltBody = "Compra confirmada. Reserva {$datos['codigo']}\n"
            . "Pelicula: {$datos['pelicula']}\nFuncion: {$funcion}\nButacas: {$butacas}\nCombos: {$combos}\nTotal: {$total}\n"
            . "El QR va en la imagen adjunta de este correo.";

        $mail->send();
        $resultado['enviado'] = true;
    } catch (Throwable $e) {
        // El detalle queda en el log de PHP; al navegador no se le muestra
        error_log('Cinema Lights - error al enviar el QR por correo: ' . $e->getMessage());
        $resultado['motivo'] = 'error_envio';
    }
    return $resultado;
}