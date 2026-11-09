<?php

declare(strict_types=1);

/**
 * Futuro Digital - Datos de demostración.
 *
 * Uso:
 *   php database/demo.php            (inserta si aún no existe la oferta demo)
 *   php database/demo.php --fresh    (borra los datos académicos y vuelve a sembrar)
 *
 * Este script escribe directamente en la base con las mismas reglas de
 * integridad del esquema. No requiere sesión HTTP.
 */

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/bootstrap/autoload.php';

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;

Env::load(BASE_PATH . '/.env');
Config::setAll(require BASE_PATH . '/config/config.php');
date_default_timezone_set((string) Config::get('app.timezone', 'America/Bogota'));
Database::init((array) Config::get('db', []));

$fresh = in_array('--fresh', $argv, true);

$already = Database::scalar("SELECT COUNT(*) FROM programas WHERE codigo = 'TEC-SIS'");

if (!$fresh && (int) $already > 0) {
    fwrite(STDOUT, "Los datos demo ya están cargados. Use --fresh para recrearlos.\n");
    exit(0);
}

/** @var array<string,string> $passwords */
$passwords = [];
$demoHash = password_hash('Demo123*', PASSWORD_DEFAULT);

/**
 * @param array<string,mixed> $data
 */
function ins(string $table, array $data): int
{
    return Database::insert($table, $data);
}

function person(int $tipo, string $doc, string $nombres, string $apellidos, ?string $email = null, ?string $tel = null, ?string $nacimiento = null): int
{
    return ins('personas', [
        'tipo_documento_id' => $tipo,
        'numero_documento' => $doc,
        'nombres' => $nombres,
        'apellidos' => $apellidos,
        'fecha_nacimiento' => $nacimiento,
        'email' => $email,
        'telefono' => $tel,
        'direccion' => null,
        'autorizacion_datos' => 1,
        'fecha_autorizacion' => date('Y-m-d'),
        'medio_autorizacion' => 'Presencial',
    ]);
}

function user(int $personaId, int $rolId, ?int $sedeId, string $username, string $hash): int
{
    return ins('usuarios', [
        'persona_id' => $personaId,
        'rol_id' => $rolId,
        'sede_id' => $sedeId,
        'username' => $username,
        'password_hash' => $hash,
        'debe_cambiar_password' => 0,
        'estado' => 'activo',
        'created_by' => 1,
    ]);
}

function student(int $personaId, int $sedeId, string $codigo): int
{
    return ins('estudiantes', [
        'persona_id' => $personaId,
        'sede_id' => $sedeId,
        'codigo' => $codigo,
        'estado' => 'activo',
        'created_by' => 1,
    ]);
}

function teacher(int $personaId, int $sedeId, string $codigo, string $titulo, string $vinculacion): int
{
    return ins('docentes', [
        'persona_id' => $personaId,
        'sede_id' => $sedeId,
        'codigo' => $codigo,
        'titulo' => $titulo,
        'tipo_vinculacion' => $vinculacion,
        'estado' => 'activo',
        'created_by' => 1,
    ]);
}

function group(int $cursoId, int $docenteId, int $periodoId, int $sedeId, int $aulaId, string $codigo, int $cap, int $min = 5): int
{
    return ins('grupos', [
        'codigo' => $codigo,
        'curso_id' => $cursoId,
        'docente_id' => $docenteId,
        'periodo_id' => $periodoId,
        'sede_id' => $sedeId,
        'aula_id' => $aulaId,
        'capacidad_max' => $cap,
        'cupo_minimo' => $min,
        'estado' => 'activo',
        'created_by' => 1,
    ]);
}

function schedule(int $grupoId, int $dia, string $inicio, string $fin): void
{
    ins('grupo_horarios', [
        'grupo_id' => $grupoId,
        'dia_semana' => $dia,
        'hora_inicio' => $inicio,
        'hora_fin' => $fin,
        'estado' => 'activo',
    ]);
}

function matricula(string $codigo, int $estudianteId, int $programaId, int $periodoId, int $sedeId, string $estado = 'activa'): int
{
    return ins('matriculas', [
        'codigo' => $codigo,
        'estudiante_id' => $estudianteId,
        'programa_id' => $programaId,
        'periodo_id' => $periodoId,
        'sede_id' => $sedeId,
        'fecha' => date('Y-m-d H:i:s'),
        'estado' => $estado,
        'created_by' => 1,
    ]);
}

