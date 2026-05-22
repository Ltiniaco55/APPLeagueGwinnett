INSERT INTO ligas
(id_liga, nombre_liga, temporada, categoria, descripcion, escudo, escudo_bloqueado, estado_liga, formato_liga)
VALUES
(1, 'Liga Primavera Academy', '2025-2026', 'U10', 'Liga por jornadas', NULL, 0, 'EN_CURSO', 'JORNADAS'),
(2, 'Atlanta Cup Knockout', '2025-2026', 'U12', 'Formato eliminatoria', NULL, 0, 'EN_CURSO', 'ELIMINATORIA'),
(3, 'Gwinnett Friendly Series', '2025-2026', 'U14', 'Partidos amistosos', NULL, 0, 'EN_CURSO', 'AMISTOSO'),
(4, 'Summer Development League', '2026-2027', 'U16', 'Próxima temporada', NULL, 0, 'PROXIMAMENTE', 'JORNADAS');

INSERT INTO equipos
(id_equipo, club, categoria, descripcion, escudo, escudo_bloqueado)
VALUES
(1, 'Phoenix Juniors', 'U10', 'Equipo U10 Phoenix Juniors', NULL, 0),
(2, 'Blue Sharks', 'U10', 'Equipo U10 Blue Sharks', NULL, 0),
(3, 'Red Falcons', 'U10', 'Equipo U10 Red Falcons', NULL, 0),
(4, 'Golden Bears', 'U10', 'Equipo U10 Golden Bears', NULL, 0),
(5, 'Metro Lions', 'U10', 'Equipo U10 Metro Lions', NULL, 0),

(6, 'Phoenix Juniors', 'U12', 'Equipo U12 Phoenix Juniors', NULL, 0),
(7, 'Blue Sharks', 'U12', 'Equipo U12 Blue Sharks', NULL, 0),
(8, 'Red Falcons', 'U12', 'Equipo U12 Red Falcons', NULL, 0),
(9, 'Golden Bears', 'U12', 'Equipo U12 Golden Bears', NULL, 0),
(10, 'Metro Lions', 'U12', 'Equipo U12 Metro Lions', NULL, 0),

(11, 'Phoenix Juniors', 'U14', 'Equipo U14 Phoenix Juniors', NULL, 0),
(12, 'Blue Sharks', 'U14', 'Equipo U14 Blue Sharks', NULL, 0),
(13, 'Red Falcons', 'U14', 'Equipo U14 Red Falcons', NULL, 0),
(14, 'Golden Bears', 'U14', 'Equipo U14 Golden Bears', NULL, 0),
(15, 'Metro Lions', 'U14', 'Equipo U14 Metro Lions', NULL, 0),

(16, 'Phoenix Juniors', 'U16', 'Equipo U16 Phoenix Juniors', NULL, 0),
(17, 'Blue Sharks', 'U16', 'Equipo U16 Blue Sharks', NULL, 0),
(18, 'Red Falcons', 'U16', 'Equipo U16 Red Falcons', NULL, 0),
(19, 'Golden Bears', 'U16', 'Equipo U16 Golden Bears', NULL, 0),
(20, 'Metro Lions', 'U16', 'Equipo U16 Metro Lions', NULL, 0);

INSERT INTO equipo_liga (id_equipo, id_liga)
VALUES
(1,1),(2,1),(3,1),(4,1),(5,1),
(6,2),(7,2),(8,2),(9,2),(10,2),
(11,3),(12,3),(13,3),(14,3),(15,3),
(16,4),(17,4),(18,4),(19,4),(20,4);

INSERT INTO usuario
(id_usuario, nombre, apellido, fecha_nacimiento, email, pwd, telefono, rol, email_verificado)
VALUES
(1, 'Admin', 'Demo', '2000-01-01', 'admin@gysl.local', '$2y$10$6tclGt1pHUAM4PSPbWYJC.0pS7Agx73.W8SQlYFd.632z1/EfkcSO', '600000000', 'ADMIN', 1),
(2, 'Usuario', 'Demo', '2000-01-01', 'usuario@gysl.local', '$2y$10$6tclGt1pHUAM4PSPbWYJC.0pS7Agx73.W8SQlYFd.632z1/EfkcSO', '600000000', 'USUARIO', 1);

