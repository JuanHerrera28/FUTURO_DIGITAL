<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\DomainException;
use App\Core\Param;

final class GroupService
{
    /**
     * @param array<string,mixed> $filters
     * @return array{rows:list<array<string,mixed>>,total:int}
     */
    public static function paginate(array $filters, int $page = 1, int $perPage = 20): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['buscar'])) {
            $term = '%' . $filters['buscar'] . '%';
            $where[] = '(g.codigo LIKE ? OR c.nombre LIKE ?)';
            array_push($params, $term, $term);
        }

        if (!empty($filters['periodo_id'])) {
            $where[] = 'g.periodo_id = ?';
            $params[] = (int) $filters['periodo_id'];
        }

        if (!empty($filters['sede_id'])) {
            $where[] = 'g.sede_id = ?';
            $params[] = (int) $filters['sede_id'];
        }

        if (!empty($filters['curso_id'])) {
            $where[] = 'g.curso_id = ?';
            $params[] = (int) $filters['curso_id'];
        }

        if (!empty($filters['docente_id'])) {
            $where[] = 'g.docente_id = ?';
            $params[] = (int) $filters['docente_id'];
        }

        if (!empty($filters['estado'])) {
            $where[] = 'g.estado = ?';
            $params[] = $filters['estado'];
        }

        $scope = Auth::sedeScope();
        if ($scope !== null) {
            $where[] = 'g.sede_id = ?';
            $params[] = $scope;
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) Database::scalar(
            "SELECT COUNT(*) FROM grupos g JOIN cursos c ON c.id = g.curso_id WHERE {$whereSql}",
            $params
        );
        $offset = max(0, ($page - 1) * $perPage);

        $rows = Database::fetchAll(
            "SELECT g.*, c.nombre AS curso, c.codigo AS curso_codigo,
                    p.nombre AS periodo, p.estado AS periodo_estado,
                    s.nombre AS sede, a.nombre AS aula, a.capacidad AS aula_capacidad,
                    CONCAT_WS(' ', pd.nombres, pd.apellidos) AS docente,
                    (SELECT COUNT(*) FROM inscripciones i WHERE i.grupo_id = g.id AND i.estado = 'activa') AS inscritos
             FROM grupos g
             JOIN cursos c ON c.id = g.curso_id
             JOIN periodos p ON p.id = g.periodo_id
             JOIN sedes s ON s.id = g.sede_id
             JOIN aulas a ON a.id = g.aula_id
             JOIN docentes d ON d.id = g.docente_id
             JOIN personas pd ON pd.id = d.persona_id
             WHERE {$whereSql}
             ORDER BY p.fecha_inicio DESC, s.nombre, c.nombre, g.codigo
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::fetch(
            "SELECT g.*, c.nombre AS curso, c.codigo AS curso_codigo,
                    p.nombre AS periodo, p.estado AS periodo_estado,
                    p.fecha_inicio AS periodo_inicio, p.fecha_fin AS periodo_fin,
                    s.nombre AS sede, a.nombre AS aula, a.capacidad AS aula_capacidad,
                    CONCAT_WS(' ', pd.nombres, pd.apellidos) AS docente,
                    (SELECT COUNT(*) FROM inscripciones i WHERE i.grupo_id = g.id AND i.estado = 'activa') AS inscritos
             FROM grupos g
             JOIN cursos c ON c.id = g.curso_id
             JOIN periodos p ON p.id = g.periodo_id
             JOIN sedes s ON s.id = g.sede_id
             JOIN aulas a ON a.id = g.aula_id
             JOIN docentes d ON d.id = g.docente_id
             JOIN personas pd ON pd.id = d.persona_id
             WHERE g.id = ?",
            [$id]
        );
    }

    /**
     * @param array<string,mixed> $data
     * @param array<int,mixed> $days
     * @param array<int,mixed> $starts
     * @param array<int,mixed> $ends
     */
    public static function create(array $data, array $days, array $starts, array $ends): int
    {
        $cursoId = (int) ($data['curso_id'] ?? 0);
        $docenteId = (int) ($data['docente_id'] ?? 0);
        $periodoId = (int) ($data['periodo_id'] ?? 0);
        $sedeId = (int) ($data['sede_id'] ?? 0);
        $aulaId = (int) ($data['aula_id'] ?? 0);
        $capacidad = (int) ($data['capacidad_max'] ?? 0);
        $cupoMinimo = max(1, (int) ($data['cupo_minimo'] ?? Param::int('cupo_minimo_grupo', 5)));

        self::assertContext($cursoId, $docenteId, $periodoId, $sedeId, $aulaId, $capacidad);

        if (!Auth::canAccessSede($sedeId)) {
            throw new DomainException('RN-19', 'Solo puede crear grupos en su sede.');
        }

        $blocks = ScheduleService::fromRequest($days, $starts, $ends);
        ScheduleService::assertTeacherFree($docenteId, $periodoId, $blocks);
        ScheduleService::assertClassroomFree($aulaId, $periodoId, $blocks);

        return Database::transaction(static function () use ($cursoId, $docenteId, $periodoId, $sedeId, $aulaId, $capacidad, $cupoMinimo, $blocks): int {
            $id = Database::insert('grupos', [
                'codigo' => 'TMP-' . bin2hex(random_bytes(6)),
                'curso_id' => $cursoId,
                'docente_id' => $docenteId,
                'periodo_id' => $periodoId,
                'sede_id' => $sedeId,
                'aula_id' => $aulaId,
                'capacidad_max' => $capacidad,
                'cupo_minimo' => $cupoMinimo,
                'estado' => 'activo',
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            CodeService::assign('grupos', $id, 'GRP', true, 5);
            self::replaceSchedule($id, $blocks);

            Audit::log('grupo_creado', 'grupos', 'grupos', $id, null, [
                'curso_id' => $cursoId,
                'docente_id' => $docenteId,
                'periodo_id' => $periodoId,
                'sede_id' => $sedeId,
                'aula_id' => $aulaId,
                'capacidad_max' => $capacidad,
                'cupo_minimo' => $cupoMinimo,
                'horarios' => $blocks,
            ]);

            return $id;
        });
    }

    /**
     * @param array<string,mixed> $data
     * @param array<int,mixed> $days
     * @param array<int,mixed> $starts
     * @param array<int,mixed> $ends
     */
    public static function update(int $id, array $data, array $days = [], array $starts = [], array $ends = []): void
    {
        $before = self::find($id);
        if ($before === null) {
            throw new DomainException('RF-07', 'El grupo no existe.');
        }

        if (!Auth::canAccessSede((int) $before['sede_id'])) {
            throw new DomainException('RN-19', 'Solo puede modificar grupos de su sede.');
        }

        if ((string) $before['periodo_estado'] === 'cerrado') {
            throw new DomainException('RF-22', 'No se puede modificar un grupo de un periodo cerrado.');
        }

        $cursoId = (int) ($data['curso_id'] ?? $before['curso_id']);
        $docenteId = (int) ($data['docente_id'] ?? $before['docente_id']);
        $periodoId = (int) ($data['periodo_id'] ?? $before['periodo_id']);
        $sedeId = (int) ($data['sede_id'] ?? $before['sede_id']);
        $aulaId = (int) ($data['aula_id'] ?? $before['aula_id']);
        $capacidad = (int) ($data['capacidad_max'] ?? $before['capacidad_max']);
        $cupoMinimo = max(1, (int) ($data['cupo_minimo'] ?? $before['cupo_minimo']));

        self::assertContext($cursoId, $docenteId, $periodoId, $sedeId, $aulaId, $capacidad);

        if (!Auth::canAccessSede($sedeId)) {
            throw new DomainException('RN-19', 'Solo puede asignar grupos a su sede.');
        }

        $inscritos = (int) $before['inscritos'];
        if ($capacidad < $inscritos) {
            throw new DomainException('RN-03', 'La capacidad máxima no puede ser menor que los ' . $inscritos . ' estudiantes inscritos.');
        }

        $blocks = [];
        if ($days !== [] || $starts !== [] || $ends !== []) {
            $blocks = ScheduleService::fromRequest($days, $starts, $ends);
            ScheduleService::assertTeacherFree($docenteId, $periodoId, $blocks, $id);
            ScheduleService::assertClassroomFree($aulaId, $periodoId, $blocks, $id);
            self::assertStudentsFree($id, $periodoId, $blocks);
        }

        Database::transaction(static function () use ($id, $before, $cursoId, $docenteId, $periodoId, $sedeId, $aulaId, $capacidad, $cupoMinimo, $blocks, $data): void {
            Database::update('grupos', [
                'curso_id' => $cursoId,
                'docente_id' => $docenteId,
                'periodo_id' => $periodoId,
                'sede_id' => $sedeId,
                'aula_id' => $aulaId,
                'capacidad_max' => $capacidad,
                'cupo_minimo' => $cupoMinimo,
                'estado' => (string) ($data['estado'] ?? $before['estado']),
                'updated_by' => Auth::id(),
            ], ['id' => $id]);

            if ($blocks !== []) {
                self::replaceSchedule($id, $blocks);
            }

            Audit::log('grupo_actualizado', 'grupos', 'grupos', $id, [
                'curso_id' => $before['curso_id'],
                'docente_id' => $before['docente_id'],
                'aula_id' => $before['aula_id'],
                'capacidad_max' => $before['capacidad_max'],
                'cupo_minimo' => $before['cupo_minimo'],
                'estado' => $before['estado'],
            ], [
                'curso_id' => $cursoId,
                'docente_id' => $docenteId,
                'aula_id' => $aulaId,
                'capacidad_max' => $capacidad,
                'cupo_minimo' => $cupoMinimo,
                'estado' => (string) ($data['estado'] ?? $before['estado']),
                'horarios' => $blocks,
            ]);
        });
    }

    /**
     * @param list<array{dia_semana:int,hora_inicio:string,hora_fin:string}> $blocks
     */
    private static function replaceSchedule(int $groupId, array $blocks): void
    {
        Database::update('grupo_horarios', ['estado' => 'inactivo'], ['grupo_id' => $groupId]);

        foreach ($blocks as $block) {
            Database::insert('grupo_horarios', [
                'grupo_id' => $groupId,
                'dia_semana' => $block['dia_semana'],
                'hora_inicio' => $block['hora_inicio'],
                'hora_fin' => $block['hora_fin'],
                'estado' => 'activo',
            ]);
        }
    }

    /**
     * @param list<array{dia_semana:int,hora_inicio:string,hora_fin:string}> $blocks
     */
    private static function assertStudentsFree(int $groupId, int $periodoId, array $blocks): void
    {
        $students = Database::fetchAll(
            "SELECT matricula_id FROM inscripciones WHERE grupo_id = ? AND estado = 'activa'",
            [$groupId]
        );

        foreach ($students as $student) {
            ScheduleService::assertStudentFree((int) $student['matricula_id'], $periodoId, $blocks, $groupId);
        }
    }

    private static function assertContext(int $cursoId, int $docenteId, int $periodoId, int $sedeId, int $aulaId, int $capacidad): void
    {
        $course = Database::fetch('SELECT * FROM cursos WHERE id = ?', [$cursoId]);
        if ($course === null || (string) $course['estado'] !== 'activo') {
            throw new DomainException('RF-07', 'El curso seleccionado no existe o está inactivo.');
        }

        $period = Database::fetch('SELECT * FROM periodos WHERE id = ?', [$periodoId]);
        if ($period === null) {
            throw new DomainException('RF-07', 'El periodo seleccionado no existe.');
        }

        if ((string) $period['estado'] === 'cerrado') {
            throw new DomainException('RF-22', 'No se pueden crear o modificar grupos en un periodo cerrado.');
        }

        $teacher = Database::fetch('SELECT * FROM docentes WHERE id = ?', [$docenteId]);
        if ($teacher === null || (string) $teacher['estado'] !== 'activo') {
            throw new DomainException('RF-07', 'El docente seleccionado no existe o está inactivo.');
        }

        if ((int) $teacher['sede_id'] !== $sedeId) {
            throw new DomainException('RF-07', 'El docente debe pertenecer a la misma sede del grupo.');
        }

        $classroom = Database::fetch('SELECT * FROM aulas WHERE id = ?', [$aulaId]);
        if ($classroom === null || (string) $classroom['estado'] !== 'activo') {
            throw new DomainException('RF-07', 'El aula seleccionada no existe o está inactiva.');
        }

        if ((int) $classroom['sede_id'] !== $sedeId) {
            throw new DomainException('RF-07', 'El aula debe pertenecer a la misma sede del grupo.');
        }

        if ($capacidad <= 0) {
            throw new DomainException('RF-07', 'La capacidad máxima del grupo debe ser mayor que cero.');
        }

        if ($capacidad > (int) $classroom['capacidad']) {
            throw new DomainException('RN-03', 'La capacidad del grupo (' . $capacidad . ') no puede superar la capacidad del aula (' . (int) $classroom['capacidad'] . ').');
        }
    }

    /** @return list<array<string,mixed>> */
    public static function schedules(int $groupId): array
    {
        return Database::fetchAll(
            "SELECT * FROM grupo_horarios WHERE grupo_id = ? AND estado = 'activo' ORDER BY dia_semana, hora_inicio",
            [$groupId]
        );
    }

    /** @return list<array<string,mixed>> */
    public static function students(int $groupId): array
    {
        return Database::fetchAll(
            "SELECT i.id AS inscripcion_id, i.estado, i.nota_final,
                    e.id AS estudiante_id, e.codigo AS estudiante_codigo, e.sede_id,
                    p.nombres, p.apellidos, p.numero_documento,
                    m.codigo AS matricula, m.id AS matricula_id
             FROM inscripciones i
             JOIN matriculas m ON m.id = i.matricula_id
             JOIN estudiantes e ON e.id = m.estudiante_id
             JOIN personas p ON p.id = e.persona_id
             WHERE i.grupo_id = ?
             ORDER BY p.apellidos, p.nombres",
            [$groupId]
        );
    }

    public static function availableSeats(int $groupId): int
    {
        $group = Database::fetch(
            "SELECT capacidad_max,
                    (SELECT COUNT(*) FROM inscripciones i WHERE i.grupo_id = g.id AND i.estado = 'activa') AS inscritos
             FROM grupos g WHERE g.id = ?",
            [$groupId]
        );

        if ($group === null) {
            return 0;
        }

        return max(0, (int) $group['capacidad_max'] - (int) $group['inscritos']);
    }

    /**
     * Grupos disponibles para inscribir una matrícula.
     *
     * @return list<array<string,mixed>>
     */
    public static function forEnrollment(int $matriculaId): array
    {
        $matricula = Database::fetch(
            'SELECT m.*, p.fecha_inicio, p.fecha_fin FROM matriculas m JOIN periodos p ON p.id = m.periodo_id WHERE m.id = ?',
            [$matriculaId]
        );

        if ($matricula === null) {
            return [];
        }

        return Database::fetchAll(
            "SELECT g.*, c.nombre AS curso, c.codigo AS curso_codigo,
                    CONCAT_WS(' ', pd.nombres, pd.apellidos) AS docente,
                    a.nombre AS aula,
                    (SELECT COUNT(*) FROM inscripciones i WHERE i.grupo_id = g.id AND i.estado = 'activa') AS inscritos,
                    (SELECT GROUP_CONCAT(CONCAT(gh.dia_semana, ':', gh.hora_inicio, '-', gh.hora_fin) ORDER BY gh.dia_semana, gh.hora_inicio SEPARATOR ' | ')
                     FROM grupo_horarios gh WHERE gh.grupo_id = g.id AND gh.estado = 'activo') AS horario
             FROM grupos g
             JOIN cursos c ON c.id = g.curso_id
             JOIN docentes d ON d.id = g.docente_id
             JOIN personas pd ON pd.id = d.persona_id
             JOIN aulas a ON a.id = g.aula_id
             WHERE g.periodo_id = ?
               AND g.sede_id = ?
               AND g.estado = 'activo'
               AND NOT EXISTS (
                   SELECT 1 FROM inscripciones i2
                   WHERE i2.grupo_id = g.id AND i2.matricula_id = ? AND i2.estado IN ('activa','finalizada')
               )
             ORDER BY c.nombre, g.codigo",
            [(int) $matricula['periodo_id'], (int) $matricula['sede_id'], $matriculaId]
        );
    }

    /** @return list<array<string,mixed>> */
    public static function forTeacher(int $docenteId, ?int $periodoId = null): array
    {
        $sql = "SELECT g.*, c.nombre AS curso, c.codigo AS curso_codigo,
                       p.nombre AS periodo, p.estado AS periodo_estado,
                       s.nombre AS sede, a.nombre AS aula,
                       (SELECT COUNT(*) FROM inscripciones i WHERE i.grupo_id = g.id AND i.estado = 'activa') AS inscritos
                FROM grupos g
                JOIN cursos c ON c.id = g.curso_id
                JOIN periodos p ON p.id = g.periodo_id
                JOIN sedes s ON s.id = g.sede_id
                JOIN aulas a ON a.id = g.aula_id
                WHERE g.docente_id = ? AND g.estado = 'activo'";
        $params = [$docenteId];

        if ($periodoId !== null) {
            $sql .= ' AND g.periodo_id = ?';
            $params[] = $periodoId;
        }

        $sql .= ' ORDER BY p.fecha_inicio DESC, c.nombre';

        return Database::fetchAll($sql, $params);
    }

    /** @return list<array<string,mixed>> */
    public static function options(?int $periodoId = null, ?int $sedeId = null): array
    {
        $sql = "SELECT g.id, g.codigo, g.sede_id, g.periodo_id, g.curso_id,
                       c.nombre AS curso, p.nombre AS periodo, s.nombre AS sede
                FROM grupos g
                JOIN cursos c ON c.id = g.curso_id
                JOIN periodos p ON p.id = g.periodo_id
                JOIN sedes s ON s.id = g.sede_id
                WHERE g.estado = 'activo'";
        $params = [];

        if ($periodoId !== null) {
            $sql .= ' AND g.periodo_id = ?';
            $params[] = $periodoId;
        }

        if ($sedeId !== null) {
            $sql .= ' AND g.sede_id = ?';
            $params[] = $sedeId;
        }

        $sql .= ' ORDER BY c.nombre, g.codigo';

        return Database::fetchAll($sql, $params);
    }
}
