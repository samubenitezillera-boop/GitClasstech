-- ════════════════════════════════════════════════════════════
-- Schema consolidado — INEM José Félix (listo para InfinityFree)
-- Reemplaza a: schema_infinityfree.sql (versión vieja) +
--              alter_usuarios_rol.sql + alter_reservas_grado.sql +
--              alter_usuarios_estudiante.sql
-- Si tu base YA tiene estas tablas creadas y funcionando, NO ejecutes
-- este archivo de nuevo — es solo para instalaciones nuevas desde cero.
-- Si tu base ya existe, corré en cambio alter_usuarios_estudiante.sql.
-- ════════════════════════════════════════════════════════════

-- Usuarios del sistema. 'rol' puede ser 'docente', 'estudiante' o
-- 'administrador' (este último solo se asigna a mano, nunca desde el
-- formulario público de registro). 'grado' y 'seccion' solo se usan
-- cuando rol = 'estudiante'; para docentes quedan vacíos.
CREATE TABLE IF NOT EXISTS usuarios (
  id              INT AUTO_INCREMENT PRIMARY KEY,
  nombre_completo VARCHAR(150)      NOT NULL,
  correo          VARCHAR(150)      NOT NULL UNIQUE,
  rol             VARCHAR(20)       NOT NULL DEFAULT 'docente',
  grado           VARCHAR(10)       DEFAULT '',
  seccion         VARCHAR(10)       DEFAULT '',
  password_hash   VARCHAR(255)      NOT NULL,
  codigo_acceso   CHAR(4)           NOT NULL,
  creado_en       TIMESTAMP         DEFAULT CURRENT_TIMESTAMP
);

-- Reservas de salones. "fecha" es una fecha real (no un día de la semana
-- genérico), así una reserva del 3 de septiembre queda ligada a esa fecha.
-- "grado" y "seccion" permiten agrupar la ocupación por curso en el panel admin.
CREATE TABLE IF NOT EXISTS reservas (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  bloque       INT           NOT NULL,
  salon        VARCHAR(10)   NOT NULL,
  fecha        DATE          NOT NULL,
  periodo      VARCHAR(5)    NOT NULL,
  hora         VARCHAR(20)   NOT NULL,
  materia      VARCHAR(150)  NOT NULL,
  grado        VARCHAR(10)   DEFAULT '',
  seccion      VARCHAR(10)   DEFAULT '',
  motivo       VARCHAR(255)  DEFAULT '',
  responsable  VARCHAR(150)  NOT NULL,
  creado_en    TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unico_slot (bloque, salon, fecha, periodo)
);

-- ── Para promover un usuario ya registrado a administrador ──────
-- UPDATE usuarios SET rol = 'administrador' WHERE correo = 'tu_correo@ejemplo.com';

-- ── Nota sobre limpieza de reservas vencidas ─────────────────────
-- InfinityFree NO permite el "event scheduler" de MySQL en el plan gratis.
-- reservas.php ya limpia registros vencidos según corresponda al consultar,
-- así que no hace falta un job programado.

-- ── Nota sobre el motor de tabla (opcional, no obligatorio) ──────
-- Tu base actual usa MyISAM (según el último backup). Si más adelante
-- quieres integridad referencial o transacciones, puedes migrar a InnoDB con:
--   ALTER TABLE usuarios ENGINE=InnoDB;
--   ALTER TABLE reservas ENGINE=InnoDB;
-- No es urgente para un proyecto de este tamaño.
