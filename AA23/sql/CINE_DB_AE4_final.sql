SET NAMES utf8mb4;
DROP DATABASE IF EXISTS CINE_DB;
CREATE DATABASE CINE_DB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE CINE_DB;

-- =====================================================================
-- 1. DDL
-- =====================================================================

-- 1.1 Clasificacion
CREATE TABLE Clasificacion (
  id_clasificacion          INT AUTO_INCREMENT,
  nombre_clasificacion      VARCHAR(20)  NOT NULL,
  descripcion_clasificacion VARCHAR(200) NULL,
  CONSTRAINT pk_clasificacion PRIMARY KEY (id_clasificacion),
  CONSTRAINT uq_clasificacion_nombre UNIQUE (nombre_clasificacion)
) ENGINE=InnoDB;

-- 1.2 Genero (atributo multivaluado de Pelicula, resuelto en 1FN)
CREATE TABLE Genero (
  id_genero     INT AUTO_INCREMENT,
  nombre_genero VARCHAR(50) NOT NULL,
  CONSTRAINT pk_genero PRIMARY KEY (id_genero),
  CONSTRAINT uq_genero_nombre UNIQUE (nombre_genero)
) ENGINE=InnoDB;

-- 1.3 Pelicula
CREATE TABLE Pelicula (
  id_pelicula      INT AUTO_INCREMENT,
  titulo           VARCHAR(150) NOT NULL,
  duracion         INT          NOT NULL COMMENT 'minutos',
  idioma           VARCHAR(30)  NULL,
  imagen           VARCHAR(255) NULL,
  descripcion      TEXT         NULL,
  id_clasificacion INT          NOT NULL,
  CONSTRAINT pk_pelicula PRIMARY KEY (id_pelicula),
  CONSTRAINT chk_pelicula_duracion CHECK (duracion > 0),
  CONSTRAINT fk_pelicula_clasificacion FOREIGN KEY (id_clasificacion)
    REFERENCES Clasificacion (id_clasificacion)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 1.4 Pelicula_Genero (N:M)
CREATE TABLE Pelicula_Genero (
  id_pelicula INT NOT NULL,
  id_genero   INT NOT NULL,
  CONSTRAINT pk_pelicula_genero PRIMARY KEY (id_pelicula, id_genero),
  CONSTRAINT fk_pg_pelicula FOREIGN KEY (id_pelicula) REFERENCES Pelicula (id_pelicula)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_pg_genero FOREIGN KEY (id_genero) REFERENCES Genero (id_genero)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 1.5 Sala (Total_asiento es derivado: vw_sala_totales)
CREATE TABLE Sala (
  id_sala     INT AUTO_INCREMENT,
  nombre_sala VARCHAR(50) NOT NULL,
  CONSTRAINT pk_sala PRIMARY KEY (id_sala),
  CONSTRAINT uq_sala_nombre UNIQUE (nombre_sala)
) ENGINE=InnoDB;

-- 1.6 Asiento (butaca fisica)
CREATE TABLE Asiento (
  id_asiento     INT AUTO_INCREMENT,
  numero_asiento VARCHAR(10) NOT NULL,
  id_sala        INT         NOT NULL,
  CONSTRAINT pk_asiento PRIMARY KEY (id_asiento),
  CONSTRAINT uq_asiento_sala_numero UNIQUE (id_sala, numero_asiento),
  CONSTRAINT fk_asiento_sala FOREIGN KEY (id_sala) REFERENCES Sala (id_sala)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 1.7 Itinerario (funcion). Hora_final es DERIVADA: vw_itinerario_horario / fn_hora_final
CREATE TABLE Itinerario (
  id_itinerario  INT AUTO_INCREMENT,
  fecha          DATE          NOT NULL,
  horario_inicio TIME          NOT NULL,
  precio         DECIMAL(10,2) NOT NULL,
  subtitulada    BOOLEAN       NOT NULL DEFAULT FALSE,
  id_pelicula    INT           NOT NULL,
  id_sala        INT           NOT NULL,
  CONSTRAINT pk_itinerario PRIMARY KEY (id_itinerario),
  CONSTRAINT uq_itinerario_sala_horario UNIQUE (fecha, horario_inicio, id_sala),
  CONSTRAINT chk_itinerario_precio CHECK (precio >= 0),
  CONSTRAINT fk_itinerario_pelicula FOREIGN KEY (id_pelicula) REFERENCES Pelicula (id_pelicula)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_itinerario_sala FOREIGN KEY (id_sala) REFERENCES Sala (id_sala)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 1.8 Asiento_Itinerario (N:M Asiento-Itinerario con atributos propios)
--     precio_asiento = precio de LISTA de la butaca en esa funcion
CREATE TABLE Asiento_Itinerario (
  id_asiento_itinerario INT AUTO_INCREMENT,
  id_asiento            INT           NOT NULL,
  id_itinerario         INT           NOT NULL,
  estado                VARCHAR(20)   NOT NULL DEFAULT 'disponible',
  precio_asiento        DECIMAL(10,2) NULL,
  CONSTRAINT pk_asiento_itinerario PRIMARY KEY (id_asiento_itinerario),
  CONSTRAINT uq_asiento_itinerario UNIQUE (id_asiento, id_itinerario),
  CONSTRAINT chk_ai_estado CHECK (estado IN ('disponible','reservado','ocupado')),
  CONSTRAINT chk_ai_precio CHECK (precio_asiento IS NULL OR precio_asiento >= 0),
  CONSTRAINT fk_ai_asiento FOREIGN KEY (id_asiento) REFERENCES Asiento (id_asiento)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_ai_itinerario FOREIGN KEY (id_itinerario) REFERENCES Itinerario (id_itinerario)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 1.9 Cliente
CREATE TABLE Cliente (
  id_cliente       INT AUTO_INCREMENT,
  nombre           VARCHAR(100) NOT NULL,
  email            VARCHAR(120) NOT NULL,
  contrasena       VARCHAR(255) NOT NULL,
  fecha_nacimiento DATE         NULL,
  genero           VARCHAR(20)  NULL,
  rol              VARCHAR(20)  NOT NULL DEFAULT 'cliente',
  CONSTRAINT pk_cliente PRIMARY KEY (id_cliente),
  CONSTRAINT uq_cliente_email UNIQUE (email),
  CONSTRAINT chk_cliente_rol CHECK (rol IN ('cliente','administrador'))
) ENGINE=InnoDB;

-- 1.10 Ticket (medio_de_pago vive aqui: depende de id_ticket). Total_precio es DERIVADO.
CREATE TABLE Ticket (
  id_ticket        INT AUTO_INCREMENT,
  fecha_boleto     DATETIME    NOT NULL,
  situacion_boleto VARCHAR(30) NOT NULL DEFAULT 'pendiente',
  medio_de_pago    VARCHAR(30) NOT NULL,
  id_cliente       INT         NOT NULL,
  CONSTRAINT pk_ticket PRIMARY KEY (id_ticket),
  CONSTRAINT chk_ticket_situacion CHECK (situacion_boleto IN ('pendiente','confirmado','cancelado','utilizado')),
  CONSTRAINT chk_ticket_medio_pago CHECK (medio_de_pago IN ('tarjeta','qr','transferencia','efectivo')),
  CONSTRAINT fk_ticket_cliente FOREIGN KEY (id_cliente) REFERENCES Cliente (id_cliente)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 1.11 Orden_CyB (cada orden pertenece a un ticket; un ticket puede tener varias).
--      Precio_total es DERIVADO: vw_orden_cyb_totales
CREATE TABLE Orden_CyB (
  id_orden_cyb INT AUTO_INCREMENT,
  id_ticket    INT NOT NULL,
  CONSTRAINT pk_orden_cyb PRIMARY KEY (id_orden_cyb),
  CONSTRAINT fk_orden_ticket FOREIGN KEY (id_ticket) REFERENCES Ticket (id_ticket)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 1.12 Compra (una fila por butaca-funcion vendida; reemplaza Detalle_Ticket_Asiento / Compra de las AE2)
--      UNIQUE(id_asiento_itinerario): una butaca-funcion se vende como maximo una vez.
--      precio_cobrado: precio CONGELADO al momento de la venta (regla de negocio).
CREATE TABLE Compra (
  id_compra             INT AUTO_INCREMENT,
  id_ticket             INT           NOT NULL,
  id_asiento_itinerario INT           NOT NULL,
  precio_cobrado        DECIMAL(10,2) NOT NULL,
  CONSTRAINT pk_compra PRIMARY KEY (id_compra),
  CONSTRAINT uq_compra_asiento_itinerario UNIQUE (id_asiento_itinerario),
  CONSTRAINT chk_compra_precio CHECK (precio_cobrado >= 0),
  CONSTRAINT fk_compra_ticket FOREIGN KEY (id_ticket) REFERENCES Ticket (id_ticket)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT fk_compra_ai FOREIGN KEY (id_asiento_itinerario)
    REFERENCES Asiento_Itinerario (id_asiento_itinerario)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 1.13 Comida_Bebida
CREATE TABLE Comida_Bebida (
  id_comida_bebida INT AUTO_INCREMENT,
  nombre           VARCHAR(80)   NOT NULL,
  descripcion      VARCHAR(200)  NULL,
  tipo             VARCHAR(30)   NOT NULL,
  precio           DECIMAL(10,2) NOT NULL,
  CONSTRAINT pk_comida_bebida PRIMARY KEY (id_comida_bebida),
  CONSTRAINT chk_cb_tipo CHECK (tipo IN ('comida','bebida','combo')),
  CONSTRAINT chk_cb_precio CHECK (precio >= 0)
) ENGINE=InnoDB;

-- 1.14 Detalle_Orden (N:M Orden_CyB-Comida_Bebida). precio_unitario = precio historico congelado.
CREATE TABLE Detalle_Orden (
  id_detalle_orden INT AUTO_INCREMENT,
  id_orden_cyb     INT           NOT NULL,
  id_comida_bebida INT           NOT NULL,
  cantidad         INT           NOT NULL,
  precio_unitario  DECIMAL(10,2) NOT NULL,
  CONSTRAINT pk_detalle_orden PRIMARY KEY (id_detalle_orden),
  CONSTRAINT uq_detalle_orden_producto UNIQUE (id_orden_cyb, id_comida_bebida),
  CONSTRAINT chk_do_cantidad CHECK (cantidad > 0),
  CONSTRAINT chk_do_precio CHECK (precio_unitario >= 0),
  CONSTRAINT fk_do_orden FOREIGN KEY (id_orden_cyb) REFERENCES Orden_CyB (id_orden_cyb)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_do_comida FOREIGN KEY (id_comida_bebida) REFERENCES Comida_Bebida (id_comida_bebida)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;

-- 1.15 Ticket_Auditoria (la llena el trigger trg_ticket_au_auditoria; sin FK a proposito,
--      para que el historial sobreviva aunque se borre el ticket)
CREATE TABLE Ticket_Auditoria (
  id_auditoria       INT AUTO_INCREMENT,
  id_ticket          INT          NOT NULL,
  situacion_anterior VARCHAR(30)  NULL,
  situacion_nueva    VARCHAR(30)  NULL,
  usuario            VARCHAR(100) NOT NULL,
  fecha_cambio       DATETIME     NOT NULL,
  CONSTRAINT pk_ticket_auditoria PRIMARY KEY (id_auditoria)
) ENGINE=InnoDB;

-- =====================================================================
-- 2. VISTAS (atributos derivados + accesos utiles)
-- =====================================================================

-- 2.1 Sala.Total_asiento
CREATE VIEW vw_sala_totales AS
SELECT s.id_sala, s.nombre_sala, COUNT(a.id_asiento) AS total_asientos
FROM Sala s
LEFT JOIN Asiento a ON a.id_sala = s.id_sala
GROUP BY s.id_sala, s.nombre_sala;

-- 2.2 Orden_CyB.Precio_total
CREATE VIEW vw_orden_cyb_totales AS
SELECT o.id_orden_cyb, o.id_ticket,
       COALESCE(SUM(d.cantidad * d.precio_unitario), 0) AS precio_total
FROM Orden_CyB o
LEFT JOIN Detalle_Orden d ON d.id_orden_cyb = o.id_orden_cyb
GROUP BY o.id_orden_cyb, o.id_ticket;

-- 2.3 Ticket.Total_precio = entradas + dulceria (dos subconsultas independientes: sin multiplicar filas)
CREATE VIEW vw_ticket_totales AS
SELECT x.id_ticket, x.id_cliente, x.total_entradas, x.total_dulceria,
       x.total_entradas + x.total_dulceria AS total_precio
FROM (
  SELECT t.id_ticket, t.id_cliente,
         COALESCE((SELECT SUM(c.precio_cobrado) FROM Compra c WHERE c.id_ticket = t.id_ticket), 0) AS total_entradas,
         COALESCE((SELECT SUM(o.precio_total) FROM vw_orden_cyb_totales o WHERE o.id_ticket = t.id_ticket), 0) AS total_dulceria
  FROM Ticket t
) x;

-- 2.4 Itinerario.Hora_final = horario_inicio + duracion de la pelicula
CREATE VIEW vw_itinerario_horario AS
SELECT i.id_itinerario, i.fecha, i.horario_inicio,
       ADDTIME(i.horario_inicio, SEC_TO_TIME(p.duracion * 60)) AS hora_final,
       p.titulo, p.duracion, s.nombre_sala
FROM Itinerario i
JOIN Pelicula p ON p.id_pelicula = i.id_pelicula
JOIN Sala s     ON s.id_sala = i.id_sala;

-- 2.5 Ocupacion de cada funcion (necesidad del sistema: controlar el estado de las butacas)
CREATE VIEW vw_ocupacion_funcion AS
SELECT i.id_itinerario, p.titulo, s.nombre_sala, i.fecha, i.horario_inicio,
       COUNT(ai.id_asiento_itinerario)          AS total_butacas,
       COALESCE(SUM(ai.estado = 'disponible'), 0) AS disponibles,
       COALESCE(SUM(ai.estado = 'reservado'), 0)  AS reservadas,
       COALESCE(SUM(ai.estado = 'ocupado'), 0)    AS ocupadas,
       ROUND(100 * COALESCE(SUM(ai.estado <> 'disponible'), 0) / NULLIF(COUNT(ai.id_asiento_itinerario), 0), 1) AS pct_ocupacion
FROM Itinerario i
JOIN Pelicula p ON p.id_pelicula = i.id_pelicula
JOIN Sala s     ON s.id_sala = i.id_sala
LEFT JOIN Asiento_Itinerario ai ON ai.id_itinerario = i.id_itinerario
GROUP BY i.id_itinerario, p.titulo, s.nombre_sala, i.fecha, i.horario_inicio;

-- 2.6 Cartelera con clasificacion y generos
CREATE VIEW vw_cartelera AS
SELECT i.id_itinerario, p.titulo, c.nombre_clasificacion AS clasificacion,
       GROUP_CONCAT(DISTINCT g.nombre_genero ORDER BY g.nombre_genero SEPARATOR ', ') AS generos,
       s.nombre_sala, i.fecha, i.horario_inicio, i.precio,
       IF(i.subtitulada, 'Subtitulada', 'Doblada/Original') AS version
FROM Itinerario i
JOIN Pelicula p      ON p.id_pelicula = i.id_pelicula
JOIN Clasificacion c ON c.id_clasificacion = p.id_clasificacion
JOIN Sala s          ON s.id_sala = i.id_sala
LEFT JOIN Pelicula_Genero pg ON pg.id_pelicula = p.id_pelicula
LEFT JOIN Genero g           ON g.id_genero = pg.id_genero
GROUP BY i.id_itinerario, p.titulo, c.nombre_clasificacion, s.nombre_sala,
         i.fecha, i.horario_inicio, i.precio, i.subtitulada;

-- =====================================================================
-- 3. FUNCIONES
-- =====================================================================
DELIMITER $$

-- 3.1 Total a pagar de un ticket (NULL si el ticket no existe)
CREATE FUNCTION fn_total_ticket(p_id_ticket INT) RETURNS DECIMAL(12,2)
READS SQL DATA
BEGIN
  DECLARE v_total DECIMAL(12,2) DEFAULT NULL;
  SELECT total_precio INTO v_total FROM vw_ticket_totales WHERE id_ticket = p_id_ticket;
  RETURN v_total;
END$$

-- 3.2 Hora de finalizacion de una funcion
CREATE FUNCTION fn_hora_final(p_id_itinerario INT) RETURNS TIME
READS SQL DATA
BEGIN
  DECLARE v_fin TIME DEFAULT NULL;
  SELECT ADDTIME(i.horario_inicio, SEC_TO_TIME(p.duracion * 60)) INTO v_fin
  FROM Itinerario i JOIN Pelicula p ON p.id_pelicula = i.id_pelicula
  WHERE i.id_itinerario = p_id_itinerario;
  RETURN v_fin;
END$$

-- 3.3 Cantidad de butacas disponibles de una funcion
CREATE FUNCTION fn_butacas_disponibles(p_id_itinerario INT) RETURNS INT
READS SQL DATA
BEGIN
  DECLARE v_cant INT DEFAULT 0;
  SELECT COUNT(*) INTO v_cant
  FROM Asiento_Itinerario
  WHERE id_itinerario = p_id_itinerario AND estado = 'disponible';
  RETURN v_cant;
END$$

-- =====================================================================
-- 4. PROCEDIMIENTOS ALMACENADOS
-- =====================================================================

-- 4.1 Valida que una funcion no se superponga con otra de la misma sala y fecha.
--     Resuelve la limitacion documentada en ambas AE2 (el UNIQUE solo detecta igual hora de inicio).
CREATE PROCEDURE sp_validar_itinerario(
  IN p_id_itinerario INT, IN p_fecha DATE, IN p_inicio TIME,
  IN p_id_pelicula INT,   IN p_id_sala INT)
BEGIN
  DECLARE v_dur INT DEFAULT NULL;
  DECLARE v_fin TIME;
  DECLARE v_conflictos INT DEFAULT 0;

  SELECT duracion INTO v_dur FROM Pelicula WHERE id_pelicula = p_id_pelicula;
  IF v_dur IS NOT NULL THEN
    SET v_fin = ADDTIME(p_inicio, SEC_TO_TIME(v_dur * 60));
    SELECT COUNT(*) INTO v_conflictos
    FROM Itinerario i
    JOIN Pelicula p ON p.id_pelicula = i.id_pelicula
    WHERE i.id_sala = p_id_sala
      AND i.fecha = p_fecha
      AND i.id_itinerario <> p_id_itinerario
      AND p_inicio < ADDTIME(i.horario_inicio, SEC_TO_TIME(p.duracion * 60))
      AND i.horario_inicio < v_fin;
    IF v_conflictos > 0 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'La funcion se superpone con otra programada en la misma sala y fecha';
    END IF;
  END IF;
END$$

-- 4.2 Vende una butaca a un ticket (parametros IN/OUT, controles y transaccion)
CREATE PROCEDURE sp_vender_asiento(
  IN  p_id_ticket       INT,
  IN  p_id_itinerario   INT,
  IN  p_numero_asiento  VARCHAR(10),
  OUT p_precio_cobrado  DECIMAL(10,2))
BEGIN
  DECLARE v_situacion VARCHAR(30)  DEFAULT NULL;
  DECLARE v_id_ai     INT          DEFAULT NULL;
  DECLARE v_estado    VARCHAR(20)  DEFAULT NULL;
  DECLARE v_precio    DECIMAL(10,2) DEFAULT NULL;

  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  SET p_precio_cobrado = NULL;

  -- Controles
  SELECT situacion_boleto INTO v_situacion FROM Ticket WHERE id_ticket = p_id_ticket;
  IF v_situacion IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El ticket indicado no existe';
  END IF;
  IF v_situacion IN ('cancelado','utilizado') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se pueden agregar butacas a un ticket cancelado o utilizado';
  END IF;

  SELECT ai.id_asiento_itinerario, ai.estado, COALESCE(ai.precio_asiento, i.precio)
    INTO v_id_ai, v_estado, v_precio
  FROM Asiento_Itinerario ai
  JOIN Asiento a    ON a.id_asiento = ai.id_asiento
  JOIN Itinerario i ON i.id_itinerario = ai.id_itinerario
  WHERE ai.id_itinerario = p_id_itinerario AND a.numero_asiento = p_numero_asiento;

  IF v_id_ai IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La butaca no existe en la sala de esa funcion';
  END IF;
  IF v_estado <> 'disponible' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La butaca ya no esta disponible para esa funcion';
  END IF;

  -- Operacion atomica: registrar la venta y marcar la butaca
  START TRANSACTION;
    INSERT INTO Compra (id_ticket, id_asiento_itinerario, precio_cobrado)
    VALUES (p_id_ticket, v_id_ai, v_precio);
    UPDATE Asiento_Itinerario
       SET estado = IF(v_situacion = 'confirmado', 'ocupado', 'reservado')
     WHERE id_asiento_itinerario = v_id_ai;
  COMMIT;

  SET p_precio_cobrado = v_precio;
END$$

-- 4.3 Cancela un ticket: libera butacas, elimina sus Compra y marca el ticket (transaccion).
--     Resuelve la regla "cancelar libera la butaca" que en las AE2 dependia de quien operara la BD.
CREATE PROCEDURE sp_cancelar_ticket(IN p_id_ticket INT)
BEGIN
  DECLARE v_situacion VARCHAR(30) DEFAULT NULL;
  DECLARE v_liberadas INT DEFAULT 0;

  DECLARE EXIT HANDLER FOR SQLEXCEPTION
  BEGIN
    ROLLBACK;
    RESIGNAL;
  END;

  SELECT situacion_boleto INTO v_situacion FROM Ticket WHERE id_ticket = p_id_ticket;
  IF v_situacion IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El ticket indicado no existe';
  END IF;
  IF v_situacion = 'cancelado' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'El ticket ya estaba cancelado';
  END IF;
  IF v_situacion = 'utilizado' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Un ticket utilizado no puede cancelarse';
  END IF;

  START TRANSACTION;
    UPDATE Asiento_Itinerario ai
      JOIN Compra c ON c.id_asiento_itinerario = ai.id_asiento_itinerario
       SET ai.estado = 'disponible'
     WHERE c.id_ticket = p_id_ticket;
    SET v_liberadas = ROW_COUNT();
    DELETE FROM Compra WHERE id_ticket = p_id_ticket;
    UPDATE Ticket SET situacion_boleto = 'cancelado' WHERE id_ticket = p_id_ticket;
  COMMIT;

  SELECT p_id_ticket AS id_ticket, 'cancelado' AS situacion, v_liberadas AS butacas_liberadas;
END$$

-- =====================================================================
-- 5. TRIGGERS
-- =====================================================================

-- 5.1 / 5.2 Validacion: no superponer funciones (INSERT y UPDATE)
CREATE TRIGGER trg_itinerario_bi BEFORE INSERT ON Itinerario
FOR EACH ROW
BEGIN
  CALL sp_validar_itinerario(NEW.id_itinerario, NEW.fecha, NEW.horario_inicio, NEW.id_pelicula, NEW.id_sala);
END$$

CREATE TRIGGER trg_itinerario_bu BEFORE UPDATE ON Itinerario
FOR EACH ROW
BEGIN
  CALL sp_validar_itinerario(NEW.id_itinerario, NEW.fecha, NEW.horario_inicio, NEW.id_pelicula, NEW.id_sala);
END$$

-- 5.3 Control: no vender butacas ocupadas ni agregar butacas a tickets cancelados/utilizados
--     (red de seguridad para INSERT directos; el UNIQUE sigue como ultima barrera)
CREATE TRIGGER trg_compra_bi BEFORE INSERT ON Compra
FOR EACH ROW
BEGIN
  DECLARE v_sit VARCHAR(30) DEFAULT NULL;
  DECLARE v_est VARCHAR(20) DEFAULT NULL;

  SELECT situacion_boleto INTO v_sit FROM Ticket WHERE id_ticket = NEW.id_ticket;
  IF v_sit IN ('cancelado','utilizado') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se pueden agregar butacas a un ticket cancelado o utilizado';
  END IF;

  SELECT estado INTO v_est FROM Asiento_Itinerario WHERE id_asiento_itinerario = NEW.id_asiento_itinerario;
  IF v_est IS NOT NULL AND v_est <> 'disponible' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'La butaca no esta disponible para esa funcion';
  END IF;
END$$

-- 5.4 Auditoria + automatizacion: registra cada cambio de situacion del ticket
--     y, al confirmarse, pasa sus butacas a 'ocupado'
CREATE TRIGGER trg_ticket_au_auditoria AFTER UPDATE ON Ticket
FOR EACH ROW
BEGIN
  IF NOT (OLD.situacion_boleto <=> NEW.situacion_boleto) THEN
    INSERT INTO Ticket_Auditoria (id_ticket, situacion_anterior, situacion_nueva, usuario, fecha_cambio)
    VALUES (NEW.id_ticket, OLD.situacion_boleto, NEW.situacion_boleto, CURRENT_USER(), NOW());

    IF NEW.situacion_boleto IN ('confirmado','utilizado') THEN
      UPDATE Asiento_Itinerario ai
        JOIN Compra c ON c.id_asiento_itinerario = ai.id_asiento_itinerario
         SET ai.estado = 'ocupado'
       WHERE c.id_ticket = NEW.id_ticket;
    END IF;
  END IF;
END$$

DELIMITER ;

-- =====================================================================
-- 6. CARGA DE DATOS DE PRUEBA
-- =====================================================================

INSERT INTO Clasificacion (nombre_clasificacion, descripcion_clasificacion) VALUES
('ATP', 'Apta para todo publico'),
('+13', 'Mayores de 13 anios'),
('+16', 'Mayores de 16 anios'),
('+18', 'Solo mayores de 18 anios');

INSERT INTO Genero (nombre_genero) VALUES
('Ciencia ficcion'), ('Comedia'), ('Suspenso'), ('Animacion'), ('Terror'), ('Aventura'), ('Drama');

INSERT INTO Pelicula (titulo, duracion, idioma, imagen, descripcion, id_clasificacion) VALUES
('Aventura en el Espacio', 135, 'Espanol', 'espacio.jpg',   'Una tripulacion viaja a un planeta desconocido', 2),
('Risas al Atardecer',     120, 'Espanol', 'risas.jpg',     'Una comedia sobre un grupo de amigos de verano', 1),
('The Last Detective',     125, 'Ingles',  'detective.jpg', 'Un detective investiga su ultimo caso', 3),
('Mundo de Colores',        95, 'Espanol', 'colores.jpg',   'Una aventura animada para toda la familia', 1),
('Noche Oscura',           110, 'Ingles',  'noche.jpg',     NULL, 4),
('Camino de Regreso',      105, 'Espanol', NULL,            'Un drama sobre volver a casa', 2);

INSERT INTO Pelicula_Genero (id_pelicula, id_genero) VALUES
(1,1),(1,6),(2,2),(3,3),(4,4),(4,6),(5,5),(6,7);

INSERT INTO Sala (nombre_sala) VALUES ('Sala 1'), ('Sala 2'), ('Sala 3');

-- Butacas con CROSS JOIN (filas x numeros): Sala 1 = 2x5, Sala 2 = 2x4, Sala 3 = 1x6
INSERT INTO Asiento (numero_asiento, id_sala)
SELECT CONCAT(f.fila, n.num), 1
FROM (SELECT 'A' AS fila UNION SELECT 'B') f
CROSS JOIN (SELECT 1 AS num UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5) n;

INSERT INTO Asiento (numero_asiento, id_sala)
SELECT CONCAT(f.fila, n.num), 2
FROM (SELECT 'A' AS fila UNION SELECT 'B') f
CROSS JOIN (SELECT 1 AS num UNION SELECT 2 UNION SELECT 3 UNION SELECT 4) n;

INSERT INTO Asiento (numero_asiento, id_sala)
SELECT CONCAT('A', n.num), 3
FROM (SELECT 1 AS num UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6) n;

-- Funciones (el trigger trg_itinerario_bi valida cada fila contra superposiciones)
INSERT INTO Itinerario (fecha, horario_inicio, precio, subtitulada, id_pelicula, id_sala) VALUES
('2026-10-01', '18:00:00', 5000.00, FALSE, 1, 1),
('2026-10-01', '21:00:00', 5000.00, FALSE, 2, 1),
('2026-10-01', '18:30:00', 5500.00, TRUE,  3, 2),
('2026-10-02', '20:00:00', 5000.00, FALSE, 1, 2),
('2026-10-02', '19:00:00', 4500.00, FALSE, 4, 3),
('2026-10-02', '21:30:00', 5500.00, TRUE,  5, 3),
('2026-10-03', '16:00:00', 4500.00, FALSE, 4, 1),
('2026-10-03', '19:00:00', 5500.00, TRUE,  3, 2),
('2026-10-03', '21:00:00', 5500.00, TRUE,  5, 3);

-- Disponibilidad: cada butaca de la sala en cada funcion (fila A = 20% mas cara)
INSERT INTO Asiento_Itinerario (id_asiento, id_itinerario, estado, precio_asiento)
SELECT a.id_asiento, i.id_itinerario, 'disponible',
       ROUND(CASE WHEN a.numero_asiento LIKE 'A%' THEN i.precio * 1.2 ELSE i.precio END, 2)
FROM Itinerario i
JOIN Asiento a ON a.id_sala = i.id_sala;

-- Clientes (contrasenas guardadas como hash SHA-256 de prueba)
INSERT INTO Cliente (nombre, email, contrasena, fecha_nacimiento, genero, rol) VALUES
('Admin Cine',    'admin@cine.com',  SHA2('admin123', 256), '1985-03-10', 'otro',      'administrador'),
('Lucia Gomez',   'lucia@mail.com',  SHA2('lucia123', 256), '1998-07-22', 'femenino',  'cliente'),
('Marcos Perez',  'marcos@mail.com', SHA2('marcos123',256), '1992-11-05', 'masculino', 'cliente'),
('Sofia Ruiz',    'sofia@mail.com',  SHA2('sofia123', 256), '2001-01-30', 'femenino',  'cliente'),
('Tomas Benitez', 'tomas@mail.com',  SHA2('tomas123', 256), NULL,         NULL,        'cliente');

-- Tickets (medio_de_pago pertenece al ticket)
INSERT INTO Ticket (fecha_boleto, situacion_boleto, medio_de_pago, id_cliente) VALUES
('2026-09-28 10:00:00', 'confirmado', 'tarjeta',  2),  -- ticket 1: Lucia
('2026-09-28 11:30:00', 'pendiente',  'qr',       3),  -- ticket 2: Marcos
('2026-09-28 12:15:00', 'cancelado',  'efectivo', 4),  -- ticket 3: Sofia (sin butacas)
('2026-09-28 13:00:00', 'confirmado', 'efectivo', 4);  -- ticket 4: Sofia

-- Ventas de butacas a traves del procedimiento (controla, congela el precio y marca la butaca)
CALL sp_vender_asiento(1, 1, 'A1', @precio);
CALL sp_vender_asiento(1, 1, 'A2', @precio);
CALL sp_vender_asiento(2, 3, 'B1', @precio);
CALL sp_vender_asiento(2, 3, 'B2', @precio);
CALL sp_vender_asiento(2, 3, 'B3', @precio);
CALL sp_vender_asiento(4, 5, 'A3', @precio);
CALL sp_vender_asiento(4, 5, 'A4', @precio);

INSERT INTO Comida_Bebida (nombre, descripcion, tipo, precio) VALUES
('Pochoclo grande',  'Pochoclo salado o dulce',     'comida', 3500.00),
('Nachos con queso', 'Porcion individual',          'comida', 3000.00),
('Gaseosa 500ml',    'Cola, lima-limon o naranja',  'bebida', 2000.00),
('Agua mineral',     'Botella 500ml',               'bebida', 1500.00),
('Combo Pareja',     '2 gaseosas + pochoclo grande','combo',  6500.00),
('Combo Individual', '1 gaseosa + pochoclo chico',  'combo',  4200.00);

INSERT INTO Orden_CyB (id_ticket) VALUES (1), (2), (4);

INSERT INTO Detalle_Orden (id_orden_cyb, id_comida_bebida, cantidad, precio_unitario) VALUES
(1, 5, 1, 6500.00),   -- orden 1: Combo Pareja
(1, 2, 1, 3000.00),   --          + nachos
(2, 6, 3, 4200.00),   -- orden 2: 3 Combos Individuales
(3, 3, 2, 2000.00),   -- orden 3: 2 gaseosas
(3, 1, 1, 3500.00);   --          + pochoclo grande
-- Los totales NO se cargan: son atributos derivados (vistas / fn_total_ticket)

-- =====================================================================
-- 7. CONSULTAS
-- =====================================================================

-- 7.1 Conteo de filas por tabla
SELECT 'Clasificacion' AS tabla, COUNT(*) AS filas FROM Clasificacion UNION ALL
SELECT 'Genero', COUNT(*) FROM Genero UNION ALL
SELECT 'Pelicula', COUNT(*) FROM Pelicula UNION ALL
SELECT 'Pelicula_Genero', COUNT(*) FROM Pelicula_Genero UNION ALL
SELECT 'Sala', COUNT(*) FROM Sala UNION ALL
SELECT 'Asiento', COUNT(*) FROM Asiento UNION ALL
SELECT 'Itinerario', COUNT(*) FROM Itinerario UNION ALL
SELECT 'Asiento_Itinerario', COUNT(*) FROM Asiento_Itinerario UNION ALL
SELECT 'Cliente', COUNT(*) FROM Cliente UNION ALL
SELECT 'Ticket', COUNT(*) FROM Ticket UNION ALL
SELECT 'Compra', COUNT(*) FROM Compra UNION ALL
SELECT 'Comida_Bebida', COUNT(*) FROM Comida_Bebida UNION ALL
SELECT 'Orden_CyB', COUNT(*) FROM Orden_CyB UNION ALL
SELECT 'Detalle_Orden', COUNT(*) FROM Detalle_Orden UNION ALL
SELECT 'Ticket_Auditoria', COUNT(*) FROM Ticket_Auditoria;

-- 7.2 Vistas de atributos derivados
SELECT * FROM vw_sala_totales;         -- Sala 1 = 10, Sala 2 = 8, Sala 3 = 6
SELECT * FROM vw_orden_cyb_totales;    -- 9500, 12600, 7500
SELECT * FROM vw_ticket_totales;       -- ticket 1 = 21500, 2 = 29100, 3 = 0, 4 = 18300
SELECT * FROM vw_itinerario_horario ORDER BY fecha, horario_inicio;

-- 7.3 Cartelera (vista con JOIN multiple y GROUP_CONCAT de generos)
SELECT * FROM vw_cartelera ORDER BY fecha, horario_inicio;

-- 7.4 Ocupacion por funcion (vista con agregaciones)
SELECT * FROM vw_ocupacion_funcion ORDER BY id_itinerario;

-- 7.5 Butacas libres de la funcion 1 (WHERE + ORDER BY)
SELECT a.numero_asiento, ai.precio_asiento
FROM Asiento_Itinerario ai
JOIN Asiento a ON a.id_asiento = ai.id_asiento
WHERE ai.id_itinerario = 1 AND ai.estado = 'disponible'
ORDER BY a.numero_asiento;

-- 7.6 Detalle de compras por cliente (JOIN de 6 tablas)
SELECT cl.nombre, t.id_ticket, t.situacion_boleto, p.titulo,
       a.numero_asiento, co.precio_cobrado, t.medio_de_pago
FROM Cliente cl
JOIN Ticket t               ON t.id_cliente = cl.id_cliente
JOIN Compra co              ON co.id_ticket = t.id_ticket
JOIN Asiento_Itinerario ai  ON ai.id_asiento_itinerario = co.id_asiento_itinerario
JOIN Asiento a              ON a.id_asiento = ai.id_asiento
JOIN Itinerario i           ON i.id_itinerario = ai.id_itinerario
JOIN Pelicula p             ON p.id_pelicula = i.id_pelicula
ORDER BY cl.nombre, t.id_ticket, a.numero_asiento;

-- 7.7 Ingresos por pelicula (solo tickets confirmados): COUNT, SUM, AVG, MIN, MAX + GROUP BY
SELECT p.titulo, COUNT(co.id_compra) AS entradas, SUM(co.precio_cobrado) AS ingresos,
       AVG(co.precio_cobrado) AS promedio, MIN(co.precio_cobrado) AS minimo, MAX(co.precio_cobrado) AS maximo
FROM Compra co
JOIN Ticket t              ON t.id_ticket = co.id_ticket AND t.situacion_boleto = 'confirmado'
JOIN Asiento_Itinerario ai ON ai.id_asiento_itinerario = co.id_asiento_itinerario
JOIN Itinerario i          ON i.id_itinerario = ai.id_itinerario
JOIN Pelicula p            ON p.id_pelicula = i.id_pelicula
GROUP BY p.id_pelicula, p.titulo
ORDER BY ingresos DESC;

-- 7.8 HAVING: clientes con gasto total (tickets no cancelados) mayor a $20.000
SELECT cl.nombre, COUNT(t.id_ticket) AS tickets, SUM(vt.total_precio) AS gasto_total
FROM Cliente cl
JOIN Ticket t              ON t.id_cliente = cl.id_cliente AND t.situacion_boleto <> 'cancelado'
JOIN vw_ticket_totales vt  ON vt.id_ticket = t.id_ticket
GROUP BY cl.id_cliente, cl.nombre
HAVING SUM(vt.total_precio) > 20000
ORDER BY gasto_total DESC;                        -- Marcos 29100, Lucia 21500

-- 7.9 HAVING: funciones con mas de una butaca vendida o reservada
SELECT i.id_itinerario, p.titulo, COUNT(co.id_compra) AS butacas_vendidas
FROM Itinerario i
JOIN Pelicula p            ON p.id_pelicula = i.id_pelicula
JOIN Asiento_Itinerario ai ON ai.id_itinerario = i.id_itinerario
JOIN Compra co             ON co.id_asiento_itinerario = ai.id_asiento_itinerario
GROUP BY i.id_itinerario, p.titulo
HAVING COUNT(co.id_compra) > 1;

-- 7.10 Productos mas vendidos
SELECT cb.nombre, cb.tipo, SUM(d.cantidad) AS unidades
FROM Detalle_Orden d
JOIN Comida_Bebida cb ON cb.id_comida_bebida = d.id_comida_bebida
GROUP BY cb.id_comida_bebida, cb.nombre, cb.tipo
ORDER BY unidades DESC;

-- 7.11 NULL con LEFT JOIN: productos que nunca se vendieron
SELECT cb.nombre
FROM Comida_Bebida cb
LEFT JOIN Detalle_Orden d ON d.id_comida_bebida = cb.id_comida_bebida
WHERE d.id_detalle_orden IS NULL;                 -- Agua mineral

-- 7.12 Subconsulta (NOT IN): peliculas sin ninguna funcion programada
SELECT titulo FROM Pelicula
WHERE id_pelicula NOT IN (SELECT id_pelicula FROM Itinerario);   -- Camino de Regreso

-- 7.13 Subconsulta escalar: tickets con total mayor al promedio de los tickets con consumo
SELECT id_ticket, total_precio
FROM vw_ticket_totales
WHERE total_precio > (SELECT AVG(total_precio) FROM vw_ticket_totales WHERE total_precio > 0);  -- ticket 2

-- 7.14 Subconsulta correlacionada: funciones con precio superior al promedio de su sala
SELECT i.id_itinerario, i.id_sala, i.precio
FROM Itinerario i
WHERE i.precio > (SELECT AVG(i2.precio) FROM Itinerario i2 WHERE i2.id_sala = i.id_sala)
ORDER BY i.id_sala, i.id_itinerario;

-- 7.15 NOT EXISTS: tickets sin orden de comida y bebida
SELECT t.id_ticket, t.situacion_boleto
FROM Ticket t
WHERE NOT EXISTS (SELECT 1 FROM Orden_CyB o WHERE o.id_ticket = t.id_ticket);   -- ticket 3

-- 7.16 Tratamiento de NULL: COALESCE, IS NULL y agregaciones que ignoran NULL
SELECT titulo, COALESCE(descripcion, '(sin descripcion)') AS descripcion,
       COALESCE(imagen, '(sin imagen)') AS imagen
FROM Pelicula
WHERE descripcion IS NULL OR imagen IS NULL;

SELECT COUNT(*) AS total_clientes,
       COUNT(fecha_nacimiento) AS con_fecha_nacimiento,     -- ignora NULL
       AVG(TIMESTAMPDIFF(YEAR, fecha_nacimiento, CURDATE())) AS edad_promedio  -- ignora NULL
FROM Cliente;

-- 7.17 Clientes que nunca compraron (LEFT JOIN + IS NULL)
SELECT cl.nombre, cl.email, COUNT(co.id_compra) AS butacas_compradas
FROM Cliente cl
LEFT JOIN Ticket t  ON t.id_cliente = cl.id_cliente
LEFT JOIN Compra co ON co.id_ticket = t.id_ticket
GROUP BY cl.id_cliente, cl.nombre, cl.email
HAVING COUNT(co.id_compra) = 0;                           -- Admin Cine, Tomas Benitez

-- 7.18 Ingresos por medio de pago (tickets no cancelados)
SELECT t.medio_de_pago, COUNT(*) AS tickets, SUM(vt.total_precio) AS total,
       AVG(vt.total_precio) AS promedio
FROM Ticket t
JOIN vw_ticket_totales vt ON vt.id_ticket = t.id_ticket
WHERE t.situacion_boleto <> 'cancelado'
GROUP BY t.medio_de_pago;

-- 7.19 Generos con mas peliculas en cartelera (N:M + GROUP BY + HAVING)
SELECT g.nombre_genero, COUNT(DISTINCT pg.id_pelicula) AS peliculas, COUNT(i.id_itinerario) AS funciones
FROM Genero g
JOIN Pelicula_Genero pg ON pg.id_genero = g.id_genero
LEFT JOIN Itinerario i  ON i.id_pelicula = pg.id_pelicula
GROUP BY g.id_genero, g.nombre_genero
HAVING COUNT(i.id_itinerario) > 0
ORDER BY funciones DESC;

-- =====================================================================
-- 8. ACTUALIZACION Y ELIMINACION DE DATOS (DML)
-- =====================================================================
SET SQL_SAFE_UPDATES = 0;

-- 8.1 INSERT / UPDATE / DELETE de un cliente temporal
INSERT INTO Cliente (nombre, email, contrasena, rol)
VALUES ('Cliente Temporal', 'temporal@mail.com', SHA2('temporal', 256), 'cliente');
UPDATE Cliente SET nombre = 'Cliente Temporal Editado', genero = 'otro' WHERE email = 'temporal@mail.com';
SELECT id_cliente, nombre, genero FROM Cliente WHERE email = 'temporal@mail.com';
DELETE FROM Cliente WHERE email = 'temporal@mail.com';

-- 8.2 Pelicula temporal con generos: el DELETE arrastra Pelicula_Genero por ON DELETE CASCADE
INSERT INTO Pelicula (titulo, duracion, idioma, id_clasificacion) VALUES ('Estreno Pendiente', 90, 'Espanol', 1);
INSERT INTO Pelicula_Genero (id_pelicula, id_genero)
SELECT p.id_pelicula, g.id_genero FROM Pelicula p JOIN Genero g ON g.nombre_genero IN ('Comedia','Drama')
WHERE p.titulo = 'Estreno Pendiente';
SELECT COUNT(*) AS generos_asignados FROM Pelicula_Genero pg JOIN Pelicula p ON p.id_pelicula = pg.id_pelicula
WHERE p.titulo = 'Estreno Pendiente';                       -- 2
DELETE FROM Pelicula WHERE titulo = 'Estreno Pendiente';
SELECT COUNT(*) AS generos_tras_borrar FROM Pelicula_Genero pg
LEFT JOIN Pelicula p ON p.id_pelicula = pg.id_pelicula WHERE p.id_pelicula IS NULL;   -- 0 huerfanos

-- 8.3 UPDATE con JOIN: aumento del 10% a las funciones del 03/10 y recalculo del precio de lista
--     de las butacas AUN DISPONIBLES (las ya vendidas conservan su precio congelado en Compra)
UPDATE Itinerario SET precio = ROUND(precio * 1.10, 2) WHERE fecha = '2026-10-03';
UPDATE Asiento_Itinerario ai
  JOIN Itinerario i ON i.id_itinerario = ai.id_itinerario
  JOIN Asiento a    ON a.id_asiento = ai.id_asiento
   SET ai.precio_asiento = ROUND(CASE WHEN a.numero_asiento LIKE 'A%' THEN i.precio * 1.2 ELSE i.precio END, 2)
 WHERE i.fecha = '2026-10-03' AND ai.estado = 'disponible';
SELECT id_itinerario, precio FROM Itinerario WHERE fecha = '2026-10-03';   -- 4950, 6050, 6050

-- 8.4 Regla "precio congelado": subir el precio de lista de los combos NO altera ordenes ya vendidas
UPDATE Comida_Bebida SET precio = ROUND(precio * 1.10, 2) WHERE tipo = 'combo';
SELECT id_comida_bebida, nombre, precio AS precio_lista_nuevo FROM Comida_Bebida WHERE tipo = 'combo';   -- 7150 y 4620
SELECT * FROM vw_orden_cyb_totales;                         -- siguen 9500, 12600, 7500

SET SQL_SAFE_UPDATES = 1;

-- ---------------------------------------------------------------------
-- 9.2 Acciones referenciales en cascada (cada bloque: antes / durante / ROLLBACK)
-- ---------------------------------------------------------------------

-- C1 Al borrar una Orden_CyB se borran sus Detalle_Orden (ON DELETE CASCADE)
START TRANSACTION;
SELECT COUNT(*) AS detalles_antes FROM Detalle_Orden WHERE id_orden_cyb = 1;        -- 2
DELETE FROM Orden_CyB WHERE id_orden_cyb = 1;
SELECT COUNT(*) AS detalles_despues FROM Detalle_Orden WHERE id_orden_cyb = 1;      -- 0
ROLLBACK;
SELECT COUNT(*) AS detalles_tras_rollback FROM Detalle_Orden WHERE id_orden_cyb = 1; -- 2

-- C2 Al borrar una funcion SIN ventas se borran sus Asiento_Itinerario (CASCADE)
START TRANSACTION;
SELECT COUNT(*) AS antes FROM Asiento_Itinerario WHERE id_itinerario = 2;           -- 10
DELETE FROM Itinerario WHERE id_itinerario = 2;
SELECT COUNT(*) AS despues FROM Asiento_Itinerario WHERE id_itinerario = 2;         -- 0
ROLLBACK;
SELECT COUNT(*) AS tras_rollback FROM Asiento_Itinerario WHERE id_itinerario = 2;   -- 10

-- C3 UPDATE en cascada de la clave de Clasificacion
START TRANSACTION;
UPDATE Clasificacion SET id_clasificacion = 10 WHERE id_clasificacion = 1;
SELECT titulo, id_clasificacion FROM Pelicula WHERE titulo IN ('Risas al Atardecer','Mundo de Colores');  -- 10 y 10
ROLLBACK;
SELECT titulo, id_clasificacion FROM Pelicula WHERE titulo IN ('Risas al Atardecer','Mundo de Colores');  -- 1 y 1

-- C4 Al retirar un genero del catalogo solo se pierden los vinculos (CASCADE), no las peliculas
START TRANSACTION;
DELETE FROM Genero WHERE nombre_genero = 'Aventura';
SELECT COUNT(*) AS peliculas_intactas FROM Pelicula;                                -- 6
SELECT COUNT(*) AS vinculos_pelicula_genero FROM Pelicula_Genero;                   -- 6 (eran 8)
ROLLBACK;

-- ---------------------------------------------------------------------
-- 9.3 Transacciones explicitas (START TRANSACTION / ROLLBACK) y trigger de auditoria
-- ---------------------------------------------------------------------

-- T1 Los derivados se recalculan solos
START TRANSACTION;
SELECT id_ticket, total_precio FROM vw_ticket_totales WHERE id_ticket = 1;          -- 21500
INSERT INTO Detalle_Orden (id_orden_cyb, id_comida_bebida, cantidad, precio_unitario) VALUES (1, 4, 1, 1500.00);
SELECT id_ticket, total_precio FROM vw_ticket_totales WHERE id_ticket = 1;          -- 23000
ROLLBACK;
SELECT id_ticket, total_precio FROM vw_ticket_totales WHERE id_ticket = 1;          -- 21500

-- T2 Trigger trg_ticket_au_auditoria: confirmar el ticket 2 ocupa sus butacas y deja registro de auditoria
START TRANSACTION;
SELECT estado, COUNT(*) AS butacas FROM Asiento_Itinerario WHERE id_itinerario = 3 GROUP BY estado;   -- disponible 5, reservado 3
UPDATE Ticket SET situacion_boleto = 'confirmado' WHERE id_ticket = 2;
SELECT estado, COUNT(*) AS butacas FROM Asiento_Itinerario WHERE id_itinerario = 3 GROUP BY estado;   -- disponible 5, ocupado 3
SELECT id_ticket, situacion_anterior, situacion_nueva, usuario FROM Ticket_Auditoria;                 -- 1 fila
ROLLBACK;
SELECT COUNT(*) AS auditorias_tras_rollback FROM Ticket_Auditoria;                                    -- 0

-- ---------------------------------------------------------------------
-- 9.4 Funciones
-- ---------------------------------------------------------------------
SELECT fn_total_ticket(1) AS total_ticket_1,            -- 21500.00
       fn_total_ticket(2) AS total_ticket_2,            -- 29100.00
       fn_total_ticket(3) AS total_ticket_3_cancelado,  -- 0.00
       fn_total_ticket(999) AS ticket_inexistente;      -- NULL
SELECT id_itinerario, fn_hora_final(id_itinerario) AS hora_final,
       fn_butacas_disponibles(id_itinerario) AS butacas_disponibles
FROM Itinerario ORDER BY id_itinerario;

-- ---------------------------------------------------------------------
-- 9.5 Procedimientos con transaccion (modifican datos: se ejecutan al final)
--     Caso de negocio: cancelar el ticket 2 libera sus butacas, que se revenden al ticket 4
-- ---------------------------------------------------------------------
SELECT t.id_ticket, t.situacion_boleto, COUNT(c.id_compra) AS butacas
FROM Ticket t LEFT JOIN Compra c ON c.id_ticket = t.id_ticket
WHERE t.id_ticket IN (2, 4) GROUP BY t.id_ticket, t.situacion_boleto;          -- t2: 3 | t4: 2

CALL sp_cancelar_ticket(2);                                                    -- 3 butacas liberadas

SELECT estado, COUNT(*) AS butacas FROM Asiento_Itinerario WHERE id_itinerario = 3 GROUP BY estado;  -- disponible 8
SELECT * FROM Ticket_Auditoria;                                                -- pendiente -> cancelado

CALL sp_vender_asiento(4, 3, 'B1', @precio);                                   -- la butaca liberada se revende
SELECT @precio AS precio_cobrado;                                              -- 5500.00
SELECT t.id_ticket, t.situacion_boleto, COUNT(c.id_compra) AS butacas
FROM Ticket t LEFT JOIN Compra c ON c.id_ticket = t.id_ticket
WHERE t.id_ticket IN (2, 4) GROUP BY t.id_ticket, t.situacion_boleto;          -- t2: 0 | t4: 3
SELECT * FROM vw_ocupacion_funcion WHERE id_itinerario = 3;

-- Estado final de las vistas de totales
SELECT * FROM vw_ticket_totales ORDER BY id_ticket;
-- =====================================================================
-- FIN DEL SCRIPT
-- =====================================================================
