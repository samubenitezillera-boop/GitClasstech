-- ════════════════════════════════════════════════════════════
-- Migración: recuperación de contraseña por correo (vía Brevo)
-- Ejecutar UNA sola vez en la base de datos ya existente
-- (en phpMyAdmin de InfinityFree: pestaña SQL, pegar y ejecutar).
-- ════════════════════════════════════════════════════════════

ALTER TABLE usuarios
  ADD COLUMN reset_token_hash    VARCHAR(64)  NULL AFTER codigo_acceso,
  ADD COLUMN reset_token_expira  DATETIME     NULL AFTER reset_token_hash;

-- reset_token_hash: hash SHA-256 del token que se manda por correo
--   (el token en sí NUNCA se guarda en la base de datos, solo su hash,
--   igual que ya hacés con las contraseñas).
-- reset_token_expira: el enlace deja de servir 30 minutos después de
--   pedirlo, así un enlace viejo en la bandeja de entrada no queda
--   utilizable para siempre.
