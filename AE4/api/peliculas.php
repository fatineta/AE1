<?php
// GET api/peliculas.php            -> lista completa, para listado_tabla.html / listado_box.html
// GET api/peliculas.php?slug=wicked -> una sola pelicula, para producto.html

require __DIR__ . '/../includes/db.php'; // deja $pdo listo

header('Content-Type: application/json; charset=utf-8');

$slug = $_GET['slug'] ?? null;

$sql = "
    SELECT p.id_pelicula, p.slug, p.titulo, p.duracion, p.idioma, p.imagen, p.descripcion,
           c.nombre_clasificacion AS clasificacion,
           GROUP_CONCAT(g.nombre_genero ORDER BY g.nombre_genero SEPARATOR ' / ') AS generos
    FROM pelicula p
    JOIN clasificacion c ON c.id_clasificacion = p.id_clasificacion
    LEFT JOIN pelicula_genero pg ON pg.id_pelicula = p.id_pelicula
    LEFT JOIN genero g ON g.id_genero = pg.id_genero
";

if ($slug !== null) {
    $sql .= " WHERE p.slug = :slug ";
} else {
    $sql .= " WHERE p.en_cartelera = 1 ";
}

$sql .= "
    GROUP BY p.id_pelicula, p.slug, p.titulo, p.duracion, p.idioma, p.imagen, p.descripcion, c.nombre_clasificacion
    ORDER BY p.titulo
";

$stmt = $pdo->prepare($sql);
$stmt->execute($slug !== null ? [':slug' => $slug] : []);
$peliculas = $stmt->fetchAll();

if ($slug !== null) {
    echo json_encode($peliculas[0] ?? null);
} else {
    echo json_encode($peliculas);
}