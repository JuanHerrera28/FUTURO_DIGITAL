-- ============================================================
-- Futuro Digital - Datos base
-- Ejecutar después de schema.sql
-- ============================================================
SET NAMES utf8mb4;

INSERT INTO `sedes` (`id`,`codigo`,`nombre`,`direccion`,`telefono`,`estado`) VALUES
  (1,'SIN','Sincelejo','Sede principal Sincelejo','','activo'),
  (2,'COR','Corozal','Sede Corozal','','activo')
ON DUPLICATE KEY UPDATE `nombre`=VALUES(`nombre`), `estado`=VALUES(`estado`);

INSERT INTO `documento_tipos` (`id`,`codigo`,`nombre`,`estado`) VALUES
  (1,'RC','Registro civil','activo'),
  (2,'TI','Tarjeta de identidad','activo'),
  (3,'CC','Cédula de ciudadanía','activo'),
  (4,'CE','Cédula de extranjería','activo'),
  (5,'PA','Pasaporte','activo')
ON DUPLICATE KEY UPDATE `nombre`=VALUES(`nombre`), `estado`=VALUES(`estado`);

INSERT INTO `roles` (`id`,`nombre`,`descripcion`,`estado`) VALUES
  (1,'Administrador','Gestiona usuarios, roles, permisos, docentes, parámetros y auditoría','activo'),
  (2,'Secretaría','Gestiona estudiantes, matrículas, inscripciones, pagos y claves de su sede','activo'),
  (3,'Coordinador Académico','Gestiona programas, cursos, plan de estudios, periodos, aulas, grupos, alertas e informes','activo'),
  (4,'Coordinador Administrativo','Gestiona pagos, comprobantes e informes administrativos','activo'),
  (5,'Director','Consulta alertas e informes de ambas sedes','activo'),
  (6,'Docente','Consulta sus grupos y registra calificaciones y asistencia','activo'),
  (7,'Estudiante','Consulta sus notas, asistencia, horario y estado de cuenta','activo')
ON DUPLICATE KEY UPDATE `descripcion`=VALUES(`descripcion`), `estado`=VALUES(`estado`);

INSERT INTO `permisos` (`id`,`clave`,`descripcion`) VALUES
  (1,'dashboard.view','Ver el panel principal'),
  (2,'students.view','Consultar estudiantes'),
  (3,'students.manage','Crear y actualizar estudiantes'),
  (4,'teachers.view','Consultar docentes'),
  (5,'teachers.manage','Crear y actualizar docentes'),
  (6,'programs.view','Consultar programas y plan de estudios'),
  (7,'programs.manage','Administrar programas, plan de estudios y prerrequisitos'),
  (8,'courses.view','Consultar cursos'),
  (9,'courses.manage','Administrar cursos'),
  (10,'periods.view','Consultar periodos'),
  (11,'periods.manage','Administrar y cerrar periodos'),
  (12,'classrooms.view','Consultar aulas'),
  (13,'classrooms.manage','Administrar aulas'),
  (14,'groups.view','Consultar grupos y horarios'),
  (15,'groups.manage','Crear y actualizar grupos'),
  (16,'enrollment.view','Consultar matrículas e inscripciones'),
  (17,'enrollment.manage','Matricular, inscribir, cambiar de grupo y cancelar'),
  (18,'grades.view','Consultar calificaciones'),
  (19,'grades.manage','Registrar evaluaciones y calificaciones'),
  (20,'attendance.view','Consultar asistencia'),
  (21,'attendance.manage','Registrar sesiones y asistencia'),
  (22,'payments.view','Consultar cargos, pagos y estados de cuenta'),
  (23,'payments.manage','Registrar cargos, pagos y anulaciones'),
  (24,'alerts.view','Consultar alertas'),
  (25,'alerts.manage','Atender alertas'),
  (26,'reports.view','Generar informes'),
  (27,'users.manage','Administrar usuarios y restablecer contraseñas'),
  (28,'roles.manage','Administrar roles y permisos'),
  (29,'params.manage','Administrar parámetros'),
  (30,'audit.view','Consultar la auditoría'),
  (31,'portal.view','Consultar el portal del estudiante')