function inscription(int $matriculaId, int $grupoId, string $estado = 'activa', ?float $notaFinal = null): int
{
    return ins('inscripciones', [
        'matricula_id' => $matriculaId,
        'grupo_id' => $grupoId,
        'estado' => $estado,
        'nota_final' => $notaFinal,
        'fecha_inscripcion' => date('Y-m-d H:i:s'),
        'created_by' => 1,
    ]);
}

function movement(int $inscripcionId, string $tipo, ?int $origen, ?int $destino, string $motivo): void
{
    ins('inscripcion_movimientos', [
        'inscripcion_id' => $inscripcionId,
        'tipo' => $tipo,
        'grupo_origen_id' => $origen,
        'grupo_destino_id' => $destino,
        'motivo' => $motivo,
        'usuario_id' => 1,
    ]);
}

function evaluation(int $grupoId, string $nombre, float $peso, string $fecha): int
{
    return ins('evaluaciones', [
        'grupo_id' => $grupoId,
        'nombre' => $nombre,
        'peso' => $peso,
        'fecha' => $fecha,
        'estado' => 'activa',
        'created_by' => 1,
    ]);
}

function note(int $evaluacionId, int $inscripcionId, float $nota, ?string $obs = null): void
{
    ins('notas', [
        'evaluacion_id' => $evaluacionId,
        'inscripcion_id' => $inscripcionId,
        'nota' => $nota,
        'observacion' => $obs,
        'registrado_por' => 1,
    ]);
}

function session(int $grupoId, string $fecha, string $inicio, string $fin, string $tema): int
{
    return ins('sesiones', [
        'grupo_id' => $grupoId,
        'fecha' => $fecha,
        'hora_inicio' => $inicio,
        'hora_fin' => $fin,
        'tema' => $tema,
        'estado' => 'cerrada',
        'created_by' => 1,
    ]);
}

function attendance(int $sesionId, int $inscripcionId, string $estado, ?string $obs = null): void
{
    ins('asistencias', [
        'sesion_id' => $sesionId,
        'inscripcion_id' => $inscripcionId,
        'estado' => $estado,
        'observacion' => $obs,
        'registrado_por' => 1,
    ]);
}

function charge(int $estudianteId, int $matriculaId, int $conceptoId, string $descripcion, float $valor, string $vence, string $estado = 'pendiente'): int
{
    return ins('cargos', [
        'estudiante_id' => $estudianteId,
        'matricula_id' => $matriculaId,
        'concepto_id' => $conceptoId,
        'descripcion' => $descripcion,
        'valor_total' => $valor,
        'fecha_emision' => date('Y-m-d'),
        'fecha_vencimiento' => $vence,
        'estado' => $estado,
        'created_by' => 1,
    ]);
}

function payment(int $estudianteId, float $valor, string $medio, string $referencia, string $fecha): int
{
    return ins('pagos', [
        'estudiante_id' => $estudianteId,
        'fecha' => $fecha,
        'valor' => $valor,
        'medio' => $medio,
        'referencia' => $referencia,
        'estado' => 'registrado',
        'created_by' => 1,
    ]);
}

function apply(int $pagoId, int $cargoId, float $valor): void
{
    ins('pago_aplicaciones', [
        'pago_id' => $pagoId,
        'cargo_id' => $cargoId,
        'valor' => $valor,
        'estado' => 'activa',
    ]);
}

function receipt(int $pagoId, string $numero, float $valor, string $fecha): void
{
    ins('comprobantes', [
        'pago_id' => $pagoId,
        'numero' => $numero,
        'fecha' => $fecha,
        'valor' => $valor,
        'estado' => 'vigente',
    ]);
}

function alert(int $estudianteId, ?int $inscripcionId, string $tipo, string $nivel, string $mensaje): void
{
    ins('alertas', [
        'estudiante_id' => $estudianteId,
        'inscripcion_id' => $inscripcionId,
        'tipo' => $tipo,
        'nivel' => $nivel,
        'mensaje' => $mensaje,
        'estado' => 'nueva',
    ]);
}

