-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 28-04-2026 a las 21:16:54
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `gwinnett_league`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clasificacion`
--

CREATE TABLE `clasificacion` (
  `id_clasificacion` int(11) NOT NULL,
  `id_liga` int(11) NOT NULL,
  `id_equipo` int(11) NOT NULL,
  `PJ` int(11) NOT NULL DEFAULT 0,
  `PG` int(11) NOT NULL DEFAULT 0,
  `PE` int(11) NOT NULL DEFAULT 0,
  `PP` int(11) NOT NULL DEFAULT 0,
  `GF` int(11) NOT NULL DEFAULT 0,
  `GC` int(11) NOT NULL DEFAULT 0,
  `PTS` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `entrenadores`
--

CREATE TABLE `entrenadores` (
  `id_entrenador` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `fecha_nacimiento` date DEFAULT NULL,
  `telefono` varchar(40) DEFAULT NULL,
  `email` varchar(180) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `entrenadores`
--

INSERT INTO `entrenadores` (`id_entrenador`, `id_usuario`, `nombre`, `apellido`, `fecha_nacimiento`, `telefono`, `email`) VALUES
(1, 25, 'Luciano', 'Tiniaco', '2026-03-04', '0637054468', '00@gmail.com');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `entrenador_equipo`
--

CREATE TABLE `entrenador_equipo` (
  `id_entrenador` int(11) NOT NULL,
  `id_equipo` int(11) NOT NULL,
  `id_liga` int(11) NOT NULL,
  `estado` varchar(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `equipos`
--

CREATE TABLE `equipos` (
  `id_equipo` int(11) NOT NULL,
  `club` varchar(120) NOT NULL,
  `categoria` varchar(50) NOT NULL,
  `descripcion` longtext DEFAULT NULL,
  `escudo` varchar(255) DEFAULT NULL,
  `escudo_bloqueado` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `equipo_jugador`
--

CREATE TABLE `equipo_jugador` (
  `id` int(11) NOT NULL,
  `id_jugador` int(11) NOT NULL,
  `id_equipo` int(11) NOT NULL,
  `id_liga` int(11) NOT NULL,
  `dorsal` int(11) DEFAULT NULL,
  `estado` enum('PENDIENTE','ALTA') NOT NULL DEFAULT 'ALTA',
  `accion_solicitada` enum('ALTA','BAJA') DEFAULT NULL,
  `id_usuario_solicitante` int(11) DEFAULT NULL,
  `fecha_solicitud` datetime DEFAULT NULL,
  `id_admin_resolutor` int(11) DEFAULT NULL,
  `fecha_resolucion` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `equipo_liga`
--

CREATE TABLE `equipo_liga` (
  `id_equipo` int(11) NOT NULL,
  `id_liga` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `jugador`
--

CREATE TABLE `jugador` (
  `id_jugador` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `fecha_nacimiento` date NOT NULL,
  `foto_path` varchar(255) DEFAULT NULL,
  `documento_identidad_path` varchar(255) DEFAULT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `nombres_padres` varchar(255) DEFAULT NULL,
  `email_padres` varchar(255) DEFAULT NULL,
  `telefono_padres` varchar(30) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ligas`
--

