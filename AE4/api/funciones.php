<?php
// GET api/funciones.php                         -> funciones de hoy a +6 dias
// GET api/funciones.php?desde=2026-11-02&dias=14 -> funciones de ese rango (max. 31 dias)
// GET api/funciones.php?slug=wicked&dias=14      -> solo las de una pelicula (para producto.html)

require_once __DIR__ . '/../config/conexion.php';
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

try {
    $pdo = Conexion::getInstance();

    if (!empty($_GET['slug'])) {
        $stmt = $pdo->prepare("
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
              AND p.slug = :slug
            ORDER BY i.fecha, p.titulo, i.horario_inicio
        ");
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta, ':slug' => (string) $_GET['slug']]);
    } else {
        $stmt = $pdo->prepare("
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
            ORDER BY i.fecha, p.titulo, i.horario_inicio
        ");
        $stmt->execute([':desde' => $desde, ':hasta' => $hasta]);
    }

    $funciones = array_map(function ($r) {
        $r['id_itinerario'] = (int) $r['id_itinerario'];
        $r['id_sala']       = (int) $r['id_sala'];
        $r['id_pelicula']   = (int) $r['id_pelicula'];
        $r['duracion']      = (int) $r['duracion'];
        $r['precio']        = (float) $r['precio'];
        $r['subtitulada']   = (bool) $r['subtitulada'];
        return $r;
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));

    echo json_encode($funciones);
} catch (PDOException $e) {
    error_log('funciones.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(["error" => "Error interno en el servidor."]);
}