// ------------------------------------------------------------------
// Limpieza opcional
// ------------------------------------------------------------------
if ($fresh) {
    Database::execute('SET FOREIGN_KEY_CHECKS = 0');
    $tables = [
        'asistencias', 'excusas', 'sesiones', 'notas', 'evaluaciones',
        'inscripcion_movimientos', 'alertas', 'inscripciones',
        'pago_aplicaciones', 'comprobantes', 'pagos', 'cargos', 'matriculas',
        'grupo_horarios', 'grupos', 'plan_estudios', 'prerrequisitos',
        'cursos', 'programas', 'aulas', 'acudientes', 'estudiantes',
        'docentes', 'auditoria', 'intentos_login',
    ];

    foreach ($tables as $table) {
        Database::execute('DELETE FROM `' . $table . '`');
    }

    Database::execute("DELETE FROM usuarios WHERE username <> 'admin'");
    Database::execute("DELETE FROM personas WHERE numero_documento <> '1000000000'");
    Database::execute('SET FOREIGN_KEY_CHECKS = 1');
}

// ------------------------------------------------------------------
// Oferta académica
// ------------------------------------------------------------------
$programa1 = ins('programas', [
    'codigo' => 'TEC-SIS', 'nombre' => 'Técnico en Sistemas', 'duracion_periodos' => 4, 'estado' => 'activo', 'created_by' => 1,
]);
$programa2 = ins('programas', [
    'codigo' => 'TEC-CON', 'nombre' => 'Técnico en Contabilidad', 'duracion_periodos' => 4, 'estado' => 'activo', 'created_by' => 1,
]);

$cursos = [];
foreach ([
    ['MAT101', 'Matemáticas I'],
    ['INF101', 'Fundamentos de Programación'],
    ['CON101', 'Contabilidad Básica'],
    ['ING101', 'Inglés I'],
    ['ETI101', 'Ética Profesional'],
] as [$codigo, $nombre]) {
    $cursos[$codigo] = ins('cursos', [
        'codigo' => $codigo, 'nombre' => $nombre, 'estado' => 'activo', 'created_by' => 1,
    ]);
}

foreach ([[$programa1, 'MAT101', 1], [$programa1, 'INF101', 1], [$programa1, 'ING101', 1], [$programa1, 'ETI101', 2],
          [$programa2, 'CON101', 1], [$programa2, 'MAT101', 1], [$programa2, 'ING101', 1], [$programa2, 'ETI101', 2]] as [$programa, $curso, $nivel]) {
    ins('plan_estudios', [
        'programa_id' => $programa, 'curso_id' => $cursos[$curso], 'nivel' => $nivel,
        'obligatorio' => 1, 'estado' => 'activo', 'created_by' => 1,
    ]);
}

$periodo1 = ins('periodos', [
    'codigo' => '2026-1', 'nombre' => 'Periodo 2026-1',
    'fecha_inicio' => '2026-02-01', 'fecha_fin' => '2026-06-15',
    'matricula_inicio' => '2026-01-05', 'matricula_fin' => '2026-02-10',
    'estado' => 'cerrado', 'created_by' => 1,
]);
$periodo2 = ins('periodos', [
    'codigo' => '2027-1', 'nombre' => 'Periodo 2027-1',
    'fecha_inicio' => '2027-01-20', 'fecha_fin' => '2027-05-30',
    'matricula_inicio' => '2026-10-01', 'matricula_fin' => '2026-12-15',
    'estado' => 'matricula_abierta', 'created_by' => 1,
]);

$aulas = [];
foreach ([
    [1, 'A-101', 30], [1, 'A-102', 25], [1, 'LAB-1', 20],
    [2, 'B-201', 30], [2, 'B-202', 25],
] as [$sede, $nombre, $cap]) {
    $aulas[$sede][$nombre] = ins('aulas', [
        'sede_id' => $sede, 'nombre' => $nombre, 'capacidad' => $cap, 'estado' => 'activo',
    ]);
}

