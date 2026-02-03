-- =========================
-- LIGAS
-- =========================
CREATE TABLE ligas (
  id_liga INT NOT NULL AUTO_INCREMENT,
  nombre_liga VARCHAR(100) NOT NULL,
  temporada VARCHAR(50) NOT NULL,
  descripcion VARCHAR(50) NOT NULL,
  PRIMARY KEY (id_liga, temporada)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- CATEGORIAS
-- =========================
CREATE TABLE categorias (
  id_categoria INT NOT NULL AUTO_INCREMENT,
  nombre_categoria VARCHAR(50) NOT NULL,
  PRIMARY KEY (id_categoria),
  UNIQUE KEY uq_categorias_nombre (nombre_categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- EQUIPOS
-- =========================
CREATE TABLE equipos (
  id_equipo INT NOT NULL AUTO_INCREMENT,
  nombre_equipo VARCHAR(120) NOT NULL,
  descripcion LONGTEXT NULL,
  id_categoria INT NOT NULL,
  PRIMARY KEY (id_equipo),
  
    FOREIGN KEY (id_categoria)
    REFERENCES categorias(id_categoria)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- USUARIOS
-- =========================
CREATE TABLE usuario (
  id_usuario INT NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(100) NOT NULL,
  apellido VARCHAR(100) NOT NULL,
  fecha_nacimiento DATE NULL,
  sexo VARCHAR(20) NOT NULL,
  email VARCHAR(180) NULL,
  pwd VARCHAR(255) NOT NULL,
  telefono VARCHAR(40) NULL,
  PRIMARY KEY (id_usuario),
  UNIQUE KEY uq_usuario_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- JUGADOR
-- =========================
CREATE TABLE jugador (
  id_jugador INT NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(100) NOT NULL,
  apellido VARCHAR(100) NOT NULL,
  fecha_nacimiento DATE NOT NULL,
  foto_path VARCHAR(255) NULL,
  id_usuario INT NULL,
  PRIMARY KEY (id_jugador),

    FOREIGN KEY (id_usuario)
    REFERENCES usuario(id_usuario)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- ENTRENADORES
-- =========================
CREATE TABLE entrenadores (
  id_entrenador INT NOT NULL AUTO_INCREMENT,
  id_usuario INT NULL,
  nombre VARCHAR(100) NOT NULL,
  apellido VARCHAR(100) NOT NULL,
  fecha_nacimiento DATE NULL,
  telefono VARCHAR(40) NULL,
  email VARCHAR(180) NULL,
  PRIMARY KEY (id_entrenador),
 
    FOREIGN KEY (id_usuario)
    REFERENCES usuario(id_usuario)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- EQUIPO_LIGA
-- =========================
CREATE TABLE equipo_liga (
  id_equipo INT NOT NULL,
  id_liga INT NOT NULL,
  temporada VARCHAR(50) NOT NULL,
  PRIMARY KEY (id_liga, temporada, id_equipo),
  CONSTRAINT fk_equipo_liga_equipo
    FOREIGN KEY (id_equipo)
    REFERENCES equipos(id_equipo)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,

    FOREIGN KEY (id_liga, temporada)
    REFERENCES ligas(id_liga, temporada)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =========================
-- EQUIPO_JUGADOR
-- =========================
CREATE TABLE equipo_jugador (
  id_jugador INT NOT NULL,
  id_equipo INT NOT NULL,
  id_liga INT NOT NULL,
  temporada VARCHAR(50) NOT NULL,
  dorsal INT NULL,
  estado VARCHAR(20) NOT NULL,
  PRIMARY KEY (id_jugador, id_equipo, id_liga, temporada),
    FOREIGN KEY (id_jugador)
    REFERENCES jugador(id_jugador)
    ON DELETE CASCADE
    ON UPDATE CASCADE,

    FOREIGN KEY (id_equipo)
    REFERENCES equipos(id_equipo)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,

    FOREIGN KEY (id_liga, temporada)
    REFERENCES ligas(id_liga, temporada)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,
  
    FOREIGN KEY (id_liga, temporada, id_equipo)
    REFERENCES equipo_liga(id_liga, temporada, id_equipo)
    ON DELETE RESTRICT
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE entrenador_equipo (
  id_entrenador INT NOT NULL,
  id_equipo INT NOT NULL,
  id_liga INT NOT NULL,
  temporada VARCHAR(50) NOT NULL,
  estado VARCHAR(20) NOT NULL,

  PRIMARY KEY (id_entrenador, id_equipo, id_liga, temporada),

  -- entrenador -> entrenadores
    FOREIGN KEY (id_entrenador)
    REFERENCES entrenadores(id_entrenador)
    ON DELETE CASCADE
    ON UPDATE CASCADE,

  -- equipo -> equipos
    FOREIGN KEY (id_equipo)
    REFERENCES equipos(id_equipo)
    ON DELETE RESTRICT
    ON UPDATE CASCADE,

  -- liga+temporada -> ligas
    FOREIGN KEY (id_liga, temporada)
    REFERENCES ligas(id_liga, temporada)
    ON DELETE RESTRICT
    ON UPDATE CASCADE

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
