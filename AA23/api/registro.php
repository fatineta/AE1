<?php
header('Content-Type: application/json');
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Método no permitido."]);
    exit;
}

$input    = json_decode(file_get_contents('php://input'), true) ?? [];
$nombre   = trim($input['nombre'] ?? '');
$email    = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if ($nombre === '' || $email === '' || $password === '') {
    http_response_code(400);
    echo json_encode(["error" => "Nombre, email y contraseña son obligatorios."]);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120) {
    http_response_code(400);
    echo json_encode(["error" => "Email inválido."]);
    exit;
}
if (mb_strlen($nombre) > 100) {
    http_response_code(400);
    echo json_encode(["error" => "El nombre es demasiado largo."]);
    exit;
}
if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(["error" => "La contraseña debe tener al menos 8 caracteres."]);
    exit;
}

$hash = password_hash($password, PASSWORD_BCRYPT);

try {
    // El rol NUNCA viene del cliente: todo registro público es 'cliente'
    $stmt = $pdo->prepare(
        "INSERT INTO Cliente (nombre, email, contrasena, rol)
         VALUES (:nombre, :email, :contrasena, 'cliente')"
    );
    $stmt->execute([
        'nombre'     => $nombre,
        'email'      => $email,
        'contrasena' => $hash,
    ]);

    http_response_code(201);
    echo json_encode(["success" => true, "mensaje" => "Registro exitoso. Ya puedes iniciar sesión."]);

} catch (PDOException $e) {
    if (($e->errorInfo[1] ?? 0) == 1062) { // UNIQUE uq_cliente_email
        http_response_code(409);
        echo json_encode(["error" => "Ese email ya está registrado."]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Error interno en el servidor."]);
    }
}
