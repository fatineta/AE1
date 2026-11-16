<?php
// Genera funciones (Itinerario) para los proximos dias con las peliculas en cartelera,
// repartidas en todas las salas, sin superponerse. Tambien crea las butacas de cada funcion
// (Asiento_Itinerario) para que se puedan elegir asientos.
// NO toca los dias que ya tienen funciones cargadas.
//
// Uso: abrir en el navegador (solo desde localhost):  generar_funciones.php?dias=14
//      o por consola: php generar_funciones.php 14
// Es una herramienta para cargar datos de prueba/demo: despues podes editar o borrar
// funciones a mano en la base.

require __DIR__ . '/includes/db.php';

header('Content-Type: text/plain; charset=utf-8');
date_default_timezone_set('America/Argentina/Buenos_Aires');

if (PHP_SAPI !== 'cli' && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    die("Solo se puede ejecutar desde la consola o desde localhost.\n");
}

const PRECIO_BASE = 4800.00;           // mismo valor que PRECIO_ASIENTO en app.js
const PRIMERA_FUNCION = 14 * 60;       // 14:00 (en minutos)
const ULTIMO_INICIO   = 22 * 60 + 30;  // ninguna funcion arranca despues de las 22:30
const LIMPIEZA_SALA   = 20;            // minutos entre una funcion y la siguiente

$dias = PHP_SAPI === 'cli' ? (int) ($argv[1] ?? 14) : (int) ($_GET['dias'] ?? 14);
$dias = max(1, min(31, $dias));

$pelis = $pdo->query("SELECT id_pelicula, titulo, duracion, idioma FROM Pelicula WHERE en_cartelera = 1 ORDER BY id_pelicula")->fetchAll();
$salas = $pdo->query("SELECT id_sala, nombre_sala FROM Sala ORDER BY id_sala")->fetchAll();
if (!$pelis || !$salas) {
    die("Faltan peliculas en cartelera (corre sincronizar_cartelera.php) o salas en la base.\n");
}

$stHayFunciones = $pdo->prepare("SELECT COUNT(*) FROM Itinerario WHERE fecha = :f");
$stFuncion = $pdo->prepare("
    INSERT INTO Itinerario (fecha, horario_inicio, precio, subtitulada, id_pelicula, id_sala)
    VALUES (:f, :h, :p, :sub, :peli, :sala)");
$stButacas = $pdo->prepare("
    INSERT INTO Asiento_Itinerario (id_asiento, id_itinerario, estado, precio_asiento)
    SELECT a.id_asiento, :it, 'disponible',
           ROUND(CASE WHEN a.numero_asiento LIKE 'A%' THEN :p1 * 1.2 ELSE :p2 END, 2)
    FROM Asiento a WHERE a.id_sala = :sala");

$hhmm = fn(int $min) => sprintf('%02d:%02d:00', intdiv($min, 60), $min % 60);

$totalCreadas = 0;
for ($i = 0; $i < $dias; $i++) {
    $fecha = date('Y-m-d', strtotime("+$i day"));

    $stHayFunciones->execute([':f' => $fecha]);
    if ($stHayFunciones->fetchColumn() > 0) {
        echo "$fecha: ya tiene funciones, se omite.\n";
        continue;
    }

    $contador = $i * 3; // cada dia arranca con otra pelicula para que haya variedad
    $delDia = 0;

    foreach ($salas as $idx => $sala) {
        $inicio = PRIMERA_FUNCION + $idx * 30; // las salas no arrancan todas a la misma hora
        while ($inicio <= ULTIMO_INICIO) {
            $peli = $pelis[$contador % count($pelis)];
            $contador++;
            $duracion = max(60, (int) $peli['duracion']);

            // Peliculas en otro idioma: subtituladas de noche
            $subtitulada = ($peli['idioma'] !== 'Espanol' && $inicio >= 19 * 60) ? 1 : 0;

            try {
                $pdo->beginTransaction();
                $stFuncion->execute([
                    ':f' => $fecha, ':h' => $hhmm($inicio), ':p' => PRECIO_BASE,
                    ':sub' => $subtitulada, ':peli' => $peli['id_pelicula'], ':sala' => $sala['id_sala'],
                ]);
                $idFuncion = (int) $pdo->lastInsertId();
                $stButacas->execute([
                    ':it' => $idFuncion, ':p1' => PRECIO_BASE, ':p2' => PRECIO_BASE, ':sala' => $sala['id_sala'],
                ]);
                $pdo->commit();
                $delDia++;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                echo "  ! $fecha {$hhmm($inicio)} {$sala['nombre_sala']}: " . $e->getMessage() . "\n";
            }

            // siguiente funcion: fin de esta + limpieza, redondeado a multiplos de 5 min
            $inicio += $duracion + LIMPIEZA_SALA;
            $inicio = (int) (ceil($inicio / 5) * 5);
        }
    }

    echo "$fecha: $delDia funciones creadas.\n";
    $totalCreadas += $delDia;
}

echo "\nTotal: $totalCreadas funciones nuevas.\nListo.\n";
