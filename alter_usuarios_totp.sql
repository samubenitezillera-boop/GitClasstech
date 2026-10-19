-- ════════════════════════════════════════════════════════════
-- Migración: verificación en dos pasos (2FA) por app autenticadora
-- (Google Authenticator, Authy, Microsoft Authenticator, etc. vía
--  código QR / TOTP — RFC 6238). No requiere ningún servicio externo.
-- Ejecutar UNA sola vez en phpMyAdmin de InfinityFree (pestaña SQL).
-- Requiere que ya se haya ejecutado alter_usuarios_reset_token.sql
-- (agrega reset_token_hash / reset_token_expira, usados como AFTER).
-- ════════════════════════════════════════════════════════════

ALTER TABLE usuarios
  ADD COLUMN totp_secret      VARCHAR(64)  NULL     AFTER reset_token_expira,
  ADD COLUMN totp_habilitado  TINYINT(1)   NOT NULL DEFAULT 0 AFTER totp_secret;

-- totp_secret: clave secreta en Base32, usada para generar y verificar
--   los códigos de 6 dígitos que muestra la app autenticadora del
--   usuario. Solo existe mientras el 2FA está activado; se borra
--   (NULL) apenas el usuario lo desactiva.
-- totp_habilitado: 1 = además de correo/contraseña, el usuario debe
--   ingresar el código de 6 dígitos de su app para poder entrar.
