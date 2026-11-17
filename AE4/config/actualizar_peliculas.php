<?php
// Script de administracion: recorre la tabla pelicula y la actualiza (duracion, idioma,
// imagen, descripcion) con datos de TMDb. Se corre a mano cuando haga falta, no lo
// enlaces desde ninguna pagina publica del sitio.
//
// Requisito: includes/config.local.php debe existir y tener la clave 'tmdb_token'
// (copiar includes/config.local.example.php y completarla).
//
// Uso: php actualizar_peliculas.php   (o abrirlo en el navegador apuntando a esta ruta)

require_once __DIR__ . '/config/conexion.php';
$pdo = Conexion::getInstance();
require __DIR__ . '/includes/tmdb.php'; // clase TmdbService

header('Content-Type: text/plain; charset=utf-8');

if (PHP_SAPI !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    die("Solo desde consola o localhost.\n");
}

$configLocal = __DIR__ . '/includes/config.local.php';
if (!is_file($configLocal)) {
    die("Falta includes/config.local.php. Copialo de config.local.example.php y completalo.\n");
}
$cfg = require $configLocal;
if (empty($cfg['tmdb_token'])) {
    die("Falta la clave 'tmdb_token' en includes/config.local.php.\n");
}

$tmdb = new TmdbService($cfg['tmdb_token']);

$peliculas = $pdo->query("SELECT id_pelicula, titulo FROM pelicula")->fetchAll();

$stmtUpdate = $pdo->prepare("
    UPDATE pelicula
       SET duracion    = :duracion,
           idioma      = :idioma,
           imagen      = :imagen,
           descripcion = :descripcion
     WHERE id_pelicula = :id
");

foreach ($peliculas as $p) {
    echo "Buscando: {$p['titulo']} ... ";

    $resultado = $tmdb->buscarPorTitulo($p['titulo']);
    if (!$resultado) {
        echo "no encontrada en TMDb, se omite.\n";
        continue;
    }

    $detalle = $tmdb->obtenerDetalle($resultado['id']);
    if (!$detalle) {
        echo "no se pudo traer el detalle, se omite.\n";
        continue;
    }

    $stmtUpdate->execute([
        ':duracion'    => $detalle['runtime'] ?: 90, // TMDb a veces no tiene runtime cargado
        ':idioma'      => $tmdb->idiomaLegible($detalle['original_language'] ?? null),
        ':imagen'      => $tmdb->urlPoster($detalle['poster_path']),
        ':descripcion' => $detalle['overview'] ?: null,
        ':id'          => $p['id_pelicula'],
    ]);

    echo "actualizada (TMDb id {$detalle['id']}).\n";

    usleep(300000); // pausa chica para no saturar la API
}

echo "\nListo.\n";
