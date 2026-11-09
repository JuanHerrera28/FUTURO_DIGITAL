<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\DomainException;
use App\Core\Param;

final class EnrollmentService
{
    /**
     * Matricula al estudiante en un programa y periodo, genera el cargo de
     * matrícula y crea el usuario del estudiante en su primera matrícula.
     *
     * @return array{matricula_id:int,codigo:string,credentials:array<string,mixed>|null}
     */
    public static function createEnrollment(int $estudianteId, int $programaId, int $periodoId): array
    {
        return Database::transaction(static function () use ($estudianteId, $programaId, $periodoId): array {
            $student = Database::fetch(
                'SELECT e.*, p.nombres, p.apellidos, p.numero_documento, p.email
                 FROM estudiantes e JOIN personas p ON p.id = e.persona_id
                 WHERE e.id = ? FOR UPDATE',
                [$estudianteId]
            );

            if ($student === null) {
                throw new DomainException('RF-09', 'El estudiante no existe.');
            }

            if ((string) $student['estado'] !== 'activo') {
                throw new DomainException('RF-09', 'Solo se pueden matricular estudiantes activos.');
            }

            if (!Auth::canAccessSede((int) $student['sede_id'])) {
                throw new DomainException('RN-19', 'Solo puede matricular estudiantes de su sede.');
            }

            $period = Database::fetch('SELECT * FROM periodos WHERE id = ? FOR UPDATE', [$periodoId]);
            if ($period === null) {
                throw new DomainException('RF-09', 'El periodo académico no existe.');
            }

            if ((string) $period['estado'] !== 'matricula_abierta') {
                throw new DomainException('RN-09', 'La matrícula del periodo ' . $period['nombre'] . ' no está abierta.');
            }

            $today = date('Y-m-d');
            if ($today < (string) $period['matricula_inicio'] || $today > (string) $period['matricula_fin']) {
                throw new DomainException('RN-09', 'La fecha actual está fuera del calendario de matrícula del periodo ' . $period['nombre'] . '.');
            }

            $program = Database::fetch('SELECT * FROM programas WHERE id = ?', [$programaId]);
            if ($program === null || (string) $program['estado'] !== 'activo') {
                throw new DomainException('RF-09', 'El programa seleccionado no existe o está inactivo.');
            }

            if (PaymentService::hasOverdueDebt($estudianteId)) {
                throw new DomainException('RN-16', 'El estudiante tiene saldo vencido más allá del plazo permitido. No puede matricularse hasta ponerse al día.');
            }

            $existing = Database::fetch(
                'SELECT id FROM matriculas WHERE estudiante_id = ? AND programa_id = ? AND periodo_id = ?',
                [$estudianteId, $programaId, $periodoId]
            );

            if ($existing !== null) {
                throw new DomainException('RF-09', 'El estudiante ya tiene una matrícula en ese programa y periodo.');
            }

            $matriculaId = Database::insert('matriculas', [
                'codigo' => 'TMP-' . bin2hex(random_bytes(6)),
                'estudiante_id' => $estudianteId,
                'programa_id' => $programaId,
                'periodo_id' => $periodoId,
                'sede_id' => (int) $student['sede_id'],
                'fecha' => date('Y-m-d H:i:s'),
                'estado' => 'activa',
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $codigo = CodeService::assign('matriculas', $matriculaId, 'MAT', true, 5);

            PaymentService::createEnrollmentCharge(
                $estudianteId,
                $matriculaId,
                $programaId,
                $periodoId,
                (string) $program['nombre'],
                (string) $period['nombre']
            );

            $credentials = null;
            $existingUser = Database::scalar(
                'SELECT COUNT(*) FROM usuarios WHERE persona_id = ? AND rol_id = 7',
                [(int) $student['persona_id']]
            );

            if ((int) $existingUser === 0) {
                $credentials = UserService::createForPersona(
                    (int) $student['persona_id'],
                    7,
                    (int) $student['sede_id'],
                    (string) $student['numero_documento'],
                    'Credenciales del estudiante generadas en su primera matrícula. La contraseña es temporal.'
                );
            }

            Audit::log('matricula_creada', 'matriculas', 'matriculas', $matriculaId, null, [
                'estudiante_id' => $estudianteId,
                'programa_id' => $programaId,
                'periodo_id' => $periodoId,
                'codigo' => $codigo,
            ]);

            return ['matricula_id' => $matriculaId, 'codigo' => $codigo, 'credentials' => $credentials];
        });
    }

    /**
     * Inscribe una matrícula en un grupo aplicando RN-02 a RN-10.
     *
     * @return array{inscripcion_id:int,grupo:string}
     */
    public static function enroll(int $matriculaId, int $grupoId): array
    {
        return Database::transaction(static function () use ($matriculaId, $grupoId): array {
            $result = self::join($matriculaId, $grupoId, null);

            Audit::log('inscripcion_creada', 'inscripciones', 'inscripciones', $result['inscripcion_id'], null, [
                'matricula_id' => $matriculaId,
                'grupo_id' => $grupoId,
            ]);

            return $result;
        });
    }

    /**
     * Cambia de grupo conservando el historial de la inscripción anterior.
     *
     * @return array{inscripcion_id:int,grupo:string}
     */
    public static function changeGroup(int $inscripcionId, int $nuevoGrupoId, string $motivo): array
    {
        return Database::transaction(static function () use ($inscripcionId, $nuevoGrupoId, $motivo): array {
            $current = self::findInscription($inscripcionId);

            if ($current === null) {
                throw new DomainException('RF-11', 'La inscripción no existe.');
            }

            if ((string) $current['estado'] !== 'activa') {
                throw new DomainException('RF-11', 'Solo se pueden cambiar inscripciones activas.');
            }

            if ((int) $current['grupo_id'] === $nuevoGrupoId) {
                throw new DomainException('RF-11', 'El grupo de destino debe ser diferente al grupo actual.');
            }

            $matricula = self::findMatricula((int) $current['matricula_id']);
            if ($matricula === null) {
                throw new DomainException('RF-11', 'La matrícula no existe.');
            }

            PeriodService::assertEnrollmentOpen((int) $matricula['periodo_id']);

            $new = self::join((int) $current['matricula_id'], $nuevoGrupoId, $inscripcionId);

            Database::update('inscripciones', [
                'estado' => 'cancelada',
                'fecha_cancelacion' => date('Y-m-d H:i:s'),
                'updated_by' => Auth::id(),
            ], ['id' => $inscripcionId]);

            Database::insert('inscripcion_movimientos', [
                'inscripcion_id' => $new['inscripcion_id'],
                'tipo' => 'cambio_grupo',
                'grupo_origen_id' => (int) $current['grupo_id'],
                'grupo_destino_id' => $nuevoGrupoId,
                'motivo' => substr(trim($motivo), 0, 255),
                'usuario_id' => Auth::id(),
            ]);

            Audit::log('cambio_grupo', 'inscripciones', 'inscripciones', $new['inscripcion_id'], [
                'grupo_id' => $current['grupo_id'],
            ], [
                'grupo_id' => $nuevoGrupoId,
                'motivo' => $motivo,
            ]);

            return $new;
        });
    }

    public static function cancel(int $inscripcionId, string $motivo): void
    {
        Database::transaction(static function () use ($inscripcionId, $motivo): void {
            $inscription = self::findInscription($inscripcionId);

            if ($inscription === null) {
                throw new DomainException('RF-11', 'La inscripción no existe.');
            }

            if ((string) $inscription['estado'] !== 'activa') {
                throw new DomainException('RF-11', 'Solo se pueden cancelar inscripciones activas.');
            }

            if (!Auth::canAccessSede((int) $inscription['sede_id'])) {
                throw new DomainException('RN-19', 'Solo puede cancelar inscripciones de su sede.');
            }

            $deadline = date('Y-m-d', strtotime((string) $inscription['periodo_inicio'] . ' +' . max(0, Param::int('plazo_cancelacion_dias', 15)) . ' days'));

            if (date('Y-m-d') > $deadline) {
                throw new DomainException('RN-11', 'El plazo para cancelar venció el ' . fecha($deadline) . ' (' . Param::int('plazo_cancelacion_dias', 15) . ' días desde el inicio del periodo).');
            }

            Database::update('inscripciones', [
                'estado' => 'cancelada',
                'fecha_cancelacion' => date('Y-m-d H:i:s'),
                'updated_by' => Auth::id(),
            ], ['id' => $inscripcionId]);

            Database::insert('inscripcion_movimientos', [
                'inscripcion_id' => $inscripcionId,
                'tipo' => 'cancelacion',
                'grupo_origen_id' => (int) $inscription['grupo_id'],
                'motivo' => substr(trim($motivo), 0, 255),
                'usuario_id' => Auth::id(),
            ]);

            Audit::log('inscripcion_cancelada', 'inscripciones', 'inscripciones', $inscripcionId, ['estado' => 'activa'], [
                'estado' => 'cancelada',
                'motivo' => $motivo,
            ]);
        });
    }

    /**
     * Valida todas las reglas y crea (o reactiva) la inscripción.
     *
     * @return array{inscripcion_id:int,grupo:string}
     */
    private static function join(int $matriculaId, int $grupoId, ?int $ignoreInscripcionId): array
    {
        $group = Database::fetch(
            "SELECT g.*, c.nombre AS curso, c.codigo AS curso_codigo,
                    p.estado AS periodo_estado, p.nombre AS periodo,
                    (SELECT COUNT(*) FROM inscripciones i WHERE i.grupo_id = g.id AND i.estado = 'activa') AS inscritos
             FROM grupos g
             JOIN cursos c ON c.id = g.curso_id
             JOIN periodos p ON p.id = g.periodo_id
             WHERE g.id = ?
             FOR UPDATE",
            [$grupoId]
        );

        if ($group === null) {
            throw new DomainException('RF-10', 'El grupo no existe.');
        }

        $matricula = Database::fetch(
            'SELECT m.*, p.nombres, p.apellidos, p.numero_documento, e.persona_id
             FROM matriculas m
             JOIN estudiantes e ON e.id = m.estudiante_id
             JOIN personas p ON p.id = e.persona_id
             WHERE m.id = ? FOR UPDATE',
            [$matriculaId]
        );

        if ($matricula === null) {
            throw new DomainException('RF-10', 'La matrícula no existe.');
        }

        if ((string) $matricula['estado'] !== 'activa') {
            throw new DomainException('RF-10', 'La matrícula no está activa.');
        }

        if (!Auth::canAccessSede((int) $matricula['sede_id'])) {
            throw new DomainException('RN-19', 'Solo puede inscribir matrículas de su sede.');
        }

        if ((string) $group['estado'] !== 'activo') {
            throw new DomainException('RF-10', 'El grupo no está activo.');
        }

        if ((string) $group['periodo_estado'] === 'cerrado') {
            throw new DomainException('RF-22', 'El periodo del grupo está cerrado.');
        }

        if ((int) $group['periodo_id'] !== (int) $matricula['periodo_id']) {
            throw new DomainException('RF-10', 'El grupo pertenece a un periodo diferente al de la matrícula.');
        }

        if ((int) $group['sede_id'] !== (int) $matricula['sede_id']) {
            throw new DomainException('RN-19', 'Solo puede inscribir en grupos de la misma sede de la matrícula.');
        }

        PeriodService::assertEnrollmentOpen((int) $matricula['periodo_id']);

        ProgramService::assertCourseInPlan((int) $matricula['programa_id'], (int) $group['curso_id']);

        self::assertPrerequisites((int) $matricula['estudiante_id'], (int) $group['curso_id']);
        self::assertNotApproved((int) $matricula['estudiante_id'], (int) $group['curso_id']);

        $activeSameCourse = Database::fetch(
            "SELECT i.id, g.codigo
             FROM inscripciones i
             JOIN grupos g ON g.id = i.grupo_id
             WHERE i.matricula_id = ?
               AND g.curso_id = ?
               AND i.estado = 'activa'
               AND (? IS NULL OR i.id <> ?)
             LIMIT 1",
            [$matriculaId, (int) $group['curso_id'], $ignoreInscripcionId, $ignoreInscripcionId ?? 0]
        );

        if ($activeSameCourse !== null) {
            throw new DomainException('RN-08', 'El estudiante ya está inscrito en el curso ' . $group['curso'] . ' en el grupo ' . $activeSameCourse['codigo'] . ' durante este periodo.');
        }

        $maxCourses = max(1, Param::int('max_cursos_periodo', 7));
        $activeCount = (int) Database::scalar(
            "SELECT COUNT(*) FROM inscripciones i
             JOIN grupos g ON g.id = i.grupo_id
             WHERE i.matricula_id = ? AND i.estado = 'activa' AND g.periodo_id = ?
               AND (? IS NULL OR i.id <> ?)",
            [$matriculaId, (int) $matricula['periodo_id'], $ignoreInscripcionId, $ignoreInscripcionId ?? 0]
        );

        if ($activeCount >= $maxCourses) {
            throw new DomainException('RN-10', 'Se alcanzó el límite de ' . $maxCourses . ' cursos activos por periodo.');
        }

        if ((int) $group['inscritos'] >= (int) $group['capacidad_max']) {
            throw new DomainException('RN-03', 'El grupo ' . $group['codigo'] . ' no tiene cupo disponible (' . $group['inscritos'] . '/' . $group['capacidad_max'] . ').');
        }

        $blocks = GroupService::schedules($grupoId);
        if ($blocks !== []) {
            $normalized = array_map(static fn (array $block): array => [
                'dia_semana' => (int) $block['dia_semana'],
                'hora_inicio' => (string) $block['hora_inicio'],
                'hora_fin' => (string) $block['hora_fin'],
            ], $blocks);

            ScheduleService::assertStudentFree($matriculaId, (int) $matricula['periodo_id'], $normalized, $grupoId);
        }

        $existing = Database::fetch(
            'SELECT * FROM inscripciones WHERE matricula_id = ? AND grupo_id = ?',
            [$matriculaId, $grupoId]
        );

        if ($existing !== null) {
            if ((string) $existing['estado'] === 'activa') {
                throw new DomainException('RN-04', 'El estudiante ya está inscrito en el grupo ' . $group['codigo'] . '.');
            }

            if (in_array((string) $existing['estado'], ['finalizada', 'perdida_inasistencia'], true)) {
                throw new DomainException('RF-10', 'La inscripción en ese grupo ya finalizó y no puede reactivarse.');
            }

            Database::update('inscripciones', [
                'estado' => 'activa',
                'fecha_cancelacion' => null,
                'updated_by' => Auth::id(),
            ], ['id' => (int) $existing['id']]);

            Database::insert('inscripcion_movimientos', [
                'inscripcion_id' => (int) $existing['id'],
                'tipo' => 'reactivacion',
                'grupo_destino_id' => $grupoId,
                'motivo' => 'Reactivación de inscripción',
                'usuario_id' => Auth::id(),
            ]);

            return ['inscripcion_id' => (int) $existing['id'], 'grupo' => (string) $group['codigo']];
        }

        $inscripcionId = Database::insert('inscripciones', [
            'matricula_id' => $matriculaId,
            'grupo_id' => $grupoId,
            'estado' => 'activa',
            'fecha_inscripcion' => date('Y-m-d H:i:s'),
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        Database::insert('inscripcion_movimientos', [
            'inscripcion_id' => $inscripcionId,
            'tipo' => 'inscripcion',
            'grupo_destino_id' => $grupoId,
            'motivo' => 'Inscripción inicial',
            'usuario_id' => Auth::id(),
        ]);

        return ['inscripcion_id' => $inscripcionId, 'grupo' => (string) $group['codigo']];
    }

    private static function assertPrerequisites(int $estudianteId, int $cursoId): void
    {
        $minimum = Param::float('nota_minima', 3.0);

        foreach (ProgramService::activePrerequisites($cursoId) as $prerequisite) {
            $condition = $prerequisite['tipo'] === 'aprobado'
                ? 'AND i.nota_final IS NOT NULL AND i.nota_final >= ?'
                : '';

            $params = [$estudianteId, (int) $prerequisite['curso_requisito_id']];
            if ($prerequisite['tipo'] === 'aprobado') {
                $params[] = $minimum;
            }

            $satisfied = (int) Database::scalar(
                "SELECT COUNT(*)
                 FROM inscripciones i
                 JOIN grupos g ON g.id = i.grupo_id
                 JOIN matriculas m ON m.id = i.matricula_id
                 WHERE m.estudiante_id = ?
                   AND g.curso_id = ?
                   AND i.estado IN ('activa','finalizada','perdida_inasistencia')
                   {$condition}",
                $params
            );

            if ($satisfied === 0) {
                throw new DomainException(
                    'RN-02',
                    'No cumple el prerrequisito ' . $prerequisite['requisito'] . ' (' . $prerequisite['requisito_codigo'] . '). Se exige ' . $prerequisite['tipo'] . '.'
                );
            }
        }
    }

    private static function assertNotApproved(int $estudianteId, int $cursoId): void
    {
        $minimum = Param::float('nota_minima', 3.0);

        $approved = Database::fetch(
            "SELECT i.id, g.codigo, i.nota_final
             FROM inscripciones i
             JOIN grupos g ON g.id = i.grupo_id
             JOIN matriculas m ON m.id = i.matricula_id
             WHERE m.estudiante_id = ?
               AND g.curso_id = ?
               AND i.estado = 'finalizada'
               AND i.nota_final IS NOT NULL
               AND i.nota_final >= ?
             LIMIT 1",
            [$estudianteId, $cursoId, $minimum]
        );

        if ($approved !== null) {
            throw new DomainException('RN-08', 'El curso ya fue aprobado con nota ' . number_format((float) $approved['nota_final'], 1, ',', '.') . '. No se puede repetir.');
        }
    }

    /** @return array<string,mixed>|null */
    public static function findMatricula(int $id): ?array
    {
        return Database::fetch(
            'SELECT m.*, pr.nombre AS programa, pr.codigo AS programa_codigo,
                    p.nombre AS periodo, p.estado AS periodo_estado, p.fecha_inicio, p.fecha_fin,
                    s.nombre AS sede, e.codigo AS estudiante_codigo,
                    per.nombres, per.apellidos, per.numero_documento, per.email, per.telefono
             FROM matriculas m
             JOIN programas pr ON pr.id = m.programa_id
             JOIN periodos p ON p.id = m.periodo_id
             JOIN sedes s ON s.id = m.sede_id
             JOIN estudiantes e ON e.id = m.estudiante_id
             JOIN personas per ON per.id = e.persona_id
             WHERE m.id = ?',
            [$id]
        );
    }

    /** @return array<string,mixed>|null */
    public static function findInscription(int $id): ?array
    {
        return Database::fetch(
            "SELECT i.*, g.codigo AS grupo, g.curso_id, g.periodo_id, g.sede_id, g.docente_id,
                    c.nombre AS curso, c.codigo AS curso_codigo,
                    m.estudiante_id, m.programa_id, m.codigo AS matricula,
                    p.nombre AS periodo, p.fecha_inicio AS periodo_inicio, p.estado AS periodo_estado,
                    s.nombre AS sede
             FROM inscripciones i
             JOIN grupos g ON g.id = i.grupo_id
             JOIN cursos c ON c.id = g.curso_id
             JOIN matriculas m ON m.id = i.matricula_id
             JOIN periodos p ON p.id = g.periodo_id
             JOIN sedes s ON s.id = g.sede_id
             WHERE i.id = ?",
            [$id]
        );
    }

    /** @return list<array<string,mixed>> */
    public static function inscriptions(int $matriculaId): array
    {
        return Database::fetchAll(
            "SELECT i.*, g.codigo AS grupo, g.estado AS grupo_estado,
                    c.nombre AS curso, c.codigo AS curso_codigo,
                    a.nombre AS aula,
                    CONCAT_WS(' ', pd.nombres, pd.apellidos) AS docente
             FROM inscripciones i
             JOIN grupos g ON g.id = i.grupo_id
             JOIN cursos c ON c.id = g.curso_id
             JOIN aulas a ON a.id = g.aula_id
             JOIN docentes d ON d.id = g.docente_id
             JOIN personas pd ON pd.id = d.persona_id
             WHERE i.matricula_id = ?
             ORDER BY c.nombre",
            [$matriculaId]
        );
    }

    /** @return list<array<string,mixed>> */
    public static function movements(int $inscripcionId): array
    {
        return Database::fetchAll(
            "SELECT im.*, go.codigo AS grupo_origen, gd.codigo AS grupo_destino,
                    CONCAT_WS(' ', p.nombres, p.apellidos) AS usuario
             FROM inscripcion_movimientos im
             LEFT JOIN grupos go ON go.id = im.grupo_origen_id
             LEFT JOIN grupos gd ON gd.id = im.grupo_destino_id
             LEFT JOIN usuarios u ON u.id = im.usuario_id
             LEFT JOIN personas p ON p.id = u.persona_id
             WHERE im.inscripcion_id = ?
             ORDER BY im.created_at DESC, im.id DESC",
            [$inscripcionId]
        );
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{rows:list<array<string,mixed>>,total:int}
     */
    public static function matriculasPaginate(array $filters, int $page = 1, int $perPage = 20): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['buscar'])) {
            $term = '%' . $filters['buscar'] . '%';
            $where[] = '(p.nombres LIKE ? OR p.apellidos LIKE ? OR p.numero_documento LIKE ? OR m.codigo LIKE ?)';
            array_push($params, $term, $term, $term, $term);
        }

        if (!empty($filters['periodo_id'])) {
            $where[] = 'm.periodo_id = ?';
            $params[] = (int) $filters['periodo_id'];
        }

        if (!empty($filters['programa_id'])) {
            $where[] = 'm.programa_id = ?';
            $params[] = (int) $filters['programa_id'];
        }

        if (!empty($filters['sede_id'])) {
            $where[] = 'm.sede_id = ?';
            $params[] = (int) $filters['sede_id'];
        }

        if (!empty($filters['estado'])) {
            $where[] = 'm.estado = ?';
            $params[] = $filters['estado'];
        }

        $scope = Auth::sedeScope();
        if ($scope !== null) {
            $where[] = 'm.sede_id = ?';
            $params[] = $scope;
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) Database::scalar(
            "SELECT COUNT(*) FROM matriculas m JOIN estudiantes e ON e.id = m.estudiante_id JOIN personas p ON p.id = e.persona_id WHERE {$whereSql}",
            $params
        );
        $offset = max(0, ($page - 1) * $perPage);

        $rows = Database::fetchAll(
            "SELECT m.*, pr.nombre AS programa, pe.nombre AS periodo, pe.estado AS periodo_estado,
                    s.nombre AS sede, e.codigo AS estudiante_codigo,
                    p.nombres, p.apellidos, p.numero_documento,
                    (SELECT COUNT(*) FROM inscripciones i WHERE i.matricula_id = m.id AND i.estado = 'activa') AS cursos
             FROM matriculas m
             JOIN estudiantes e ON e.id = m.estudiante_id
             JOIN personas p ON p.id = e.persona_id
             JOIN programas pr ON pr.id = m.programa_id
             JOIN periodos pe ON pe.id = m.periodo_id
             JOIN sedes s ON s.id = m.sede_id
             WHERE {$whereSql}
             ORDER BY pe.fecha_inicio DESC, p.apellidos, p.nombres
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total];
    }
}
