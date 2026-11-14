<?php
require_once __DIR__ . '/../includes/sesion.php';
header('Content-Type: application/json');
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode(["error" => "Método no permitido."]);
    exit;
}

// H3: límite de intentos fallidos (5 intentos cada 10 minutos por email + IP)
const MAX_INTENTOS = 5;
const VENTANA_SEGUNDOS = 600;

function archivo_intentos(string $email): string {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'local';
    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cinema_login_' . hash('sha256', strtolower($email) . '|' . $ip) . '.json';
}
function leer_intentos(string $archivo): array {
    $d = is_file($archivo) ? json_decode((string)@file_get_contents($archivo), true) : null;
    if (!is_array($d) || (time() - ($d['ultimo'] ?? 0)) > VENTANA_SEGUNDOS) {
        return ['cuenta' => 0, 'ultimo' => 0];
    }
    return $d;
}

// Hash BCRYPT de relleno: se verifica igual cuando el email no existe para igualar los tiempos de respuesta
const HASH_FALSO = '$2y$10$7/zLpbvuEguVhAtZL3Mfp.SoVI8Wb2xb7mfFondAKUohRcuXOu1bi';

$input    = json_decode(file_get_contents('php://input'), true) ?? [];
$email    = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if ($email === '' || $password === '') {
    http_response_code(400);
    echo json_encode(["error" => "Faltan credenciales obligatorias."]);
    exit;
}

$archivoIntentos = archivo_intentos($email);
$intentos = leer_intentos($archivoIntentos);
if ($intentos['cuenta'] >= MAX_INTENTOS) {
    http_response_code(429);
    header('Retry-After: ' . VENTANA_SEGUNDOS);
    echo json_encode(["error" => "Demasiados intentos fallidos. Probá de nuevo en unos minutos."]);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "SELECT id_cliente, nombre, email, contrasena, rol FROM Cliente WHERE email = :email"
    );
    $stmt->execute(['email' => $email]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    $passwordOk = password_verify($password, $cliente ? $cliente['contrasena'] : HASH_FALSO);

    if ($cliente && $passwordOk) {
        @unlink($archivoIntentos);
        session_regenerate_id(true);

        $_SESSION['usuario_id']     = (int)$cliente['id_cliente'];
        $_SESSION['usuario_nombre'] = $cliente['nombre'];
        $_SESSION['usuario_email']  = $cliente['email'];
        $_SESSION['usuario_rol']    = $cliente['rol'];

        http_response_code(200);
        echo json_encode([
            "success" => true,
            "mensaje" => "Autenticación exitosa",
            "nombre"  => $cliente['nombre'], // se escapa en el frontend con textContent
            "rol"     => $cliente['rol']
        ]);
    } else {
        $intentos['cuenta']++;
        $intentos['ultimo'] = time();
        @file_put_contents($archivoIntentos, json_encode($intentos), LOCK_EX);

        http_response_code(401);
        echo json_encode(["error" => "Credenciales inválidas."]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error interno en el servidor."]);
}