// ------------------------------------------------------------------
// Docentes
// ------------------------------------------------------------------
$docentes = [];
$docentesData = [
    [1, '2000000001', 'Carlos', 'Pérez', 'DOC-00001', 'Ingeniero de Sistemas', 'planta'],
    [1, '2000000002', 'María', 'Gómez', 'DOC-00002', 'Licenciada en Idiomas', 'catedra'],
    [2, '2000000003', 'José', 'Rodríguez', 'DOC-00003', 'Contador Público', 'contrato'],
];
foreach ($docentesData as [$sede, $doc, $nombres, $apellidos, $codigo, $titulo, $vinculacion]) {
    $personaId = person(3, $doc, $nombres, $apellidos, strtolower($nombres) . '.doc@futurodigital.edu', '300111' . substr($doc, -4), '1985-05-10');
    $docenteId = teacher($personaId, $sede, $codigo, $titulo, $vinculacion);
    user($personaId, 6, $sede, $doc, $demoHash);
    $docentes[$codigo] = $docenteId;
}

// ------------------------------------------------------------------
// Estudiantes
// ------------------------------------------------------------------
$estudiantes = [];
$estudiantesData = [
    [1, '3000000001', 'Ana', 'Torres', '2010-03-12'],
    [1, '3000000002', 'Luis', 'Martínez', '2010-07-22'],
    [1, '3000000003', 'Sofía', 'Ramírez', '2010-11-02'],
    [1, '3000000004', 'Diego', 'Herrera', '2010-01-18'],
    [1, '3000000005', 'Valentina', 'Cruz', '2010-09-30'],
    [2, '3000000006', 'Camila', 'López', '2010-04-25'],
    [2, '3000000007', 'Andrés', 'Pérez', '2010-08-14'],
    [2, '3000000008', 'Isabella', 'Díaz', '2010-12-05'],
];
$i = 0;
foreach ($estudiantesData as [$sede, $doc, $nombres, $apellidos, $nacimiento]) {
    $i++;
    $personaId = person(2, $doc, $nombres, $apellidos, strtolower($nombres) . '.est@futurodigital.edu', '310111' . substr($doc, -4), $nacimiento);
    $estudiantes[$doc] = student($personaId, $sede, sprintf('EST-%05d', $i));
}

// Un usuario de portal para la primera estudiante.
user(
    (int) Database::scalar("SELECT id FROM personas WHERE numero_documento = '3000000001'"),
    7,
    1,
    '3000000001',
    $demoHash
);

// ------------------------------------------------------------------
// Usuarios administrativos
// ------------------------------------------------------------------
$staff = [
    [2, 1, '4000000001', 'Laura', 'Secretaría', 'Secretaría Sincelejo'],
    [2, 2, '4000000002', 'Pedro', 'Secretaría', 'Secretaría Corozal'],
    [3, null, '4000000003', 'Claudia', 'Coordinación', 'Coordinación Académica'],
    [4, null, '4000000004', 'Jorge', 'Administración', 'Coordinación Administrativa'],
    [5, null, '4000000005', 'Elena', 'Dirección', 'Dirección General'],
];
foreach ($staff as [$rol, $sede, $doc, $nombres, $apellidos, $cargo]) {
    $personaId = person(3, $doc, $nombres, $apellidos, strtolower($nombres) . '.staff@futurodigital.edu', '320111' . substr($doc, -4), '1980-02-20');
    user($personaId, $rol, $sede, $doc, $demoHash);
    $passwords[$doc] = $cargo;
}

