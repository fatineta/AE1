USE CINE_DB;

-- =====================================================================
-- Migracion: saca las peliculas de prueba de la AE2 y carga las 6
-- peliculas reales que ya se ven en listado_tabla.html, listado_box.html
-- y producto.html.
-- =====================================================================

-- 1) Columna para enganchar cada fila con el slug que usan las URLs
--    del sitio (?peli=spiderman, ?peli=wicked, etc.)
ALTER TABLE Pelicula
  ADD COLUMN slug VARCHAR(50) NULL UNIQUE AFTER titulo;

-- 2) Clasificacion +10 (la usan Spider-Verse y, segun la tabla, no Wicked)
INSERT INTO Clasificacion (nombre_clasificacion, descripcion_clasificacion)
SELECT '+10', 'Mayores de 10 anios'
WHERE NOT EXISTS (SELECT 1 FROM Clasificacion WHERE nombre_clasificacion = '+10');

-- 3) Generos que faltan (los de la AE2 no cubrian estos)
INSERT INTO Genero (nombre_genero)
SELECT g FROM (
  SELECT 'Accion' AS g UNION SELECT 'Fantasia' UNION SELECT 'Thriller'
  UNION SELECT 'Musical' UNION SELECT 'Romance' UNION SELECT 'Anime'
) nuevos
WHERE NOT EXISTS (SELECT 1 FROM Genero WHERE Genero.nombre_genero = nuevos.g);

-- 4) Limpieza de las peliculas de prueba y todo lo que colgaba de ellas
--    (en este orden para no chocar con las FK)
DELETE FROM Compra;
DELETE FROM Asiento_Itinerario;
DELETE FROM Itinerario;
DELETE FROM Pelicula_Genero;
DELETE FROM Pelicula;
ALTER TABLE Pelicula AUTO_INCREMENT = 1;

-- 5) Las 6 peliculas reales del sitio
INSERT INTO Pelicula (titulo, slug, duracion, idioma, imagen, descripcion, id_clasificacion) VALUES
('Spider-Man: Brand New Day', 'spiderman', 135, 'Espanol',
 'https://sm.ign.com/ign_es/image/n/new-spider/new-spider-man-brand-new-day-posters-released_eca9.jpg',
 'A medida que las exigencias a las que se ve sometido Spider-Man se intensifican, la presion desencadena una sorprendente evolucion fisica que pone en peligro su existencia. Al mismo tiempo, una nueva y extrana ola de delitos da lugar a una de las amenazas mas poderosas a las que se ha enfrentado jamas.',
 (SELECT id_clasificacion FROM Clasificacion WHERE nombre_clasificacion = '+13')),

('Obsession', 'obsession', 100, 'Espanol',
 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTL8oYJa4pjqzvxkERGJYLtul2AdvwnXalkVC_U2L56FjDV0Q-mK1g6L5A&s=10',
 'Una historia de deseo y peligro que mantiene al espectador al borde del asiento hasta el final.',
 (SELECT id_clasificacion FROM Clasificacion WHERE nombre_clasificacion = '+18')),

('Wicked', 'wicked', 100, 'Espanol',
 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcTGjfipBvjWkEMy_95O9CCddnx-AwW3maoWCTh6keeyp095J1hmaG1lQ9WghIgyRVksggO2FqgPU2hWQsY7ZGy6vBPigNaQiCB8AWGfwWQ&s=10',
 'La historia no contada de las brujas de Oz. Elphaba y Glinda forjan una amistad que cambiara el destino de Emerald City para siempre.',
 (SELECT id_clasificacion FROM Clasificacion WHERE nombre_clasificacion = '+13')),

('Crepusculo', 'crepusculo', 100, 'Espanol',
 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRo7XU-nmkn69WU5xEW7bVpUhB7Jc5ucbRupg_PpE2HZg&s=10',
 'Bella Swan se muda a Forks y conoce a Edward Cullen, un misterioso vampiro. Una historia de amor prohibido que desafia la naturaleza.',
 (SELECT id_clasificacion FROM Clasificacion WHERE nombre_clasificacion = '+13')),

('Chainsaw Man: Reze Arc', 'chainsawman', 100, 'Espanol',
 'https://upload.wikimedia.org/wikipedia/en/9/95/Chainsaw_Man_Reze_Arc_movie_poster.jpg',
 'Denji conoce a Reze, una chica misteriosa que esconde un oscuro secreto. Una historia de accion, traicion y diablos en Tokio.',
 (SELECT id_clasificacion FROM Clasificacion WHERE nombre_clasificacion = '+16')),

('Spider-Man: Across the Spider-Verse', 'spiderverse', 100, 'Espanol',
 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcQr--RgPLZ2CO9L5-qvjhHIln8ZFUeOUNqKP5CdWgTq4g&s=10',
 'Miles Morales viaja a traves del Multiverso y se une a un equipo de Spider-People para enfrentar una nueva amenaza.',
 (SELECT id_clasificacion FROM Clasificacion WHERE nombre_clasificacion = '+10'));

-- 6) Generos de cada pelicula (N:M)
INSERT INTO Pelicula_Genero (id_pelicula, id_genero)
SELECT p.id_pelicula, g.id_genero FROM Pelicula p JOIN Genero g
  ON (p.slug = 'spiderman'   AND g.nombre_genero IN ('Accion','Fantasia'))
  OR (p.slug = 'obsession'   AND g.nombre_genero IN ('Thriller'))
  OR (p.slug = 'wicked'      AND g.nombre_genero IN ('Musical','Fantasia'))
  OR (p.slug = 'crepusculo'  AND g.nombre_genero IN ('Romance','Fantasia'))
  OR (p.slug = 'chainsawman' AND g.nombre_genero IN ('Anime','Accion'))
  OR (p.slug = 'spiderverse' AND g.nombre_genero IN ('Animacion','Accion'));

-- 7) Verificacion rapida
SELECT p.id_pelicula, p.slug, p.titulo, c.nombre_clasificacion,
       GROUP_CONCAT(g.nombre_genero SEPARATOR ', ') AS generos
FROM Pelicula p
JOIN Clasificacion c ON c.id_clasificacion = p.id_clasificacion
LEFT JOIN Pelicula_Genero pg ON pg.id_pelicula = p.id_pelicula
LEFT JOIN Genero g ON g.id_genero = pg.id_genero
GROUP BY p.id_pelicula, p.slug, p.titulo, c.nombre_clasificacion
ORDER BY p.id_pelicula;