<?php
// Configuración central de la cookie de sesión (H3).
// Se incluye en lugar de llamar a session_start() a secas.
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,   // el JavaScript no puede leer la cookie
        'samesite' => 'Lax',  // no viaja en peticiones iniciadas por otros sitios
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}