DELIMITER //

CREATE PROCEDURE insertar_demo_gysl()
BEGIN
    DECLARE equipo_id INT DEFAULT 1;
    DECLARE liga_id INT;
    DECLARE staff_num INT DEFAULT 1;
    DECLARE jugador_num INT DEFAULT 1;
    DECLARE jugador_id INT DEFAULT 1;
    DECLARE staff_user_id INT;
    DECLARE entrenador_id INT;
    DECLARE jugador_por_equipo INT;
    DECLARE equipo_nombre VARCHAR(120);
    DECLARE categoria_equipo VARCHAR(50);

    WHILE equipo_id <= 20 DO

        SET liga_id = CEIL(equipo_id / 5);
        SET equipo_nombre = (SELECT club FROM equipos WHERE id_equipo = equipo_id);
        SET categoria_equipo = (SELECT categoria FROM equipos WHERE id_equipo = equipo_id);

        SET staff_user_id = staff_num + 2;

        INSERT INTO usuario
        (id_usuario, nombre, apellido, fecha_nacimiento, email, pwd, telefono, rol, email_verificado)
        VALUES
        (staff_user_id, CONCAT('Staff', staff_num), CONCAT('Equipo', equipo_id), '2000-01-01',
         CONCAT('staff', staff_num, '@gysl.local'),
         '$2y$10$6tclGt1pHUAM4PSPbWYJC.0pS7Agx73.W8SQlYFd.632z1/EfkcSO',
         '600000000', 'STAFF', 1);

        INSERT INTO entrenadores
        (id_entrenador, id_usuario, nombre, apellido, fecha_nacimiento, telefono, email, foto)
        VALUES
        (staff_num, staff_user_id, CONCAT('Staff', staff_num), CONCAT('Equipo', equipo_id),
         '2000-01-01', '600000000', CONCAT('staff', staff_num, '@gysl.local'), NULL);

        INSERT INTO entrenador_equipo
        (id_entrenador, id_equipo, id_liga, estado)
        VALUES
        (staff_num, equipo_id, liga_id, 'ACTIVO');

        SET staff_num = staff_num + 1;
        SET staff_user_id = staff_num + 2;

        INSERT INTO usuario
        (id_usuario, nombre, apellido, fecha_nacimiento, email, pwd, telefono, rol, email_verificado)
        VALUES
        (staff_user_id, CONCAT('Staff', staff_num), CONCAT('Equipo', equipo_id), '2000-01-01',
         CONCAT('staff', staff_num, '@gysl.local'),
         '$2y$10$6tclGt1pHUAM4PSPbWYJC.0pS7Agx73.W8SQlYFd.632z1/EfkcSO',
         '600000000', 'STAFF', 1);

        INSERT INTO entrenadores
        (id_entrenador, id_usuario, nombre, apellido, fecha_nacimiento, telefono, email, foto)
        VALUES
        (staff_num, staff_user_id, CONCAT('Staff', staff_num), CONCAT('Equipo', equipo_id),
         '2000-01-01', '600000000', CONCAT('staff', staff_num, '@gysl.local'), NULL);

        INSERT INTO entrenador_equipo
        (id_entrenador, id_equipo, id_liga, estado)
        VALUES
        (staff_num, equipo_id, liga_id, 'ACTIVO');

        SET jugador_por_equipo = 1;

        WHILE jugador_por_equipo <= 10 DO

            INSERT INTO jugador
            (id_jugador, nombre, apellido, fecha_nacimiento, foto_path, documento_identidad_path, id_usuario, nombres_padres, email_padres, telefono_padres)
            VALUES
            (
                jugador_id,
                CONCAT('Jugador', jugador_num),
                CONCAT('Demo', equipo_id),
                DATE_ADD('2012-01-01', INTERVAL jugador_num DAY),
                NULL,
                NULL,
                NULL,
                NULL,
                NULL,
                NULL
            );

            IF jugador_por_equipo <= 8 THEN
                INSERT INTO equipo_jugador
                (id_jugador, id_equipo, id_liga, dorsal, estado, accion_solicitada, id_usuario_solicitante, fecha_solicitud, id_admin_resolutor, fecha_resolucion)
                VALUES
                (jugador_id, equipo_id, liga_id, jugador_por_equipo, 'ALTA', NULL, NULL, NULL, NULL, NULL);
            ELSEIF jugador_por_equipo = 9 THEN
                INSERT INTO equipo_jugador
                (id_jugador, id_equipo, id_liga, dorsal, estado, accion_solicitada, id_usuario_solicitante, fecha_solicitud, id_admin_resolutor, fecha_resolucion)
                VALUES
                (jugador_id, equipo_id, liga_id, NULL, 'PENDIENTE', 'ALTA', staff_user_id, NOW(), NULL, NULL);
            ELSE
                INSERT INTO equipo_jugador
                (id_jugador, id_equipo, id_liga, dorsal, estado, accion_solicitada, id_usuario_solicitante, fecha_solicitud, id_admin_resolutor, fecha_resolucion)
                VALUES
                (jugador_id, equipo_id, liga_id, NULL, 'PENDIENTE', 'BAJA', staff_user_id, NOW(), NULL, NULL);
            END IF;

            SET jugador_id = jugador_id + 1;
            SET jugador_num = jugador_num + 1;
            SET jugador_por_equipo = jugador_por_equipo + 1;

        END WHILE;

        SET staff_num = staff_num + 1;
        SET equipo_id = equipo_id + 1;

    END WHILE;
