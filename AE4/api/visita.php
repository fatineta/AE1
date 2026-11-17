<?php
// Suma una visita al contador del día. No devuelve datos ni usa información del visitante.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

require_once '../includes/db.php';

try {
    $pdo->exec(
        "INSERT INTO Visita (dia, cantidad) VALUES (CURDATE(), 1)
         ON DUPLICATE KEY UPDATE cantidad = cantidad + 1"
    );
    http_response_code(204);
} catch (PDOException $e) {
    http_response_code(500);
}