CREATE TABLE `ligas` (
  `id_liga` int(11) NOT NULL,
  `nombre_liga` varchar(100) NOT NULL,
  `temporada` varchar(50) NOT NULL,
  `categoria` varchar(50) NOT NULL,
  `descripcion` varchar(50) NOT NULL,
  `escudo` varchar(255) DEFAULT NULL,
  `escudo_bloqueado` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `partidos`
--

CREATE TABLE `partidos` (
  `id_partido` int(11) NOT NULL,
  `id_liga` int(11) NOT NULL,
  `jornada` varchar(50) NOT NULL,
  `fecha` datetime NOT NULL,
  `lugar` varchar(100) NOT NULL,
  `arbitro` varchar(100) DEFAULT NULL,
  `id_equipo_local` int(11) NOT NULL,
  `id_equipo_visitante` int(11) NOT NULL,
  `goles_local` int(11) DEFAULT NULL,
  `goles_visitante` int(11) DEFAULT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'programado'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario`
--

CREATE TABLE `usuario` (
  `id_usuario` int(11) NOT NULL,
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
  `id_equipo` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `usuario`
--

INSERT INTO `usuario` (`id_usuario`, `nombre`, `apellido`, `fecha_nacimiento`, `email`, `pwd`, `telefono`, `rol`, `email_verificado`, `email_verification_code_hash`, `email_verification_expire`, `oauth_provider`, `oauth_id`, `reset_password_token`, `reset_password_expire`, `id_equipo`) VALUES
(21, 'Luciano', 'Tiniaco', '2004-06-05', 'tiniacoluciano05@gmail.com', '$2y$10$nogLaLeS/PgOKj/l9e5LW.vM5sWOQlfeGFvIxKUUi6ChxYsMCF83C', '0637054468', 'ADMIN', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(25, 'Luciano', 'Tiniaco', '2026-03-04', '00@gmail.com', '$2y$10$gqqyzor.B7Co39I0EhOyj.OEuehk0XAXlr7CV.XPS4kVtKAcJCrFq', '0637054468', 'STAFF', 1, NULL, NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuario_equipo_favorito`
--

CREATE TABLE `usuario_equipo_favorito` (
  `id_usuario` int(11) NOT NULL,
  `id_equipo` int(11) NOT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `clasificacion`
--
ALTER TABLE `clasificacion`
  ADD PRIMARY KEY (`id_clasificacion`),
  ADD UNIQUE KEY `uq_liga_equipo` (`id_liga`,`id_equipo`),
  ADD KEY `id_equipo` (`id_equipo`);

--
-- Indices de la tabla `entrenadores`
--
ALTER TABLE `entrenadores`
  ADD PRIMARY KEY (`id_entrenador`),
  ADD KEY `user_id` (`id_usuario`);

--
-- Indices de la tabla `entrenador_equipo`
--
ALTER TABLE `entrenador_equipo`
  ADD PRIMARY KEY (`id_entrenador`,`id_equipo`,`id_liga`),
  ADD KEY `id_equipo` (`id_equipo`),
  ADD KEY `id_liga` (`id_liga`);

--
-- Indices de la tabla `equipos`
--
ALTER TABLE `equipos`
  ADD PRIMARY KEY (`id_equipo`) USING BTREE,
  ADD UNIQUE KEY `uq_club_categoria` (`club`,`categoria`);

--
-- Indices de la tabla `equipo_jugador`
--
ALTER TABLE `equipo_jugador`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_jugador_equipo_liga` (`id_jugador`,`id_equipo`,`id_liga`),
  ADD KEY `id_equipo` (`id_equipo`),
  ADD KEY `id_liga` (`id_liga`);

--
-- Indices de la tabla `equipo_liga`
--
ALTER TABLE `equipo_liga`
  ADD PRIMARY KEY (`id_equipo`,`id_liga`),
  ADD KEY `id_liga` (`id_liga`);

--
-- Indices de la tabla `jugador`
--
ALTER TABLE `jugador`
  ADD PRIMARY KEY (`id_jugador`);

--
-- Indices de la tabla `ligas`
--
ALTER TABLE `ligas`
  ADD PRIMARY KEY (`id_liga`) USING BTREE,
  ADD UNIQUE KEY `uq_liga` (`nombre_liga`,`temporada`,`categoria`);

--
-- Indices de la tabla `partidos`
--
ALTER TABLE `partidos`
  ADD PRIMARY KEY (`id_partido`),
  ADD KEY `id_liga` (`id_liga`),
  ADD KEY `id_equipo1` (`id_equipo_local`),
  ADD KEY `id_equipo2` (`id_equipo_visitante`);

--
-- Indices de la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD PRIMARY KEY (`id_usuario`),
  ADD UNIQUE KEY `uq_usuario_email` (`email`),
  ADD UNIQUE KEY `uq_usuario_oauth` (`oauth_provider`,`oauth_id`),
  ADD KEY `fk_user_equipo` (`id_equipo`);

--
-- Indices de la tabla `usuario_equipo_favorito`
--
ALTER TABLE `usuario_equipo_favorito`
  ADD PRIMARY KEY (`id_usuario`,`id_equipo`),
  ADD KEY `fk_favorito_equipo` (`id_equipo`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `clasificacion`
--
ALTER TABLE `clasificacion`
  MODIFY `id_clasificacion` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `entrenadores`
--
ALTER TABLE `entrenadores`
  MODIFY `id_entrenador` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `equipos`
--
ALTER TABLE `equipos`
  MODIFY `id_equipo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=93;

--
-- AUTO_INCREMENT de la tabla `equipo_jugador`
--
ALTER TABLE `equipo_jugador`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `jugador`
--
ALTER TABLE `jugador`
  MODIFY `id_jugador` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de la tabla `ligas`
--
ALTER TABLE `ligas`
  MODIFY `id_liga` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT de la tabla `partidos`
--
ALTER TABLE `partidos`
  MODIFY `id_partido` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuario`
--
ALTER TABLE `usuario`
  MODIFY `id_usuario` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `clasificacion`
--
ALTER TABLE `clasificacion`
  ADD CONSTRAINT `id_equipo` FOREIGN KEY (`id_equipo`) REFERENCES `equipos` (`id_equipo`);

--
-- Filtros para la tabla `entrenadores`
--
ALTER TABLE `entrenadores`
  ADD CONSTRAINT `user_id` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`);

--
-- Filtros para la tabla `entrenador_equipo`
--
ALTER TABLE `entrenador_equipo`
  ADD CONSTRAINT `entrenador_equipo_ibfk_1` FOREIGN KEY (`id_entrenador`) REFERENCES `entrenadores` (`id_entrenador`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `entrenador_equipo_ibfk_2` FOREIGN KEY (`id_equipo`) REFERENCES `equipos` (`id_equipo`) ON UPDATE CASCADE,
  ADD CONSTRAINT `entrenador_equipo_ibfk_3` FOREIGN KEY (`id_liga`) REFERENCES `ligas` (`id_liga`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `equipo_jugador`
--
ALTER TABLE `equipo_jugador`
  ADD CONSTRAINT `equipo_jugador_ibfk_1` FOREIGN KEY (`id_jugador`) REFERENCES `jugador` (`id_jugador`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `equipo_jugador_ibfk_2` FOREIGN KEY (`id_equipo`) REFERENCES `equipos` (`id_equipo`) ON UPDATE CASCADE,
  ADD CONSTRAINT `equipo_jugador_ibfk_3` FOREIGN KEY (`id_liga`) REFERENCES `ligas` (`id_liga`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `equipo_liga`
--
ALTER TABLE `equipo_liga`
  ADD CONSTRAINT `equipo_liga_ibfk_1` FOREIGN KEY (`id_equipo`) REFERENCES `equipos` (`id_equipo`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `equipo_liga_ibfk_2` FOREIGN KEY (`id_liga`) REFERENCES `ligas` (`id_liga`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `partidos`
--
ALTER TABLE `partidos`
  ADD CONSTRAINT `id_equipo1` FOREIGN KEY (`id_equipo_local`) REFERENCES `equipos` (`id_equipo`),
  ADD CONSTRAINT `id_equipo2` FOREIGN KEY (`id_equipo_visitante`) REFERENCES `equipos` (`id_equipo`),
  ADD CONSTRAINT `id_liga` FOREIGN KEY (`id_liga`) REFERENCES `ligas` (`id_liga`);

--
-- Filtros para la tabla `usuario`
--
ALTER TABLE `usuario`
  ADD CONSTRAINT `fk_user_equipo` FOREIGN KEY (`id_equipo`) REFERENCES `equipos` (`id_equipo`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `usuario_equipo_favorito`
--
ALTER TABLE `usuario_equipo_favorito`
  ADD CONSTRAINT `fk_favorito_equipo` FOREIGN KEY (`id_equipo`) REFERENCES `equipos` (`id_equipo`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_favorito_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
