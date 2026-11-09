-- ============================================================
-- Futuro Digital - Esquema inicial
-- MySQL 8 / MariaDB 10.4+ · utf8mb4 · InnoDB
-- Zona horaria de la aplicación: America/Bogota
-- ============================================================
-- Reglas de diseño:
--  * Sin borrado físico de datos académicos/financieros: se usa estado.
--  * Saldos y cupos se calculan; no se almacenan.
--  * Auditoría con valor anterior y nuevo.
--  * Unicidad global de persona: (tipo_documento_id, numero_documento).
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `sedes` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codigo` VARCHAR(10) NOT NULL,
  `nombre` VARCHAR(80) NOT NULL,
  `direccion` VARCHAR(180) NULL,
  `telefono` VARCHAR(30) NULL,
  `estado` ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sedes_codigo` (`codigo`),
  UNIQUE KEY `uq_sedes_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `documento_tipos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codigo` VARCHAR(10) NOT NULL,
  `nombre` VARCHAR(60) NOT NULL,
  `estado` ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_documento_tipos_codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `parametros` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `clave` VARCHAR(80) NOT NULL,
  `valor` VARCHAR(255) NOT NULL,
  `tipo` ENUM('int','decimal','string','bool','json') NOT NULL DEFAULT 'string',
  `grupo` VARCHAR(60) NOT NULL DEFAULT 'general',
  `descripcion` VARCHAR(255) NULL,
  `updated_by` BIGINT UNSIGNED NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_parametros_clave` (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `roles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(60) NOT NULL,
  `descripcion` VARCHAR(255) NULL,
  `estado` ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permisos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `clave` VARCHAR(80) NOT NULL,
  `descripcion` VARCHAR(255) NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permisos_clave` (`clave`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rol_permiso` (
  `rol_id` INT UNSIGNED NOT NULL,
  `permiso_id` INT UNSIGNED NOT NULL,
  PRIMARY KEY (`rol_id`,`permiso_id`),
  CONSTRAINT `fk_rol_permiso_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rol_permiso_permiso` FOREIGN KEY (`permiso_id`) REFERENCES `permisos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `personas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tipo_documento_id` INT UNSIGNED NOT NULL,
  `numero_documento` VARCHAR(30) NOT NULL,
  `nombres` VARCHAR(100) NOT NULL,
  `apellidos` VARCHAR(100) NOT NULL,
  `fecha_nacimiento` DATE NULL,
  `email` VARCHAR(150) NULL,
  `telefono` VARCHAR(30) NULL,
  `direccion` VARCHAR(200) NULL,
  `autorizacion_datos` TINYINT(1) NOT NULL DEFAULT 0,
  `fecha_autorizacion` DATE NULL,
  `medio_autorizacion` VARCHAR(100) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_personas_documento` (`tipo_documento_id`,`numero_documento`),
  KEY `idx_personas_apellidos` (`apellidos`,`nombres`),
  CONSTRAINT `fk_personas_tipo_documento` FOREIGN KEY (`tipo_documento_id`) REFERENCES `documento_tipos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `estudiantes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `persona_id` BIGINT UNSIGNED NOT NULL,
  `sede_id` INT UNSIGNED NOT NULL,
  `codigo` VARCHAR(30) NULL,
  `estado` ENUM('activo','inactivo','graduado','retirado') NOT NULL DEFAULT 'activo',
  `created_by` BIGINT UNSIGNED NULL,
  `updated_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_estudiantes_persona` (`persona_id`),
  UNIQUE KEY `uq_estudiantes_codigo` (`codigo`),
  KEY `idx_estudiantes_sede` (`sede_id`,`estado`),
  CONSTRAINT `fk_estudiantes_persona` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`),
  CONSTRAINT `fk_estudiantes_sede` FOREIGN KEY (`sede_id`) REFERENCES `sedes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `docentes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `persona_id` BIGINT UNSIGNED NOT NULL,
  `sede_id` INT UNSIGNED NOT NULL,
  `codigo` VARCHAR(30) NULL,
  `titulo` VARCHAR(150) NULL,
  `tipo_vinculacion` ENUM('planta','catedra','contrato') NOT NULL DEFAULT 'catedra',
  `estado` ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  `created_by` BIGINT UNSIGNED NULL,
  `updated_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_docentes_persona` (`persona_id`),
  UNIQUE KEY `uq_docentes_codigo` (`codigo`),
  KEY `idx_docentes_sede` (`sede_id`,`estado`),
  CONSTRAINT `fk_docentes_persona` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`),
  CONSTRAINT `fk_docentes_sede` FOREIGN KEY (`sede_id`) REFERENCES `sedes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `persona_id` BIGINT UNSIGNED NOT NULL,
  `rol_id` INT UNSIGNED NOT NULL,
  `sede_id` INT UNSIGNED NULL,
  `username` VARCHAR(80) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `debe_cambiar_password` TINYINT(1) NOT NULL DEFAULT 1,
  `estado` ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  `intentos_fallidos` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `bloqueado_hasta` DATETIME NULL,
  `ultimo_acceso` DATETIME NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `updated_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuarios_username` (`username`),
  KEY `idx_usuarios_persona` (`persona_id`),
  KEY `idx_usuarios_rol_sede` (`rol_id`,`sede_id`),
  CONSTRAINT `fk_usuarios_persona` FOREIGN KEY (`persona_id`) REFERENCES `personas` (`id`),
  CONSTRAINT `fk_usuarios_rol` FOREIGN KEY (`rol_id`) REFERENCES `roles` (`id`),
  CONSTRAINT `fk_usuarios_sede` FOREIGN KEY (`sede_id`) REFERENCES `sedes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `acudientes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `estudiante_id` BIGINT UNSIGNED NOT NULL,
  `nombre` VARCHAR(150) NOT NULL,
  `documento` VARCHAR(30) NULL,
  `telefono` VARCHAR(30) NULL,
  `parentesco` VARCHAR(50) NULL,
  `estado` ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_acudientes_estudiante` (`estudiante_id`),
  CONSTRAINT `fk_acudientes_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `estudiantes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `programas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codigo` VARCHAR(20) NOT NULL,
  `nombre` VARCHAR(150) NOT NULL,
  `duracion_periodos` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `estado` ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  `created_by` BIGINT UNSIGNED NULL,
  `updated_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_programas_codigo` (`codigo`),
  UNIQUE KEY `uq_programas_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cursos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codigo` VARCHAR(20) NOT NULL,
  `nombre` VARCHAR(150) NOT NULL,
  `estado` ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  `created_by` BIGINT UNSIGNED NULL,
  `updated_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cursos_codigo` (`codigo`),
  UNIQUE KEY `uq_cursos_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `plan_estudios` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `programa_id` INT UNSIGNED NOT NULL,
  `curso_id` INT UNSIGNED NOT NULL,
  `nivel` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `obligatorio` TINYINT(1) NOT NULL DEFAULT 1,
  `estado` ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_plan_programa_curso` (`programa_id`,`curso_id`),
  KEY `idx_plan_programa_nivel` (`programa_id`,`nivel`,`estado`),
  CONSTRAINT `fk_plan_programa` FOREIGN KEY (`programa_id`) REFERENCES `programas` (`id`),
  CONSTRAINT `fk_plan_curso` FOREIGN KEY (`curso_id`) REFERENCES `cursos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `prerrequisitos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `curso_id` INT UNSIGNED NOT NULL,
  `curso_requisito_id` INT UNSIGNED NOT NULL,
  `tipo` ENUM('aprobado','cursado') NOT NULL DEFAULT 'aprobado',
  `estado` ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_prerrequisito` (`curso_id`,`curso_requisito_id`),
  CONSTRAINT `fk_prerrequisito_curso` FOREIGN KEY (`curso_id`) REFERENCES `cursos` (`id`),
  CONSTRAINT `fk_prerrequisito_requisito` FOREIGN KEY (`curso_requisito_id`) REFERENCES `cursos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `periodos` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codigo` VARCHAR(20) NOT NULL,
  `nombre` VARCHAR(100) NOT NULL,
  `fecha_inicio` DATE NOT NULL,
  `fecha_fin` DATE NOT NULL,
  `matricula_inicio` DATE NOT NULL,
  `matricula_fin` DATE NOT NULL,
  `estado` ENUM('planificado','matricula_abierta','en_curso','cerrado') NOT NULL DEFAULT 'planificado',
  `created_by` BIGINT UNSIGNED NULL,
  `updated_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_periodos_codigo` (`codigo`),
  UNIQUE KEY `uq_periodos_nombre` (`nombre`),
  KEY `idx_periodos_estado` (`estado`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `aulas` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sede_id` INT UNSIGNED NOT NULL,
  `nombre` VARCHAR(80) NOT NULL,
  `capacidad` INT UNSIGNED NOT NULL,
  `estado` ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_aulas_sede_nombre` (`sede_id`,`nombre`),
  CONSTRAINT `fk_aulas_sede` FOREIGN KEY (`sede_id`) REFERENCES `sedes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `grupos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codigo` VARCHAR(30) NOT NULL,
  `curso_id` INT UNSIGNED NOT NULL,
  `docente_id` BIGINT UNSIGNED NOT NULL,
  `periodo_id` INT UNSIGNED NOT NULL,
  `sede_id` INT UNSIGNED NOT NULL,
  `aula_id` INT UNSIGNED NOT NULL,
  `capacidad_max` INT UNSIGNED NOT NULL,
  `cupo_minimo` INT UNSIGNED NOT NULL DEFAULT 5,
  `estado` ENUM('activo','inactivo','cancelado') NOT NULL DEFAULT 'activo',
  `created_by` BIGINT UNSIGNED NULL,
  `updated_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_grupos_codigo` (`codigo`),
  KEY `idx_grupos_periodo_sede` (`periodo_id`,`sede_id`,`estado`),
  KEY `idx_grupos_docente` (`docente_id`,`periodo_id`),
  KEY `idx_grupos_aula` (`aula_id`,`periodo_id`),
  CONSTRAINT `fk_grupos_curso` FOREIGN KEY (`curso_id`) REFERENCES `cursos` (`id`),
  CONSTRAINT `fk_grupos_docente` FOREIGN KEY (`docente_id`) REFERENCES `docentes` (`id`),
  CONSTRAINT `fk_grupos_periodo` FOREIGN KEY (`periodo_id`) REFERENCES `periodos` (`id`),
  CONSTRAINT `fk_grupos_sede` FOREIGN KEY (`sede_id`) REFERENCES `sedes` (`id`),
  CONSTRAINT `fk_grupos_aula` FOREIGN KEY (`aula_id`) REFERENCES `aulas` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `grupo_horarios` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `grupo_id` BIGINT UNSIGNED NOT NULL,
  `dia_semana` TINYINT UNSIGNED NOT NULL COMMENT '1=lunes ... 7=domingo',
  `hora_inicio` TIME NOT NULL,
  `hora_fin` TIME NOT NULL,
  `estado` ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_horarios_grupo` (`grupo_id`,`estado`),
  KEY `idx_horarios_dia` (`dia_semana`,`hora_inicio`,`hora_fin`),
  CONSTRAINT `fk_horarios_grupo` FOREIGN KEY (`grupo_id`) REFERENCES `grupos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `matriculas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codigo` VARCHAR(30) NOT NULL,
  `estudiante_id` BIGINT UNSIGNED NOT NULL,
  `programa_id` INT UNSIGNED NOT NULL,
  `periodo_id` INT UNSIGNED NOT NULL,
  `sede_id` INT UNSIGNED NOT NULL,
  `fecha` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `estado` ENUM('activa','cancelada','finalizada') NOT NULL DEFAULT 'activa',
  `created_by` BIGINT UNSIGNED NULL,
  `updated_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_matriculas_codigo` (`codigo`),
  UNIQUE KEY `uq_matriculas_estudiante_programa_periodo` (`estudiante_id`,`programa_id`,`periodo_id`),
  KEY `idx_matriculas_periodo_sede` (`periodo_id`,`sede_id`,`estado`),
  CONSTRAINT `fk_matriculas_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `estudiantes` (`id`),
  CONSTRAINT `fk_matriculas_programa` FOREIGN KEY (`programa_id`) REFERENCES `programas` (`id`),
  CONSTRAINT `fk_matriculas_periodo` FOREIGN KEY (`periodo_id`) REFERENCES `periodos` (`id`),
  CONSTRAINT `fk_matriculas_sede` FOREIGN KEY (`sede_id`) REFERENCES `sedes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inscripciones` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `matricula_id` BIGINT UNSIGNED NOT NULL,
  `grupo_id` BIGINT UNSIGNED NOT NULL,
  `estado` ENUM('activa','cancelada','retirada','finalizada','perdida_inasistencia') NOT NULL DEFAULT 'activa',
  `nota_final` DECIMAL(3,1) NULL,
  `fecha_inscripcion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_cancelacion` DATETIME NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `updated_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_inscripciones_matricula_grupo` (`matricula_id`,`grupo_id`),
  KEY `idx_inscripciones_grupo_estado` (`grupo_id`,`estado`),
  KEY `idx_inscripciones_matricula` (`matricula_id`,`estado`),
  CONSTRAINT `fk_inscripciones_matricula` FOREIGN KEY (`matricula_id`) REFERENCES `matriculas` (`id`),
  CONSTRAINT `fk_inscripciones_grupo` FOREIGN KEY (`grupo_id`) REFERENCES `grupos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `inscripcion_movimientos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `inscripcion_id` BIGINT UNSIGNED NOT NULL,
  `tipo` ENUM('inscripcion','cambio_grupo','cancelacion','reactivacion','finalizacion') NOT NULL,
  `grupo_origen_id` BIGINT UNSIGNED NULL,
  `grupo_destino_id` BIGINT UNSIGNED NULL,
  `motivo` VARCHAR(255) NULL,
  `usuario_id` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_movimientos_inscripcion` (`inscripcion_id`,`created_at`),
  CONSTRAINT `fk_movimientos_inscripcion` FOREIGN KEY (`inscripcion_id`) REFERENCES `inscripciones` (`id`),
  CONSTRAINT `fk_movimientos_grupo_origen` FOREIGN KEY (`grupo_origen_id`) REFERENCES `grupos` (`id`),
  CONSTRAINT `fk_movimientos_grupo_destino` FOREIGN KEY (`grupo_destino_id`) REFERENCES `grupos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `evaluaciones` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `grupo_id` BIGINT UNSIGNED NOT NULL,
  `nombre` VARCHAR(120) NOT NULL,
  `peso` DECIMAL(5,2) NOT NULL,
  `fecha` DATE NULL,
  `estado` ENUM('activa','inactiva') NOT NULL DEFAULT 'activa',
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_evaluaciones_grupo_nombre` (`grupo_id`,`nombre`),
  CONSTRAINT `fk_evaluaciones_grupo` FOREIGN KEY (`grupo_id`) REFERENCES `grupos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `evaluacion_id` BIGINT UNSIGNED NOT NULL,
  `inscripcion_id` BIGINT UNSIGNED NOT NULL,
  `nota` DECIMAL(3,1) NOT NULL,
  `observacion` VARCHAR(255) NULL,
  `registrado_por` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_notas_evaluacion_inscripcion` (`evaluacion_id`,`inscripcion_id`),
  KEY `idx_notas_inscripcion` (`inscripcion_id`),
  CONSTRAINT `fk_notas_evaluacion` FOREIGN KEY (`evaluacion_id`) REFERENCES `evaluaciones` (`id`),
  CONSTRAINT `fk_notas_inscripcion` FOREIGN KEY (`inscripcion_id`) REFERENCES `inscripciones` (`id`),
  CONSTRAINT `chk_notas_rango` CHECK (`nota` >= 0.0 AND `nota` <= 5.0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sesiones` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `grupo_id` BIGINT UNSIGNED NOT NULL,
  `fecha` DATE NOT NULL,
  `hora_inicio` TIME NOT NULL,
  `hora_fin` TIME NOT NULL,
  `tema` VARCHAR(180) NULL,
  `estado` ENUM('abierta','cerrada') NOT NULL DEFAULT 'abierta',
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sesiones_grupo_fecha_hora` (`grupo_id`,`fecha`,`hora_inicio`),
  KEY `idx_sesiones_grupo_fecha` (`grupo_id`,`fecha`),
  CONSTRAINT `fk_sesiones_grupo` FOREIGN KEY (`grupo_id`) REFERENCES `grupos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `asistencias` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `sesion_id` BIGINT UNSIGNED NOT NULL,
  `inscripcion_id` BIGINT UNSIGNED NOT NULL,
  `estado` ENUM('presente','ausente','excusa','tarde') NOT NULL DEFAULT 'presente',
  `observacion` VARCHAR(255) NULL,
  `registrado_por` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_asistencias_sesion_inscripcion` (`sesion_id`,`inscripcion_id`),
  KEY `idx_asistencias_inscripcion` (`inscripcion_id`),
  CONSTRAINT `fk_asistencias_sesion` FOREIGN KEY (`sesion_id`) REFERENCES `sesiones` (`id`),
  CONSTRAINT `fk_asistencias_inscripcion` FOREIGN KEY (`inscripcion_id`) REFERENCES `inscripciones` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `excusas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `asistencia_id` BIGINT UNSIGNED NOT NULL,
  `motivo` VARCHAR(255) NOT NULL,
  `soporte` VARCHAR(255) NULL,
  `estado` ENUM('pendiente','aprobada','rechazada') NOT NULL DEFAULT 'aprobada',
  `aprobado_por` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_excusas_asistencia` (`asistencia_id`),
  CONSTRAINT `fk_excusas_asistencia` FOREIGN KEY (`asistencia_id`) REFERENCES `asistencias` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `alertas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `estudiante_id` BIGINT UNSIGNED NOT NULL,
  `inscripcion_id` BIGINT UNSIGNED NULL,
  `tipo` ENUM('inasistencia','bajo_rendimiento','mora') NOT NULL,
  `nivel` ENUM('informativa','advertencia','critica') NOT NULL DEFAULT 'advertencia',
  `mensaje` VARCHAR(255) NOT NULL,
  `estado` ENUM('nueva','atendida') NOT NULL DEFAULT 'nueva',
  `atendida_por` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atendida_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_alertas_estudiante` (`estudiante_id`,`estado`),
  KEY `idx_alertas_tipo_estado` (`tipo`,`estado`),
  CONSTRAINT `fk_alertas_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `estudiantes` (`id`),
  CONSTRAINT `fk_alertas_inscripcion` FOREIGN KEY (`inscripcion_id`) REFERENCES `inscripciones` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `conceptos_cargo` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codigo` VARCHAR(20) NOT NULL,
  `nombre` VARCHAR(100) NOT NULL,
  `estado` ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_conceptos_cargo_codigo` (`codigo`),
  UNIQUE KEY `uq_conceptos_cargo_nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tarifas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `concepto_id` INT UNSIGNED NOT NULL,
  `programa_id` INT UNSIGNED NULL,
  `periodo_id` INT UNSIGNED NULL,
  `valor` DECIMAL(12,2) NOT NULL,
  `estado` ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tarifas_concepto` (`concepto_id`,`programa_id`,`periodo_id`,`estado`),
  CONSTRAINT `fk_tarifas_concepto` FOREIGN KEY (`concepto_id`) REFERENCES `conceptos_cargo` (`id`),
  CONSTRAINT `fk_tarifas_programa` FOREIGN KEY (`programa_id`) REFERENCES `programas` (`id`),
  CONSTRAINT `fk_tarifas_periodo` FOREIGN KEY (`periodo_id`) REFERENCES `periodos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `cargos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `estudiante_id` BIGINT UNSIGNED NOT NULL,
  `matricula_id` BIGINT UNSIGNED NULL,
  `concepto_id` INT UNSIGNED NOT NULL,
  `descripcion` VARCHAR(180) NOT NULL,
  `valor_total` DECIMAL(12,2) NOT NULL,
  `fecha_emision` DATE NOT NULL,
  `fecha_vencimiento` DATE NOT NULL,
  `estado` ENUM('pendiente','pagado','anulado') NOT NULL DEFAULT 'pendiente',
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_cargos_estudiante` (`estudiante_id`,`estado`),
  KEY `idx_cargos_vencimiento` (`fecha_vencimiento`,`estado`),
  KEY `idx_cargos_matricula` (`matricula_id`),
  CONSTRAINT `fk_cargos_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `estudiantes` (`id`),
  CONSTRAINT `fk_cargos_matricula` FOREIGN KEY (`matricula_id`) REFERENCES `matriculas` (`id`),
  CONSTRAINT `fk_cargos_concepto` FOREIGN KEY (`concepto_id`) REFERENCES `conceptos_cargo` (`id`),
  CONSTRAINT `chk_cargos_valor` CHECK (`valor_total` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pagos` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `estudiante_id` BIGINT UNSIGNED NOT NULL,
  `fecha` DATE NOT NULL,
  `valor` DECIMAL(12,2) NOT NULL,
  `medio` ENUM('efectivo','transferencia','tarjeta','otro') NOT NULL DEFAULT 'efectivo',
  `referencia` VARCHAR(100) NULL,
  `estado` ENUM('registrado','anulado') NOT NULL DEFAULT 'registrado',
  `motivo_anulacion` VARCHAR(255) NULL,
  `anulado_por` BIGINT UNSIGNED NULL,
  `anulado_at` DATETIME NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pagos_estudiante` (`estudiante_id`,`estado`),
  KEY `idx_pagos_fecha` (`fecha`,`estado`),
  CONSTRAINT `fk_pagos_estudiante` FOREIGN KEY (`estudiante_id`) REFERENCES `estudiantes` (`id`),
  CONSTRAINT `chk_pagos_valor` CHECK (`valor` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pago_aplicaciones` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pago_id` BIGINT UNSIGNED NOT NULL,
  `cargo_id` BIGINT UNSIGNED NOT NULL,
  `valor` DECIMAL(12,2) NOT NULL,
  `estado` ENUM('activa','anulada') NOT NULL DEFAULT 'activa',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_aplicaciones_pago` (`pago_id`,`estado`),
  KEY `idx_aplicaciones_cargo` (`cargo_id`,`estado`),
  CONSTRAINT `fk_aplicaciones_pago` FOREIGN KEY (`pago_id`) REFERENCES `pagos` (`id`),
  CONSTRAINT `fk_aplicaciones_cargo` FOREIGN KEY (`cargo_id`) REFERENCES `cargos` (`id`),
  CONSTRAINT `chk_aplicaciones_valor` CHECK (`valor` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `comprobantes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pago_id` BIGINT UNSIGNED NOT NULL,
  `numero` VARCHAR(30) NOT NULL,
  `fecha` DATE NOT NULL,
  `valor` DECIMAL(12,2) NOT NULL,
  `estado` ENUM('vigente','anulado') NOT NULL DEFAULT 'vigente',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_comprobantes_numero` (`numero`),
  UNIQUE KEY `uq_comprobantes_pago` (`pago_id`),
  CONSTRAINT `fk_comprobantes_pago` FOREIGN KEY (`pago_id`) REFERENCES `pagos` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `auditoria` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id` BIGINT UNSIGNED NULL,
  `accion` VARCHAR(30) NOT NULL,
  `modulo` VARCHAR(60) NOT NULL,
  `entidad` VARCHAR(60) NOT NULL,
  `entidad_id` VARCHAR(40) NULL,
  `valor_anterior` JSON NULL,
  `valor_nuevo` JSON NULL,
  `ip` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_auditoria_usuario` (`usuario_id`,`created_at`),
  KEY `idx_auditoria_entidad` (`entidad`,`entidad_id`),
  KEY `idx_auditoria_modulo` (`modulo`,`created_at`),
  CONSTRAINT `fk_auditoria_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `intentos_login` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(80) NOT NULL,
  `ip` VARCHAR(45) NULL,
  `exito` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_intentos_username` (`username`,`created_at`),
  KEY `idx_intentos_ip` (`ip`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `migraciones` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `archivo` VARCHAR(180) NOT NULL,
  `aplicada_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_migraciones_archivo` (`archivo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
