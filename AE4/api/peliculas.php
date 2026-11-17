<?php
require_once __DIR__ . '/../config/conexion.php';
header('Content-Type: application/json; charset=utf-8');

$slug = $_GET['slug'] ?? null;

try {
    $pdo = Conexion::getInstance();

    if ($slug !== null) {
        $stmt = $pdo->prepare("
            SELECT p.id_pelicula, p.slug, p.titulo, p.duracion, p.idioma, p.imagen, p.descripcion,
                   c.nombre_clasificacion AS clasificacion,
                   GROUP_CONCAT(g.nombre_genero ORDER BY g.nombre_genero SEPARATOR ' / ') AS generos
            FROM Pelicula p
            JOIN Clasificacion c ON c.id_clasificacion = p.id_clasificacion
            LEFT JOIN Pelicula_Genero pg ON pg.id_pelicula = p.id_pelicula
            LEFT JOIN Genero g ON g.id_genero = pg.id_genero
            WHERE p.slug = :slug
            GROUP BY p.id_pelicula, p.slug, p.titulo, p.duracion, p.idioma, p.imagen, p.descripcion, c.nombre_clasificacion
            ORDER BY p.titulo
        ");
        $stmt->execute([':slug' => $slug]);
        $pelicula = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($pelicula ?: null);
    } else {
        $stmt = $pdo->prepare("
            SELECT p.id_pelicula, p.slug, p.titulo, p.duracion, p.idioma, p.imagen, p.descripcion,
                   c.nombre_clasificacion AS clasificacion,
                   GROUP_CONCAT(g.nombre_genero ORDER BY g.nombre_genero SEPARATOR ' / ') AS generos
            FROM Pelicula p
            JOIN Clasificacion c ON c.id_clasificacion = p.id_clasificacion
            LEFT JOIN Pelicula_Genero pg ON pg.id_pelicula = p.id_pelicula
            LEFT JOIN Genero g ON g.id_genero = pg.id_genero
            WHERE p.en_cartelera = 1
            GROUP BY p.id_pelicula, p.slug, p.titulo, p.duracion, p.idioma, p.imagen, p.descripcion, c.nombre_clasificacion
            ORDER BY p.titulo
        ");
        $stmt->execute();
        $peliculas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($peliculas);
    }
} catch (PDOException $e) {
    error_log('peliculas.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Error interno en el servidor."]);
}<?php
require_once __DIR__ . '/../config/conexion.php';
header('Content-Type: application/json; charset=utf-8');

$slug = $_GET['slug'] ?? null;

try {
    $pdo = Conexion::getInstance();

    if ($slug !== null) {
        $stmt = $pdo->prepare("
            SELECT p.id_pelicula, p.slug, p.titulo, p.duracion, p.idioma, p.imagen, p.descripcion,
                   c.nombre_clasificacion AS clasificacion,
                   GROUP_CONCAT(g.nombre_genero ORDER BY g.nombre_genero SEPARATOR ' / ') AS generos
            FROM Pelicula p
            JOIN Clasificacion c ON c.id_clasificacion = p.id_clasificacion
            LEFT JOIN Pelicula_Genero pg ON pg.id_pelicula = p.id_pelicula
            LEFT JOIN Genero g ON g.id_genero = pg.id_genero
            WHERE p.slug = :slug
            GROUP BY p.id_pelicula, p.slug, p.titulo, p.duracion, p.idioma, p.imagen, p.descripcion, c.nombre_clasificacion
            ORDER BY p.titulo
        ");
        $stmt->execute([':slug' => $slug]);
        $pelicula = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($pelicula ?: null);
    } else {
        $stmt = $pdo->prepare("
            SELECT p.id_pelicula, p.slug, p.titulo, p.duracion, p.idioma, p.imagen, p.descripcion,
                   c.nombre_clasificacion AS clasificacion,
                   GROUP_CONCAT(g.nombre_genero ORDER BY g.nombre_genero SEPARATOR ' / ') AS generos
            FROM Pelicula p
            JOIN Clasificacion c ON c.id_clasificacion = p.id_clasificacion
            LEFT JOIN Pelicula_Genero pg ON pg.id_pelicula = p.id_pelicula
            LEFT JOIN Genero g ON g.id_genero = pg.id_genero
            WHERE p.en_cartelera = 1
            GROUP BY p.id_pelicula, p.slug, p.titulo, p.duracion, p.idioma, p.imagen, p.descripcion, c.nombre_clasificacion
            ORDER BY p.titulo
        ");
        $stmt->execute();
        $peliculas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($peliculas);
    }
} catch (PDOException $e) {
    error_log('peliculas.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Error interno en el servidor."]);
}
