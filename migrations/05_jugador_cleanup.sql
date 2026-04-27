-- ============================================================================
-- Migration 05: Limpiar campos operativos de la tabla jugador
-- ============================================================================
-- La tabla jugador es ahora solo un registro de PERSONA GLOBAL.
-- Todo el estado operativo (pendiente/alta/baja, equipo, liga, solicitante)
-- vive en equipo_jugador.
-- ============================================================================

ALTER TABLE jugador
    DROP COLUMN IF EXISTS estado,
    DROP COLUMN IF EXISTS accion_solicitada,
    DROP COLUMN IF EXISTS id_equipo_solicitante,
    DROP COLUMN IF EXISTS id_usuario_solicitante,
    DROP COLUMN IF EXISTS id_liga_solicitante;

-- Resultado: jugador solo conserva datos biográficos inmutables:
--   id_jugador | nombre | apellido | fecha_nacimiento | foto_path | id_usuario