// ------------------------------------------------------------------
// Grupos
// ------------------------------------------------------------------
$g = [];
$g['MAT-P1-SIN'] = group($cursos['MAT101'], $docentes['DOC-00001'], $periodo1, 1, $aulas[1]['A-101'], 'GRP-2026-00001', 30);
$g['INF-P1-SIN'] = group($cursos['INF101'], $docentes['DOC-00001'], $periodo1, 1, $aulas[1]['LAB-1'], 'GRP-2026-00002', 20);
$g['ING-P1-SIN'] = group($cursos['ING101'], $docentes['DOC-00002'], $periodo1, 1, $aulas[1]['A-102'], 'GRP-2026-00003', 25);
$g['CON-P1-COR'] = group($cursos['CON101'], $docentes['DOC-00003'], $periodo1, 2, $aulas[2]['B-201'], 'GRP-2026-00004', 30);
$g['MAT-P1-COR'] = group($cursos['MAT101'], $docentes['DOC-00003'], $periodo1, 2, $aulas[2]['B-202'], 'GRP-2026-00005', 25);
$g['MAT-P2-SIN'] = group($cursos['MAT101'], $docentes['DOC-00001'], $periodo2, 1, $aulas[1]['A-101'], 'GRP-2027-00001', 30);
$g['INF-P2-SIN'] = group($cursos['INF101'], $docentes['DOC-00002'], $periodo2, 1, $aulas[1]['LAB-1'], 'GRP-2027-00002', 20);
$g['CON-P2-COR'] = group($cursos['CON101'], $docentes['DOC-00003'], $periodo2, 2, $aulas[2]['B-201'], 'GRP-2027-00003', 30);

schedule($g['MAT-P1-SIN'], 1, '07:00:00', '09:00:00');
schedule($g['MAT-P1-SIN'], 3, '07:00:00', '09:00:00');
schedule($g['INF-P1-SIN'], 2, '09:00:00', '11:00:00');
schedule($g['INF-P1-SIN'], 4, '09:00:00', '11:00:00');
schedule($g['ING-P1-SIN'], 1, '11:00:00', '13:00:00');
schedule($g['ING-P1-SIN'], 5, '11:00:00', '13:00:00');
schedule($g['CON-P1-COR'], 2, '07:00:00', '09:00:00');
schedule($g['CON-P1-COR'], 4, '07:00:00', '09:00:00');
schedule($g['MAT-P1-COR'], 1, '09:00:00', '11:00:00');
schedule($g['MAT-P1-COR'], 3, '09:00:00', '11:00:00');
schedule($g['MAT-P2-SIN'], 1, '07:00:00', '09:00:00');
schedule($g['MAT-P2-SIN'], 3, '07:00:00', '09:00:00');
schedule($g['INF-P2-SIN'], 2, '09:00:00', '11:00:00');
schedule($g['INF-P2-SIN'], 4, '09:00:00', '11:00:00');
schedule($g['CON-P2-COR'], 2, '07:00:00', '09:00:00');
schedule($g['CON-P2-COR'], 4, '07:00:00', '09:00:00');

// ------------------------------------------------------------------
// Matrículas e inscripciones - Periodo 2026-1
// ------------------------------------------------------------------
$matP1 = [];
$n = 0;
foreach ($estudiantesData as [$sede, $doc]) {
    $n++;
    $programa = $sede === 1 ? $programa1 : $programa2;
    $matP1[$doc] = matricula(sprintf('MAT-2026-%05d', $n), $estudiantes[$doc], $programa, $periodo1, $sede);
}

$insc = [];
$enroll = [
    '3000000001' => ['MAT-P1-SIN', 'INF-P1-SIN', 'ING-P1-SIN'],
    '3000000002' => ['MAT-P1-SIN', 'INF-P1-SIN'],
    '3000000003' => ['MAT-P1-SIN', 'ING-P1-SIN'],
    '3000000004' => ['INF-P1-SIN', 'ING-P1-SIN'],
    '3000000005' => ['MAT-P1-SIN', 'INF-P1-SIN', 'ING-P1-SIN'],
    '3000000006' => ['CON-P1-COR', 'MAT-P1-COR'],
    '3000000007' => ['CON-P1-COR', 'MAT-P1-COR'],
    '3000000008' => ['CON-P1-COR'],
];
foreach ($enroll as $doc => $grupos) {
    foreach ($grupos as $grupoKey) {
        $insc[$doc][$grupoKey] = inscription($matP1[$doc], $g[$grupoKey]);
        movement($insc[$doc][$grupoKey], 'inscripcion', null, $g[$grupoKey], 'Inscripción inicial de demostración.');
    }
}

// Matrículas del periodo 2027-1 (matrícula abierta)
$matP2 = [];
$matP2['3000000001'] = matricula('MAT-2027-00001', $estudiantes['3000000001'], $programa1, $periodo2, 1);
$matP2['3000000006'] = matricula('MAT-2027-00002', $estudiantes['3000000006'], $programa2, $periodo2, 2);
$inscP2 = [];
$inscP2['3000000001'] = inscription($matP2['3000000001'], $g['MAT-P2-SIN']);
$inscP2['3000000006'] = inscription($matP2['3000000006'], $g['CON-P2-COR']);

