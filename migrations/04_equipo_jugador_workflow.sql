-- ============================================================================
-- Migration 04: Add workflow fields to equipo_jugador
-- ============================================================================
-- The player-team relationship (equipo_jugador) now carries all the
-- operational state. The jugador table remains a global person record.
-- ============================================================================

ALTER TABLE equipo_jugador
    ADD COLUMN IF NOT EXISTS estado               ENUM('PENDIENTE','ALTA') NOT NULL DEFAULT 'ALTA',
    ADD COLUMN IF NOT EXISTS accion_solicitada    ENUM('ALTA','BAJA') NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS id_usuario_solicitante INT NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS fecha_solicitud      DATETIME NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS id_admin_resolutor   INT NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS fecha_resolucion     DATETIME NULL DEFAULT NULL;

-- Unique constraint: only one active/pending record per jugador+equipo+liga
-- (prevents duplicate pending requests for same relationship)
ALTER TABLE equipo_jugador
    ADD CONSTRAINT uq_jugador_equipo_liga UNIQUE (id_jugador, id_equipo, id_liga);
