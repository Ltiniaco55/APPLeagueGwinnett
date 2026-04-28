-- ============================================================================
--  Migración 06: Añadir campos de documentación e información de padres/tutores
--  a la tabla jugador.
--
--  Ejecutar una sola vez sobre la base de datos del proyecto.
-- ============================================================================

ALTER TABLE jugador
    ADD COLUMN documento_identidad_path VARCHAR(255) NULL COMMENT 'Ruta al archivo de documento de identidad subido',
    ADD COLUMN nombres_padres           VARCHAR(255) NULL COMMENT 'Nombre/s del padre, madre o tutor legal (obligatorio si menor de 18)',
    ADD COLUMN email_padres             VARCHAR(255) NULL COMMENT 'Email del padre, madre o tutor (obligatorio si menor de 18)',
    ADD COLUMN telefono_padres          VARCHAR(30)  NULL COMMENT 'Teléfono del padre, madre o tutor (obligatorio si menor de 18)';
