-- Amplia todas las salas al esquema de filas A-I con asientos 1-20.
-- Es seguro volver a ejecutar: solo inserta butacas y disponibilidades faltantes.
-- No elimina ni modifica las butacas existentes o vendidas.

START TRANSACTION;

INSERT INTO Asiento (numero_asiento, id_sala)
SELECT CONCAT(f.fila, n.numero), s.id_sala
FROM Sala s
CROSS JOIN (
    SELECT 'A' AS fila UNION ALL SELECT 'B' UNION ALL SELECT 'C'
    UNION ALL SELECT 'D' UNION ALL SELECT 'E' UNION ALL SELECT 'F'
    UNION ALL SELECT 'G' UNION ALL SELECT 'H' UNION ALL SELECT 'I'
) AS f
CROSS JOIN (
    SELECT 1 AS numero UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
    UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8
    UNION ALL SELECT 9 UNION ALL SELECT 10 UNION ALL SELECT 11 UNION ALL SELECT 12
    UNION ALL SELECT 13 UNION ALL SELECT 14 UNION ALL SELECT 15 UNION ALL SELECT 16
    UNION ALL SELECT 17 UNION ALL SELECT 18 UNION ALL SELECT 19 UNION ALL SELECT 20
) AS n
WHERE NOT EXISTS (
    SELECT 1
    FROM Asiento existente
    WHERE existente.id_sala = s.id_sala
      AND existente.numero_asiento = CONCAT(f.fila, n.numero)
);

INSERT INTO Asiento_Itinerario (id_asiento, id_itinerario, estado, precio_asiento)
SELECT a.id_asiento,
       i.id_itinerario,
       'disponible',
       ROUND(CASE WHEN LEFT(a.numero_asiento, 1) = 'A'
                  THEN i.precio * 1.2
                  ELSE i.precio
             END, 2)
FROM Itinerario i
JOIN Asiento a ON a.id_sala = i.id_sala
WHERE LEFT(a.numero_asiento, 1) BETWEEN 'A' AND 'I'
  AND CAST(SUBSTRING(a.numero_asiento, 2) AS UNSIGNED) BETWEEN 1 AND 20
  AND a.numero_asiento = CONCAT(
      LEFT(a.numero_asiento, 1),
      CAST(SUBSTRING(a.numero_asiento, 2) AS UNSIGNED)
  )
  AND NOT EXISTS (
      SELECT 1
      FROM Asiento_Itinerario existente
      WHERE existente.id_asiento = a.id_asiento
        AND existente.id_itinerario = i.id_itinerario
  );

COMMIT;

-- Verifica cuantas butacas hay por fila en cada sala.
SELECT s.nombre_sala, LEFT(a.numero_asiento, 1) AS fila, COUNT(*) AS cantidad
FROM Sala s
JOIN Asiento a ON a.id_sala = s.id_sala
WHERE LEFT(a.numero_asiento, 1) BETWEEN 'A' AND 'I'
GROUP BY s.id_sala, s.nombre_sala, LEFT(a.numero_asiento, 1)
ORDER BY s.id_sala, fila;
