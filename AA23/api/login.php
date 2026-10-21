<?php
session_start();
header('Content-Type: application/json');
require_once '../includes/db.php';

$input    = json_decode(file_get_contents('php://input'), true) ?? [];
$email    = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if ($email === '' || $password === '') {
    http_response_code(400);
    echo json_encode(["error" => "Faltan credenciales obligatorias."]);
    exit;
}

try {
    $stmt = $pdo->prepare(
        "SELECT id_cliente, nombre, email, contrasena, rol FROM Cliente WHERE email = :email"
    );
    $stmt->execute(['email' => $email]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cliente && password_verify($password, $cliente['contrasena'])) {
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
        http_response_code(401);
        echo json_encode(["error" => "Credenciales inválidas."]);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["error" => "Error interno en el servidor."]);
}