// ------------------------------------------------------------------
// Evaluaciones y notas - Periodo 2026-1
// ------------------------------------------------------------------
$evaluaciones = [];
foreach ($g as $key => $grupoId) {
    if (!str_contains($key, 'P1')) {
        continue;
    }
    $evaluaciones[$key] = [
        'Parcial 1' => evaluation($grupoId, 'Parcial 1', 30.0, '2026-03-10'),
        'Parcial 2' => evaluation($grupoId, 'Parcial 2', 30.0, '2026-04-14'),
        'Examen final' => evaluation($grupoId, 'Examen final', 40.0, '2026-05-26'),
    ];
}

$notas = [
    '3000000001' => [4.2, 4.5, 4.6],
    '3000000002' => [3.5, 3.8, 3.9],
    '3000000003' => [4.8, 4.7, 4.9],
    '3000000004' => [2.2, 2.5, 2.0],
    '3000000005' => [3.8, 4.0, 3.7],
    '3000000006' => [4.0, 4.1, 4.3],
    '3000000007' => [3.2, 3.0, 3.1],
    '3000000008' => [4.5, 4.6, 4.7],
];

foreach ($insc as $doc => $grupos) {
    foreach ($grupos as $grupoKey => $inscripcionId) {
        $vals = $notas[$doc];
        $idx = 0;
        foreach ($evaluaciones[$grupoKey] as $evalId) {
            $nota = $vals[$idx % count($vals)];
            note($evalId, $inscripcionId, $nota, $nota < 3.0 ? 'Requiere refuerzo.' : null);
            $idx++;
        }
    }
}

// ------------------------------------------------------------------
// Sesiones y asistencia - Periodo 2026-1
// ------------------------------------------------------------------
$fechas = ['2026-03-02', '2026-03-09', '2026-03-16', '2026-03-23'];

// Las sesiones son por grupo: se crean una sola vez.
$sessionIds = [];
$groupKeys = [];
foreach ($insc as $grupos) {
    foreach (array_keys($grupos) as $grupoKey) {
        $groupKeys[$grupoKey] = true;
    }
}
foreach (array_keys($groupKeys) as $grupoKey) {
    foreach ($fechas as $k => $fecha) {
        $sessionIds[$grupoKey][$k] = session($g[$grupoKey], $fecha, '07:00:00', '09:00:00', 'Sesión ' . ($k + 1));
    }
}

foreach ($insc as $doc => $grupos) {
    foreach ($grupos as $grupoKey => $inscripcionId) {
        foreach ($fechas as $k => $fecha) {
            $sesionId = $sessionIds[$grupoKey][$k];
            // Valentina acumula inasistencias; el resto asiste.
            if ($doc === '3000000005') {
                $estado = $k < 2 ? 'ausente' : 'presente';
                attendance($sesionId, $inscripcionId, $estado, $estado === 'ausente' ? 'Sin justificación.' : null);
            } elseif ($doc === '3000000003' && $k === 0) {
                attendance($sesionId, $inscripcionId, 'excusa', 'Cita médica.');
            } elseif ($doc === '3000000002' && $k === 1) {
                attendance($sesionId, $inscripcionId, 'tarde', 'Ingresó 15 minutos tarde.');
            } else {
                attendance($sesionId, $inscripcionId, 'presente');
            }
        }
    }
}

// ------------------------------------------------------------------
// Cargos, pagos y comprobantes
// ------------------------------------------------------------------
$cargos = [];
$n = 0;
foreach ($matP1 as $doc => $matriculaId) {
    $n++;
    $estudianteId = $estudiantes[$doc];
    $cargos[$doc]['MAT'] = charge($estudianteId, $matriculaId, 1, 'Matrícula periodo 2026-1', 350000.00, '2026-02-15');
    $cargos[$doc]['COL'] = charge($estudianteId, $matriculaId, 2, 'Colegiatura periodo 2026-1', 450000.00, '2026-03-15');
}