ON DUPLICATE KEY UPDATE `descripcion`=VALUES(`descripcion`);

-- Administrador: acceso global y gestión técnica
INSERT IGNORE INTO `rol_permiso` (`rol_id`,`permiso_id`) VALUES
  (1,1),(1,2),(1,4),(1,5),(1,6),(1,8),(1,10),(1,12),(1,14),(1,16),(1,18),(1,20),(1,22),(1,24),(1,26),(1,27),(1,28),(1,29),(1,30);
-- Secretaría: su sede
INSERT IGNORE INTO `rol_permiso` (`rol_id`,`permiso_id`) VALUES
  (2,1),(2,2),(2,3),(2,4),(2,6),(2,8),(2,10),(2,12),(2,14),(2,16),(2,17),(2,22),(2,23),(2,24);
-- Coordinador académico: oferta y control académico global
INSERT IGNORE INTO `rol_permiso` (`rol_id`,`permiso_id`) VALUES
  (3,1),(3,2),(3,4),(3,5),(3,6),(3,7),(3,8),(3,9),(3,10),(3,11),(3,12),(3,13),(3,14),(3,15),(3,16),(3,18),(3,19),(3,20),(3,21),(3,24),(3,25),(3,26);
-- Coordinador administrativo: pagos e informes
INSERT IGNORE INTO `rol_permiso` (`rol_id`,`permiso_id`) VALUES
  (4,1),(4,2),(4,16),(4,22),(4,23),(4,24),(4,26);
-- Director: lectura global
INSERT IGNORE INTO `rol_permiso` (`rol_id`,`permiso_id`) VALUES
  (5,1),(5,2),(5,4),(5,6),(5,8),(5,10),(5,12),(5,14),(5,16),(5,18),(5,20),(5,22),(5,24),(5,26);
-- Docente: sus grupos
INSERT IGNORE INTO `rol_permiso` (`rol_id`,`permiso_id`) VALUES
  (6,1),(6,14),(6,18),(6,19),(6,20),(6,21);
-- Estudiante: su portal
INSERT IGNORE INTO `rol_permiso` (`rol_id`,`permiso_id`) VALUES
  (7,1),(7,31);

INSERT INTO `parametros` (`clave`,`valor`,`tipo`,`grupo`,`descripcion`) VALUES
  ('institucion_nombre','Centro Educativo Futuro Digital','string','general','Nombre de la institución'),
  ('nombre_sistema','Futuro Digital','string','general','Nombre visible del sistema'),
  ('moneda','COP','string','general','Moneda de los valores financieros'),
  ('zona_horaria','America/Bogota','string','general','Zona horaria de la aplicación'),
  ('nota_minima_escala','0.0','decimal','academico','Nota mínima de la escala'),
  ('nota_maxima','5.0','decimal','academico','Nota máxima de la escala'),
  ('nota_minima','3.0','decimal','academico','Nota mínima aprobatoria'),
  ('decimales_nota','1','int','academico','Decimales para mostrar y guardar notas'),
  ('alerta_inasistencia','20','decimal','asistencia','Porcentaje de inasistencia que genera alerta'),
  ('perdida_inasistencia','25','decimal','asistencia','Porcentaje de inasistencia que marca pérdida'),
  ('bajo_rendimiento_nota','3.0','decimal','academico','Promedio parcial que marca bajo rendimiento'),
  ('bajo_rendimiento_peso','50','decimal','academico','Peso evaluado mínimo para marcar bajo rendimiento'),
  ('max_cursos_periodo','7','int','matricula','Máximo de cursos activos por periodo'),
  ('plazo_cancelacion_dias','15','int','matricula','Días calendario para cancelar desde el inicio del periodo'),
  ('mora_dias','30','int','financiero','Días de mora que generan restricción'),
  ('dias_vencimiento_matricula','30','int','financiero','Días para vencer el cargo de matrícula'),
  ('intentos_fallidos','5','int','seguridad','Intentos fallidos antes del bloqueo'),
  ('bloqueo_minutos','15','int','seguridad','Minutos de bloqueo de la cuenta'),
  ('sesion_minutos','30','int','seguridad','Minutos de inactividad antes de cerrar la sesión'),
  ('cupo_minimo_grupo','5','int','academico','Cupo mínimo para abrir un grupo')
