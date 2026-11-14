<?php
// GET api/funciones.php                         -> funciones de hoy a +6 dias
// GET api/funciones.php?desde=2026-11-02&dias=14 -> funciones de ese rango (max. 31 dias)
// GET api/funciones.php?slug=wicked&dias=14      -> solo las de una pelicula (para producto.html)
// Solo devuelve peliculas que estan en cartelera. Usa consultas preparadas.

require __DIR__ . '/../includes/db.php'; // deja $pdo listo

header('Content-Type: application/json; charset=utf-8');

date_default_timezone_set('America/Argentina/Buenos_Aires');

$desde = $_GET['desde'] ?? date('Y-m-d');
$f = DateTime::createFromFormat('Y-m-d', (string) $desde);
if (!$f || $f->format('Y-m-d') !== $desde) {
    http_response_code(400);
    echo json_encode(["error" => "Fecha inválida."]);
    exit;
}

$dias = (int) ($_GET['dias'] ?? 7);
$dias = max(1, min(31, $dias));
$hasta = (clone $f)->modify('+' . ($dias - 1) . ' day')->format('Y-m-d');

$sql = "
    SELECT i.id_itinerario, i.fecha, TIME_FORMAT(i.horario_inicio, '%H:%i') AS hora,
           i.precio, i.subtitulada, s.id_sala, s.nombre_sala,
           p.id_pelicula, p.slug, p.titulo, p.imagen, p.duracion,
           c.nombre_clasificacion AS clasificacion
    FROM Itinerario i
    JOIN Pelicula p      ON p.id_pelicula = i.id_pelicula
    JOIN Sala s          ON s.id_sala = i.id_sala
    JOIN Clasificacion c ON c.id_clasificacion = p.id_clasificacion
    WHERE i.fecha BETWEEN :desde AND :hasta
      AND p.en_cartelera = 1
";
$params = [':desde' => $desde, ':hasta' => $hasta];

if (!empty($_GET['slug'])) {
    $sql .= " AND p.slug = :slug ";
    $params[':slug'] = (string) $_GET['slug'];
}

$sql .= " ORDER BY i.fecha, p.titulo, i.horario_inicio ";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$funciones = array_map(function ($r) {
    $r['id_itinerario'] = (int) $r['id_itinerario'];
    $r['id_sala']       = (int) $r['id_sala'];
    $r['id_pelicula']   = (int) $r['id_pelicula'];
    $r['duracion']      = (int) $r['duracion'];
    $r['precio']        = (float) $r['precio'];
    $r['subtitulada']   = (bool) $r['subtitulada'];
    return $r;
}, $stmt->fetchAll());

echo json_encode($funciones);
