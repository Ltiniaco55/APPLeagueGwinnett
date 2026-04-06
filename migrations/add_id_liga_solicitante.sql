-- =====================================================
-- Añadir columna id_liga_solicitante a tabla jugador
-- Necesaria para que la aprobación admin sepa en qué liga insertar
-- =====================================================
ALTER TABLE jugador
ADD COLUMN id_liga_solicitante INT NULL DEFAULT NULL
AFTER id_usuario_solicitante;
