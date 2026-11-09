<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\DomainException;

final class StudentService
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
            $where[] = '(p.nombres LIKE ? OR p.apellidos LIKE ? OR p.numero_documento LIKE ? OR e.codigo LIKE ?)';
            array_push($params, $term, $term, $term, $term);
        }

        if (!empty($filters['sede_id'])) {
            $where[] = 'e.sede_id = ?';
            $params[] = (int) $filters['sede_id'];
        }

        if (!empty($filters['estado'])) {
            $where[] = 'e.estado = ?';
            $params[] = $filters['estado'];
        }

        $scope = Auth::sedeScope();
        if ($scope !== null) {
            $where[] = 'e.sede_id = ?';
            $params[] = $scope;
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) Database::scalar(
            "SELECT COUNT(*) FROM estudiantes e JOIN personas p ON p.id = e.persona_id WHERE {$whereSql}",
            $params
        );

        $offset = max(0, ($page - 1) * $perPage);
        $rows = Database::fetchAll(
            "SELECT e.*, p.nombres, p.apellidos, p.numero_documento, p.email, p.telefono,
                    td.nombre AS tipo_documento, s.nombre AS sede
             FROM estudiantes e
             JOIN personas p ON p.id = e.persona_id
             JOIN documento_tipos td ON td.id = p.tipo_documento_id
             JOIN sedes s ON s.id = e.sede_id
             WHERE {$whereSql}
             ORDER BY p.apellidos, p.nombres
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::fetch(
            'SELECT e.*, p.tipo_documento_id, p.numero_documento, p.nombres, p.apellidos,
                    p.fecha_nacimiento, p.email, p.telefono, p.direccion, p.autorizacion_datos,
                    p.fecha_autorizacion, p.medio_autorizacion,
                    td.nombre AS tipo_documento, s.nombre AS sede
             FROM estudiantes e
             JOIN personas p ON p.id = e.persona_id
             JOIN documento_tipos td ON td.id = p.tipo_documento_id
             JOIN sedes s ON s.id = e.sede_id
             WHERE e.id = ?',
            [$id]
        );
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function create(array $data): int
    {
        $sedeId = (int) ($data['sede_id'] ?? 0);
        if ($sedeId <= 0) {
            throw new DomainException('RF-01', 'Debe seleccionar la sede del estudiante.');
        }

        if (!Auth::canAccessSede($sedeId)) {
            throw new DomainException('RN-19', 'Solo puede registrar estudiantes de su sede.');
        }

        return Database::transaction(static function () use ($data, $sedeId): int {
            $personaId = PersonService::create($data);

            $id = Database::insert('estudiantes', [
                'persona_id' => $personaId,
                'sede_id' => $sedeId,
                'estado' => 'activo',
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            CodeService::assign('estudiantes', $id, 'EST', false, 5);

            Audit::log('estudiante_creado', 'estudiantes', 'estudiantes', $id, null, [
                'persona_id' => $personaId,
                'sede_id' => $sedeId,
            ]);

            return $id;
        });
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function update(int $id, array $data): void
    {
        $before = self::find($id);
        if ($before === null) {
            throw new DomainException('RF-01', 'El estudiante no existe.');
        }

        if (!Auth::canAccessSede((int) $before['sede_id'])) {
            throw new DomainException('RN-19', 'Solo puede modificar estudiantes de su sede.');
        }

        $sedeId = (int) ($data['sede_id'] ?? $before['sede_id']);
        if (!Auth::canAccessSede($sedeId)) {
            throw new DomainException('RN-19', 'Solo puede asignar estudiantes a su sede.');
        }

        Database::transaction(static function () use ($id, $before, $data, $sedeId): void {
            PersonService::update((int) $before['persona_id'], $data);

            Database::update('estudiantes', [
                'sede_id' => $sedeId,
                'estado' => (string) ($data['estado'] ?? $before['estado']),
                'updated_by' => Auth::id(),
            ], ['id' => $id]);

            Audit::log('estudiante_actualizado', 'estudiantes', 'estudiantes', $id, [
                'sede_id' => $before['sede_id'],
                'estado' => $before['estado'],
            ], [
                'sede_id' => $sedeId,
                'estado' => (string) ($data['estado'] ?? $before['estado']),
            ]);
        });
    }

    /** @return list<array<string,mixed>> */
    public static function guardians(int $studentId): array
    {
        return Database::fetchAll(
            'SELECT * FROM acudientes WHERE estudiante_id = ? ORDER BY estado, nombre',
            [$studentId]
        );
    }

    /** @param array<string,mixed> $data */
    public static function addGuardian(int $studentId, array $data): int
    {
        $student = self::find($studentId);
        if ($student === null) {
            throw new DomainException('RF-01', 'El estudiante no existe.');
        }

        $id = Database::insert('acudientes', [
            'estudiante_id' => $studentId,
            'nombre' => trim((string) ($data['nombre'] ?? '')),
            'documento' => ($data['documento'] ?? '') !== '' ? trim((string) $data['documento']) : null,
            'telefono' => ($data['telefono'] ?? '') !== '' ? trim((string) $data['telefono']) : null,
            'parentesco' => ($data['parentesco'] ?? '') !== '' ? trim((string) $data['parentesco']) : null,
            'estado' => 'activo',
        ]);

        Audit::log('acudiente_creado', 'estudiantes', 'acudientes', $id, null, ['estudiante_id' => $studentId]);

        return $id;
    }

    public static function toggleGuardian(int $guardianId): void
    {
        $guardian = Database::fetch('SELECT * FROM acudientes WHERE id = ?', [$guardianId]);
        if ($guardian === null) {
            throw new DomainException('RF-01', 'El acudiente no existe.');
        }

        $newState = (string) $guardian['estado'] === 'activo' ? 'inactivo' : 'activo';
        Database::update('acudientes', ['estado' => $newState], ['id' => $guardianId]);
        Audit::log('acudiente_estado', 'estudiantes', 'acudientes', $guardianId, ['estado' => $guardian['estado']], ['estado' => $newState]);
    }

    /**
     * Historial académico del estudiante.
     *
     * @return list<array<string,mixed>>
     */
    public static function academicHistory(int $studentId): array
    {
        return Database::fetchAll(
            "SELECT m.id AS matricula_id, m.codigo AS matricula, m.estado AS matricula_estado,
                    pr.nombre AS programa, pe.nombre AS periodo, m.fecha,
                    i.id AS inscripcion_id, i.estado AS inscripcion_estado, i.nota_final,
                    c.nombre AS curso, c.codigo AS curso_codigo,
                    g.codigo AS grupo,
                    (SELECT CONCAT_WS(' ', pd.nombres, pd.apellidos) FROM docentes dd JOIN personas pd ON pd.id = dd.persona_id WHERE dd.id = g.docente_id) AS docente
             FROM matriculas m
             JOIN programas pr ON pr.id = m.programa_id
             JOIN periodos pe ON pe.id = m.periodo_id
             LEFT JOIN inscripciones i ON i.matricula_id = m.id
             LEFT JOIN grupos g ON g.id = i.grupo_id
             LEFT JOIN cursos c ON c.id = g.curso_id
             WHERE m.estudiante_id = ?
             ORDER BY pe.fecha_inicio DESC, c.nombre",
            [$studentId]
        );
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function enrollmentOptions(int $studentId): array
    {
        return Database::fetchAll(
            'SELECT m.*, pr.nombre AS programa, pe.nombre AS periodo, pe.estado AS periodo_estado
             FROM matriculas m
             JOIN programas pr ON pr.id = m.programa_id
             JOIN periodos pe ON pe.id = m.periodo_id
             WHERE m.estudiante_id = ? AND m.estado = ?
             ORDER BY pe.fecha_inicio DESC',
            [$studentId, 'activa']
        );
    }

    /** @return array<string,mixed>|null */
    public static function byPersona(int $personaId): ?array
    {
        return Database::fetch('SELECT * FROM estudiantes WHERE persona_id = ?', [$personaId]);
    }
}
