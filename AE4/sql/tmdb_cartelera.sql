USE CINE_DB;

-- Migracion para la cartelera automatica con TMDb.
-- NO borra nada: solo agrega columnas a Pelicula. Se corre UNA sola vez.
ALTER TABLE Pelicula
  ADD COLUMN tmdb_id        INT        NULL UNIQUE AFTER slug,
  ADD COLUMN en_cartelera   TINYINT(1) NOT NULL DEFAULT 1 AFTER tmdb_id,
  ADD COLUMN fecha_estreno  DATE       NULL AFTER en_cartelera;
