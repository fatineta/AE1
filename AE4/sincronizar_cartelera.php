<?php
// Sincroniza la tabla Pelicula con la cartelera real de TMDb (now_playing).
//  - Si la pelicula ya existe (por tmdb_id o por titulo) la actualiza.
//  - Si es nueva la inserta con slug, clasificacion y generos.
//  - Las que ya no estan en cartelera NO se borran (tienen itinerarios/compras):
//    se marcan en_cartelera = 0 y api/peliculas.php deja de mostrarlas.
// Requiere haber corrido sql/tmdb_cartelera.sql una vez.
// Uso: php sincronizar_cartelera.php   (o abrir en el navegador, solo desde localhost)

require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/tmdb.php';

header('Content-Type: text/plain; charset=utf-8');

if (PHP_SAPI !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    die("Solo se puede ejecutar desde la consola o desde localhost.\n");
}

$configLocal = __DIR__ . '/includes/config.local.php';
if (!is_file($configLocal)) {
    die("Falta includes/config.local.php (copialo de config.local.example.php).\n");
}
$cfg = require $configLocal;
if (empty($cfg['tmdb_token'])) {
    die("Falta 'tmdb_token' en includes/config.local.php.\n");
}

$region = $cfg['tmdb_region'] ?? 'AR';   // AR = cartelera argentina; probar 'US' para la de EE.UU.
$tope   = (int) ($cfg['tmdb_max'] ?? 10);
$tmdb   = new TmdbService($cfg['tmdb_token'], 'es-AR', $cfg['tmdb_cacert'] ?? null);

function sinTildes(string $t): string
{
    $mapa = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
             'Á'=>'A','É'=>'E','Í'=>'I','Ó'=>'O','Ú'=>'U','Ü'=>'U','Ñ'=>'N'];
    return strtr($t, $mapa);
}

function hacerSlug(string $titulo): string
{
    $s = strtolower(sinTildes($titulo));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim($s, '-');
    return substr($s !== '' ? $s : 'pelicula', 0, 45);
}

echo "Pidiendo la cartelera de $region a TMDb...\n";
$lista = $tmdb->enCartelera($region, $tope);
if (!$lista) {
    echo "No se obtuvo ninguna pelicula.\n";
    echo ($tmdb->ultimoError ?? 'Sin detalle del error.') . "\n";
    echo "Pistas: extension curl activa en php.ini, certificados SSL (tmdb_cacert), token correcto.\n";
    exit(1);
}
echo count($lista) . " peliculas en cartelera.\n\n";

// Clasificaciones y generos que ya hay en la base
$clasif = $pdo->query("SELECT nombre_clasificacion, id_clasificacion FROM Clasificacion")->fetchAll(PDO::FETCH_KEY_PAIR);
$idPorDefecto = $clasif['+13'] ?? reset($clasif);

