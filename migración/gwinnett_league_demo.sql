SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE `gwinnett_league`
DEFAULT CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE `gwinnett_league`;

CREATE TABLE `ligas` (
  `id_liga` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_liga` varchar(100) NOT NULL,
  `temporada` varchar(50) NOT NULL,
  `categoria` varchar(50) NOT NULL,
  `descripcion` varchar(50) NOT NULL,
  `escudo` varchar(255) DEFAULT NULL,
  `escudo_bloqueado` tinyint(1) DEFAULT 0,
  `estado_liga` enum('EN_CURSO','PROXIMAMENTE') NOT NULL DEFAULT 'PROXIMAMENTE',
  `formato_liga` enum('JORNADAS','ELIMINATORIA','AMISTOSO') NOT NULL DEFAULT 'JORNADAS',
  PRIMARY KEY (`id_liga`),
  UNIQUE KEY `uq_liga` (`nombre_liga`,`temporada`,`categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `equipos` (
  `id_equipo` int(11) NOT NULL AUTO_INCREMENT,
  `club` varchar(120) NOT NULL,
  `categoria` varchar(50) NOT NULL,
  `descripcion` longtext DEFAULT NULL,
  `escudo` varchar(255) DEFAULT NULL,
  `escudo_bloqueado` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id_equipo`),
  UNIQUE KEY `uq_club_categoria` (`club`,`categoria`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `usuario` (
  `id_usuario` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `email` varchar(180) NOT NULL,
  `pwd` varchar(255) DEFAULT NULL,
  `telefono` varchar(40) DEFAULT NULL,
  `rol` enum('ADMIN','ARBITRO','STAFF','USUARIO') NOT NULL DEFAULT 'USUARIO',
  `email_verificado` tinyint(1) NOT NULL DEFAULT 0,
  `email_verification_code_hash` varchar(255) DEFAULT NULL,
  `email_verification_expire` datetime DEFAULT NULL,
  `oauth_provider` varchar(30) DEFAULT NULL,
  `oauth_id` varchar(255) DEFAULT NULL,
  `reset_password_token` varchar(255) DEFAULT NULL,
  `reset_password_expire` datetime DEFAULT NULL,
  `id_equipo` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `uq_usuario_email` (`email`),
  UNIQUE KEY `uq_usuario_oauth` (`oauth_provider`,`oauth_id`),
  KEY `fk_user_equipo` (`id_equipo`),
  CONSTRAINT `fk_user_equipo` FOREIGN KEY (`id_equipo`) REFERENCES `equipos` (`id_equipo`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `entrenadores` (
  `id_entrenador` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) DEFAULT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `telefono` varchar(40) DEFAULT NULL,
  `email` varchar(180) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_entrenador`),
  KEY `user_id` (`id_usuario`),
  CONSTRAINT `user_id` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `jugador` (
  `id_jugador` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `fecha_nacimiento` date NOT NULL,
  `foto_path` varchar(255) DEFAULT NULL,
  `documento_identidad_path` varchar(255) DEFAULT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `nombres_padres` varchar(255) DEFAULT NULL,
  `email_padres` varchar(255) DEFAULT NULL,
  `telefono_padres` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`id_jugador`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `equipo_liga` (
  `id_equipo` int(11) NOT NULL,
  `id_liga` int(11) NOT NULL,
  PRIMARY KEY (`id_equipo`,`id_liga`),
  KEY `id_liga` (`id_liga`),
  CONSTRAINT `equipo_liga_ibfk_1` FOREIGN KEY (`id_equipo`) REFERENCES `equipos` (`id_equipo`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `equipo_liga_ibfk_2` FOREIGN KEY (`id_liga`) REFERENCES `ligas` (`id_liga`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `entrenador_equipo` (
  `id_entrenador` int(11) NOT NULL,
  `id_equipo` int(11) NOT NULL,
  `id_liga` int(11) NOT NULL,
  `estado` varchar(20) NOT NULL,
  PRIMARY KEY (`id_entrenador`,`id_equipo`,`id_liga`),
  KEY `id_liga` (`id_liga`),
  KEY `entrenador_equipo_ibfk_2` (`id_equipo`),
  CONSTRAINT `entrenador_equipo_ibfk_1` FOREIGN KEY (`id_entrenador`) REFERENCES `entrenadores` (`id_entrenador`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `entrenador_equipo_ibfk_2` FOREIGN KEY (`id_equipo`) REFERENCES `equipos` (`id_equipo`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `entrenador_equipo_ibfk_3` FOREIGN KEY (`id_liga`) REFERENCES `ligas` (`id_liga`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `equipo_jugador` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_jugador` int(11) NOT NULL,
  `id_equipo` int(11) NOT NULL,
  `id_liga` int(11) NOT NULL,
  `dorsal` int(11) DEFAULT NULL,
  `estado` enum('PENDIENTE','ALTA') NOT NULL DEFAULT 'ALTA',
  `accion_solicitada` enum('ALTA','BAJA') DEFAULT NULL,
  `id_usuario_solicitante` int(11) DEFAULT NULL,
  `fecha_solicitud` datetime DEFAULT NULL,
  `id_admin_resolutor` int(11) DEFAULT NULL,
  `fecha_resolucion` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_jugador_equipo_liga` (`id_jugador`,`id_equipo`,`id_liga`),
  KEY `id_liga` (`id_liga`),
  KEY `equipo_jugador_ibfk_2` (`id_equipo`),
  CONSTRAINT `equipo_jugador_ibfk_1` FOREIGN KEY (`id_jugador`) REFERENCES `jugador` (`id_jugador`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `equipo_jugador_ibfk_2` FOREIGN KEY (`id_equipo`) REFERENCES `equipos` (`id_equipo`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `equipo_jugador_ibfk_3` FOREIGN KEY (`id_liga`) REFERENCES `ligas` (`id_liga`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `clasificacion` (
  `id_clasificacion` int(11) NOT NULL AUTO_INCREMENT,
  `id_liga` int(11) NOT NULL,
  `id_equipo` int(11) NOT NULL,
  `PJ` int(11) NOT NULL DEFAULT 0,
  `PG` int(11) NOT NULL DEFAULT 0,
  `PE` int(11) NOT NULL DEFAULT 0,
  `PP` int(11) NOT NULL DEFAULT 0,
  `GF` int(11) NOT NULL DEFAULT 0,
  `GC` int(11) NOT NULL DEFAULT 0,
  `DG` int(11) NOT NULL DEFAULT 0,
  `PTS` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_clasificacion`),
  UNIQUE KEY `uq_liga_equipo` (`id_liga`,`id_equipo`),
  KEY `id_equipo` (`id_equipo`),
  CONSTRAINT `clasificacion_ibfk_1` FOREIGN KEY (`id_equipo`) REFERENCES `equipos` (`id_equipo`),
  CONSTRAINT `clasificacion_ibfk_2` FOREIGN KEY (`id_liga`) REFERENCES `ligas` (`id_liga`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `partidos` (
  `id_partido` int(11) NOT NULL AUTO_INCREMENT,
  `id_liga` int(11) NOT NULL,
  `tipo_ronda` varchar(50) NOT NULL,
  `fecha` datetime NOT NULL,
  `lugar` varchar(100) NOT NULL,
  `arbitro` varchar(100) DEFAULT NULL,
  `id_equipo_local` int(11) NOT NULL,
  `id_equipo_visitante` int(11) NOT NULL,
  `goles_local` int(11) DEFAULT NULL,
  `goles_visitante` int(11) DEFAULT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'programado',
  PRIMARY KEY (`id_partido`),
  KEY `id_liga` (`id_liga`),
  KEY `id_equipo1` (`id_equipo_local`),
  KEY `id_equipo2` (`id_equipo_visitante`),
  CONSTRAINT `partidos_ibfk_1` FOREIGN KEY (`id_liga`) REFERENCES `ligas` (`id_liga`),
  CONSTRAINT `partidos_ibfk_2` FOREIGN KEY (`id_equipo_local`) REFERENCES `equipos` (`id_equipo`),
  CONSTRAINT `partidos_ibfk_3` FOREIGN KEY (`id_equipo_visitante`) REFERENCES `equipos` (`id_equipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `usuario_equipo_favorito` (
  `id_usuario` int(11) NOT NULL,
  `id_equipo` int(11) NOT NULL,
  PRIMARY KEY (`id_usuario`,`id_equipo`),
  KEY `fk_favorito_equipo` (`id_equipo`),
  CONSTRAINT `fk_favorito_equipo` FOREIGN KEY (`id_equipo`) REFERENCES `equipos` (`id_equipo`) ON DELETE CASCADE,
  CONSTRAINT `fk_favorito_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



