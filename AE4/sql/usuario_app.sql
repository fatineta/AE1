-- H8: usuario dedicado para la aplicacion, con permisos solo sobre CINE_DB (opcional)
-- Reemplazar CAMBIAR_CLAVE por una clave propia y cargarla en includes/config.local.php
CREATE USER IF NOT EXISTS 'cinema_app'@'localhost' IDENTIFIED BY 'CAMBIAR_CLAVE';
GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON CINE_DB.* TO 'cinema_app'@'localhost';
FLUSH PRIVILEGES;