$stBuscarTmdb   = $pdo->prepare("SELECT id_pelicula FROM Pelicula WHERE tmdb_id = :t");
$stBuscarTitulo = $pdo->prepare("SELECT id_pelicula FROM Pelicula WHERE LOWER(titulo) = LOWER(:ti) AND tmdb_id IS NULL LIMIT 1");
$stSlugExiste   = $pdo->prepare("SELECT 1 FROM Pelicula WHERE slug = :s AND id_pelicula <> :id");
$stInsert = $pdo->prepare("
    INSERT INTO Pelicula (titulo, slug, tmdb_id, en_cartelera, fecha_estreno, duracion, idioma, imagen, descripcion, id_clasificacion)
    VALUES (:titulo, :slug, :tmdb, 1, :estreno, :dur, :idioma, :img, :desc, :clas)");
$stUpdate = $pdo->prepare("
    UPDATE Pelicula
       SET titulo = :titulo, tmdb_id = :tmdb, en_cartelera = 1, fecha_estreno = :estreno,
           duracion = :dur, idioma = :idioma, imagen = :img, descripcion = :desc,
           id_clasificacion = :clas
     WHERE id_pelicula = :id");
$stSetSlug  = $pdo->prepare("UPDATE Pelicula SET slug = :s WHERE id_pelicula = :id AND slug IS NULL");
$stGenero   = $pdo->prepare("INSERT IGNORE INTO Genero (nombre_genero) VALUES (:g)");
$stIdGenero = $pdo->prepare("SELECT id_genero FROM Genero WHERE nombre_genero = :g");
$stLimpiaPG = $pdo->prepare("DELETE FROM Pelicula_Genero WHERE id_pelicula = :id");
$stPG       = $pdo->prepare("INSERT IGNORE INTO Pelicula_Genero (id_pelicula, id_genero) VALUES (:p, :g)");

$idsTmdbVigentes = [];
$nuevas = $actualizadas = $omitidas = 0;

foreach ($lista as $r) {
    $titulo = $r['title'] ?? '(sin titulo)';
    echo "- $titulo ... ";

    $d = $tmdb->obtenerDetalle((int) $r['id'], ['append_to_response' => 'release_dates']);
    if (!$d) {
        echo "no se pudo traer el detalle (" . ($tmdb->ultimoError ?? '?') . "), se omite.\n";
        $omitidas++;
        continue;
    }

    $descripcion = trim($d['overview'] ?? '');
    $titulo = $d['title'] ?? $titulo;

    $nombreClasif = $tmdb->clasificacionDe($d, $region);
    $idClasif = $clasif[$nombreClasif] ?? $idPorDefecto;
    $datos = [
        ':titulo'  => mb_substr($titulo, 0, 100),
        ':tmdb'    => (int) $d['id'],
        ':estreno' => !empty($d['release_date']) ? $d['release_date'] : null,
        ':dur'     => !empty($d['runtime']) ? (int) $d['runtime'] : 100,
        ':idioma'  => $tmdb->idiomaLegible($d['original_language'] ?? null),
        ':img'     => $tmdb->urlPoster($d['poster_path'] ?? null),
        ':desc'    => $descripcion !== '' ? $descripcion : null,
        ':clas'    => $idClasif,
    ];

    try {
        $pdo->beginTransaction();

        $stBuscarTmdb->execute([':t' => $d['id']]);
        $id = $stBuscarTmdb->fetchColumn();
        if (!$id) {
            $stBuscarTitulo->execute([':ti' => $titulo]);
            $id = $stBuscarTitulo->fetchColumn();
        }

        if ($id) {
            $stUpdate->execute($datos + [':id' => $id]);
            $stSetSlug->execute([':s' => hacerSlug($titulo), ':id' => $id]); // solo si no tenia slug
            $accion = 'actualizada';
            $actualizadas++;
        } else {
            $slug = hacerSlug($titulo);
            $stSlugExiste->execute([':s' => $slug, ':id' => 0]);
            if ($stSlugExiste->fetchColumn()) {
                $slug = substr($slug, 0, 38) . '-' . $d['id'];
            }
            $stInsert->execute($datos + [':slug' => $slug]);
            // el INSERT usa :titulo, :tmdb, etc. -> mismo arreglo de datos
            $id = (int) $pdo->lastInsertId();
            $accion = 'nueva';
            $nuevas++;
        }

        // Generos (siempre sin tildes, como los de la base)
        $stLimpiaPG->execute([':id' => $id]);
        foreach ($d['genres'] ?? [] as $g) {
            $nombre = sinTildes($g['name']);
            $stGenero->execute([':g' => $nombre]);
            $stIdGenero->execute([':g' => $nombre]);
            $idG = $stIdGenero->fetchColumn();
            if ($idG) {
                $stPG->execute([':p' => $id, ':g' => $idG]);
            }
        }

        $pdo->commit();
        $idsTmdbVigentes[] = (int) $d['id'];
        echo "$accion (" . ($nombreClasif ?? 'sin clasificacion en TMDb -> +13') . ").\n";
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "ERROR: " . $e->getMessage() . "\n";
        $omitidas++;
    }

    usleep(300000);
}

// Todo lo que NO vino de la cartelera de TMDb (incluidas las peliculas de prueba
// viejas, que no tienen tmdb_id) queda oculto. No se borra porque tiene itinerarios/compras.
if ($idsTmdbVigentes) {
    $marcas = implode(',', array_fill(0, count($idsTmdbVigentes), '?'));
    $st = $pdo->prepare("UPDATE Pelicula SET en_cartelera = 0 WHERE tmdb_id IS NULL OR tmdb_id NOT IN ($marcas)");
    $st->execute($idsTmdbVigentes);
    echo "\nOcultadas (no estan en la cartelera de TMDb): " . $st->rowCount() . "\n";
}

echo "\nNuevas: $nuevas | Actualizadas: $actualizadas | Omitidas: $omitidas\nListo.\n";