ON DUPLICATE KEY UPDATE `valor`=VALUES(`valor`), `tipo`=VALUES(`tipo`), `grupo`=VALUES(`grupo`), `descripcion`=VALUES(`descripcion`);

INSERT INTO `conceptos_cargo` (`id`,`codigo`,`nombre`,`estado`) VALUES
  (1,'MAT','Matrícula','activo'),
  (2,'COL','Colegiatura','activo'),
  (3,'MATE','Materiales','activo'),
  (4,'OTRO','Otros','activo')
ON DUPLICATE KEY UPDATE `nombre`=VALUES(`nombre`), `estado`=VALUES(`estado`);

INSERT INTO `tarifas` (`concepto_id`,`programa_id`,`periodo_id`,`valor`,`estado`)
SELECT 1,NULL,NULL,350000.00,'activo' WHERE NOT EXISTS (SELECT 1 FROM `tarifas` WHERE `concepto_id`=1 AND `programa_id` IS NULL AND `periodo_id` IS NULL);
INSERT INTO `tarifas` (`concepto_id`,`programa_id`,`periodo_id`,`valor`,`estado`)
SELECT 2,NULL,NULL,450000.00,'activo' WHERE NOT EXISTS (SELECT 1 FROM `tarifas` WHERE `concepto_id`=2 AND `programa_id` IS NULL AND `periodo_id` IS NULL);
INSERT INTO `tarifas` (`concepto_id`,`programa_id`,`periodo_id`,`valor`,`estado`)
SELECT 3,NULL,NULL,80000.00,'activo' WHERE NOT EXISTS (SELECT 1 FROM `tarifas` WHERE `concepto_id`=3 AND `programa_id` IS NULL AND `periodo_id` IS NULL);
INSERT INTO `tarifas` (`concepto_id`,`programa_id`,`periodo_id`,`valor`,`estado`)
SELECT 4,NULL,NULL,0.00,'activo' WHERE NOT EXISTS (SELECT 1 FROM `tarifas` WHERE `concepto_id`=4 AND `programa_id` IS NULL AND `periodo_id` IS NULL);

-- Administrador inicial: admin / Admin123* (cambio obligatorio al primer ingreso)
INSERT INTO `personas` (`tipo_documento_id`,`numero_documento`,`nombres`,`apellidos`,`email`,`autorizacion_datos`,`fecha_autorizacion`)
SELECT 3,'1000000000','Administrador','General','admin@futurodigital.edu',1,CURDATE()
WHERE NOT EXISTS (SELECT 1 FROM `personas` WHERE `tipo_documento_id`=3 AND `numero_documento`='1000000000');

INSERT INTO `usuarios` (`persona_id`,`rol_id`,`sede_id`,`username`,`password_hash`,`debe_cambiar_password`,`estado`)
SELECT p.`id`,1,NULL,'admin','$2y$10$019r.45e9c/zJa12c50f0.j0YVHm6Nb2fhwg6/vKjQNpsZF44oQgy',1,'activo'
FROM `personas` p
WHERE p.`tipo_documento_id`=3 AND p.`numero_documento`='1000000000'
  AND NOT EXISTS (SELECT 1 FROM `usuarios` WHERE `username`='admin');