END//

DELIMITER ;

CALL insertar_demo_gysl();

DROP PROCEDURE insertar_demo_gysl;

INSERT INTO clasificacion
(id_liga, id_equipo, PJ, PG, PE, PP, GF, GC, DG, PTS)
VALUES
(1,1,1,1,0,0,3,1,2,3),
(1,2,1,0,0,1,1,3,-2,0),
(1,3,0,0,0,0,0,0,0,0),
(1,4,0,0,0,0,0,0,0,0),
(1,5,0,0,0,0,0,0,0,0);

INSERT INTO partidos
(id_partido, id_liga, tipo_ronda, fecha, lugar, arbitro, id_equipo_local, id_equipo_visitante, goles_local, goles_visitante, estado)
VALUES
(1, 1, 'Jornada 1', '2026-05-30 17:00:00', 'Gwinnett Soccer Field', 'Árbitro Demo', 1, 2, 3, 1, 'jugado'),
(2, 1, 'Jornada 2', '2026-06-06 18:00:00', 'Gwinnett Soccer Field', 'Árbitro Demo', 3, 4, NULL, NULL, 'programado'),
(3, 1, 'Jornada 3', '2026-06-13 18:00:00', 'Gwinnett Soccer Field', 'Árbitro Demo', 5, 1, NULL, NULL, 'programado'),

(4, 2, 'Semifinal', '2026-06-20 19:00:00', 'Gwinnett Soccer Field', 'Árbitro Demo', 6, 7, NULL, NULL, 'programado'),
(5, 2, 'Semifinal', '2026-06-21 19:00:00', 'Gwinnett Soccer Field', 'Árbitro Demo', 8, 9, NULL, NULL, 'programado'),
(6, 2, 'Final', '2026-06-28 20:00:00', 'Gwinnett Soccer Field', 'Árbitro Demo', 6, 10, NULL, NULL, 'programado'),

(7, 3, 'Amistoso', '2026-07-05 18:30:00', 'Gwinnett Soccer Field', 'Árbitro Demo', 11, 12, NULL, NULL, 'programado'),
(8, 3, 'Amistoso', '2026-07-12 18:30:00', 'Gwinnett Soccer Field', 'Árbitro Demo', 13, 14, NULL, NULL, 'programado'),

(9, 4, 'Jornada 1', '2026-08-10 18:00:00', 'Gwinnett Soccer Field', 'Árbitro Demo', 16, 17, NULL, NULL, 'programado');

INSERT INTO usuario_equipo_favorito
(id_usuario, id_equipo)
VALUES
(2, 1);