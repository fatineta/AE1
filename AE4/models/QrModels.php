<?php
// /models/UsuarioModel.php
// Modelo de Usuario/Cliente: acceso a datos puro con PDO Prepared Statements.
// Extrae el SQL que estaba inline en api/login.php y api/registro.php.

class UsuarioModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Busca un cliente por email. Devuelve el registro completo o null.
    // El llamador (login.php) es quien verifica la contraseña con password_verify().
    public function buscarPorEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id_cliente, nombre, email, contrasena, rol
             FROM Cliente
             WHERE email = :email"
        );
        $stmt->execute([':email' => $email]);
        $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
        return $cliente ?: null;
    }

    // Crea un cliente nuevo. El hash ya viene calculado desde el controlador (BCRYPT).
    // El rol SIEMPRE es 'cliente': el controlador no lo recibe del request.
    public function crear(string $nombre, string $email, string $hash): int
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO Cliente (nombre, email, contrasena, rol)
             VALUES (:nombre, :email, :contrasena, 'cliente')"
        );
        $stmt->execute([
            ':nombre'     => $nombre,
            ':email'      => $email,
            ':contrasena' => $hash,
        ]);
        return (int) $this->pdo->lastInsertId();
    }
}