// Ana: matrícula pagada y colegiatura abonada.
$pago1 = payment($estudiantes['3000000001'], 350000.00, 'efectivo', 'CAJA-001', '2026-02-05');
apply($pago1, $cargos['3000000001']['MAT'], 350000.00);
receipt($pago1, 'REC-2026-000001', 350000.00, '2026-02-05');
Database::update('cargos', ['estado' => 'pagado'], ['id' => $cargos['3000000001']['MAT']]);

$pago2 = payment($estudiantes['3000000001'], 200000.00, 'transferencia', 'TRF-88231', '2026-03-10');
apply($pago2, $cargos['3000000001']['COL'], 200000.00);
receipt($pago2, 'REC-2026-000002', 200000.00, '2026-03-10');

// Luis: matrícula pagada.
$pago3 = payment($estudiantes['3000000002'], 350000.00, 'tarjeta', 'POS-5512', '2026-02-08');
apply($pago3, $cargos['3000000002']['MAT'], 350000.00);
receipt($pago3, 'REC-2026-000003', 350000.00, '2026-02-08');
Database::update('cargos', ['estado' => 'pagado'], ['id' => $cargos['3000000002']['MAT']]);

// Camila: matrícula pendiente (vencida) para demostrar mora.
// El resto queda pendiente.

// ------------------------------------------------------------------
// Alertas y acudientes
// ------------------------------------------------------------------
alert($estudiantes['3000000004'], $insc['3000000004']['INF-P1-SIN'] ?? null, 'bajo_rendimiento', 'advertencia', 'Promedio parcial inferior a 3.0 en dos evaluaciones.');
alert($estudiantes['3000000005'], $insc['3000000005']['MAT-P1-SIN'] ?? null, 'inasistencia', 'critica', 'Inasistencia acumulada del 50% en el grupo.');
alert($estudiantes['3000000006'], $inscP2['3000000006'] ?? null, 'mora', 'critica', 'Cargo de matrícula vencido sin pago registrado.');

ins('acudientes', [
    'estudiante_id' => $estudiantes['3000000001'], 'nombre' => 'Marta Torres', 'documento' => '5000000001',
    'telefono' => '3005550001', 'parentesco' => 'Madre', 'estado' => 'activo',
]);
ins('acudientes', [
    'estudiante_id' => $estudiantes['3000000002'], 'nombre' => 'Pedro Martínez', 'documento' => '5000000002',
    'telefono' => '3005550002', 'parentesco' => 'Padre', 'estado' => 'activo',
]);

// ------------------------------------------------------------------
// Resumen
// ------------------------------------------------------------------
$tables = ['programas', 'cursos', 'periodos', 'aulas', 'docentes', 'estudiantes', 'grupos', 'matriculas', 'inscripciones', 'evaluaciones', 'notas', 'sesiones', 'asistencias', 'cargos', 'pagos', 'comprobantes', 'alertas'];
fwrite(STDOUT, "Datos demo cargados correctamente.\n");
foreach ($tables as $table) {
    $count = Database::scalar('SELECT COUNT(*) FROM `' . $table . '`');
    fwrite(STDOUT, sprintf("  %-18s %s\n", $table, $count));
}
fwrite(STDOUT, "\nUsuarios demo (contraseña: Demo123*):\n");
fwrite(STDOUT, "  2000000001  Docente Carlos Pérez (Sincelejo)\n");
fwrite(STDOUT, "  2000000002  Docente María Gómez (Sincelejo)\n");
fwrite(STDOUT, "  2000000003  Docente José Rodríguez (Corozal)\n");
fwrite(STDOUT, "  3000000001  Estudiante Ana Torres\n");
fwrite(STDOUT, "  4000000001  Secretaría Sincelejo\n");
fwrite(STDOUT, "  4000000002  Secretaría Corozal\n");
fwrite(STDOUT, "  4000000003  Coordinación Académica\n");
fwrite(STDOUT, "  4000000004  Coordinación Administrativa\n");
fwrite(STDOUT, "  4000000005  Dirección General\n");
fwrite(STDOUT, "  admin       Administrador (contraseña de fábrica Admin123*, cambio obligatorio)\n");
