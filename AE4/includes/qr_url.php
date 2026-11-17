<?php
// Enlace que va DENTRO del QR: al escanearlo, el celular abre validar.html con el código y la firma.
// La firma (HMAC de includes/qr.php) hace que nadie pueda inventar un enlace válido.
require_once __DIR__ . '/qr.php';

// Dirección base del sitio tal como la ve el CELULAR (no "localhost": en el celular localhost es el celular).
// Se configura en includes/config.local.php:
//     'public_base_url' => 'http://192.168.1.35/fatineta_AE1_AE2_AA23_corregido',
// Si no está configurada, intenta armarla sola con la IP de la PC (no siempre acierta en Windows).
function qr_base_url(): string
{
    $archivoCfg = __DIR__ . '/config.local.php';
    if (is_file($archivoCfg)) {
        $cfg = require $archivoCfg;
        if (is_array($cfg) && !empty($cfg['public_base_url'])) {
            return rtrim((string) $cfg['public_base_url'], '/');
        }
    }

    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    $soloHost = preg_replace('/:\d+$/', '', $host);
    if (in_array($soloHost, ['localhost', '127.0.0.1', '::1', '[::1]'], true)) {
        $ip = gethostbyname(gethostname());
        if (filter_var($ip, FILTER_VALIDATE_IP) && $ip !== '127.0.0.1') {
            $puerto = preg_match('/(:\d+)$/', $host, $m) ? $m[1] : '';
            $host = $ip . $puerto;
        }
    }

    $esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    // /carpeta/api/comprar.php  ->  /carpeta
    $carpeta = rtrim(str_replace('\\', '/', dirname(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/api/x.php')))), '/');
    return $esquema . '://' . $host . $carpeta;
}

// Contenido del QR de un pedido (recibe el arreglo de qr_datos_ticket)
function qr_url(array $d): string
{
    return qr_base_url() . '/validar.html?r=' . rawurlencode($d['codigo']) . '&f=' . $d['firma'];
}
