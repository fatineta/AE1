<?php
// Sincroniza la tabla Pelicula con la cartelera real de TMDb (now_playing).
// Programa 12 funciones diarias (3 salas x 4 turnos) rotando las 20 peliculas a lo largo de 14 dias.

require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/tmdb.php';

header('Content-Type: text/plain; charset=utf-8');
date_default_timezone_set('America/Argentina/Buenos_Aires');

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

$region = $cfg['tmdb_region'] ?? 'AR';
$tope   = (int) ($cfg['tmdb_max'] ?? 20);
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
    exit(1);
}
echo count($lista) . " peliculas en cartelera.\n\n";

// 1. Limpiar itinerarios y asientos anteriores sin errores de FK
try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
    $pdo->exec("DELETE FROM Asiento_Itinerario");
    $pdo->exec("DELETE FROM Itinerario");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    echo "Itinerarios y asientos anteriores limpiados correctamente.\n\n";
} catch (Throwable $e) {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
    echo "Nota al limpiar tablas: " . $e->getMessage() . "\n";
}

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
$peliculasActivas = [];
$nuevas = $actualizadas = $omitidas = 0;

// 2. Guardar o actualizar todas las peliculas de TMDb
foreach ($lista as $r) {
    $titulo = $r['title'] ?? '(sin titulo)';
    echo "- $titulo ... ";

    $d = $tmdb->obtenerDetalle((int) $r['id'], ['append_to_response' => 'release_dates']);
    if (!$d) {
        echo "no se pudo traer el detalle, se omite.\n";
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
            $stSetSlug->execute([':s' => hacerSlug($titulo), ':id' => $id]);
            $accion = 'actualizada';
            $actualizadas++;
        } else {
            $slug = hacerSlug($titulo);
            $stSlugExiste->execute([':s' => $slug, ':id' => 0]);
            if ($stSlugExiste->fetchColumn()) {
                $slug = substr($slug, 0, 38) . '-' . $d['id'];
            }
            $stInsert->execute($datos + [':slug' => $slug]);
            $id = (int) $pdo->lastInsertId();
            $accion = 'nueva';
            $nuevas++;
        }

        // Generos
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
        $peliculasActivas[] = (int) $id;
        echo "$accion.\n";
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo "ERROR: " . $e->getMessage() . "\n";
        $omitidas++;
    }

    usleep(150000);
}

// Ocultar películas que ya no están vigentes
if ($idsTmdbVigentes) {
    $marcas = implode(',', array_fill(0, count($idsTmdbVigentes), '?'));
    $st = $pdo->prepare("UPDATE Pelicula SET en_cartelera = 0 WHERE tmdb_id IS NULL OR tmdb_id NOT IN ($marcas)");
    $st->execute($idsTmdbVigentes);
    echo "\nOcultadas: " . $st->rowCount() . "\n";
}

// 3. Programar grilla completa de 14 dias (3 salas x 4 horarios = 12 peliculas distintas por dia)
$totalPelis = count($peliculasActivas);
if ($totalPelis > 0) {
    echo "\nProgramando grilla rotativa de 14 dias con $totalPelis peliculas...\n";

    $salas = $pdo->query("SELECT id_sala FROM Sala ORDER BY id_sala")->fetchAll(PDO::FETCH_COLUMN);
    if (!$salas) {
        $salas = [1, 2, 3];
    }

    // Horarios espaciados (cada 3h 15m) para evitar colisiones con el trigger de duracion
    $horarios = ['13:30:00', '16:45:00', '20:00:00', '23:15:00'];

    $stInsIt      = $pdo->prepare("INSERT INTO Itinerario (fecha, horario_inicio, precio, subtitulada, id_pelicula, id_sala) VALUES (?, ?, ?, ?, ?, ?)");
    $stAsientos   = $pdo->prepare("SELECT id_asiento, numero_asiento FROM Asiento WHERE id_sala = ?");
    $stInsAsiento = $pdo->prepare("INSERT IGNORE INTO Asiento_Itinerario (id_asiento, id_itinerario, estado, precio_asiento) VALUES (?, ?, 'disponible', ?)");

    // Cachear asientos por sala para acelerar la insercion masiva
    $asientosPorSala = [];
    foreach ($salas as $idSala) {
        $stAsientos->execute([$idSala]);
        $asientosPorSala[$idSala] = $stAsientos->fetchAll();
    }

    $funcionesCreadas = 0;
    for ($dia = 0; $dia < 14; $dia++) {
        $fechaFuncion = date('Y-m-d', strtotime("+$dia days"));
        $slotDelDia = 0;

        foreach ($salas as $idSala) {
            foreach ($horarios as $idxHora => $horarioInicio) {
                // Desplazamos el indice segun el dia para que cada jornada tenga peliculas distintas
                $indicePeli = ($dia * 5 + $slotDelDia) % $totalPelis;
                $idPelicula = $peliculasActivas[$indicePeli];
                $precio = ($idxHora >= 2) ? 6000.00 : 5000.00; // Noche un poco mas cara
                $subtitulada = ($idxHora % 2 === 1) ? 1 : 0;

                try {
                    $stInsIt->execute([$fechaFuncion, $horarioInicio, $precio, $subtitulada, $idPelicula, $idSala]);
                    $idItinerario = $pdo->lastInsertId();

                    foreach ($asientosPorSala[$idSala] as $asiento) {
                        $precioAsiento = str_starts_with($asiento['numero_asiento'], 'A') ? $precio * 1.2 : $precio;
                        $stInsAsiento->execute([$asiento['id_asiento'], $idItinerario, $precioAsiento]);
                    }
                    $funcionesCreadas++;
                } catch (Throwable $ex) {
                    echo "Aviso ($fechaFuncion $horarioInicio Sala $idSala): " . $ex->getMessage() . "\n";
                }

                $slotDelDia++;
            }
        }
    }
    echo "Funciones creadas: $funcionesCreadas (hasta " . (count($salas) * count($horarios)) . " peliculas distintas por dia).\n";
}

echo "\nNuevas: $nuevas | Actualizadas: $actualizadas | Omitidas: $omitidas\nListo.\n";
