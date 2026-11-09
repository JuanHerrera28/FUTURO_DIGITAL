<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\DomainException;

final class TeacherService
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
            $where[] = '(p.nombres LIKE ? OR p.apellidos LIKE ? OR p.numero_documento LIKE ? OR d.codigo LIKE ?)';
            array_push($params, $term, $term, $term, $term);
        }

        if (!empty($filters['sede_id'])) {
            $where[] = 'd.sede_id = ?';
            $params[] = (int) $filters['sede_id'];
        }

        if (!empty($filters['estado'])) {
            $where[] = 'd.estado = ?';
            $params[] = $filters['estado'];
        }

        $scope = Auth::sedeScope();
        if ($scope !== null) {
            $where[] = 'd.sede_id = ?';
            $params[] = $scope;
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) Database::scalar(
            "SELECT COUNT(*) FROM docentes d JOIN personas p ON p.id = d.persona_id WHERE {$whereSql}",
            $params
        );

        $offset = max(0, ($page - 1) * $perPage);
        $rows = Database::fetchAll(
            "SELECT d.*, p.nombres, p.apellidos, p.numero_documento, p.email, p.telefono,
                    s.nombre AS sede,
                    (SELECT COUNT(*) FROM grupos g WHERE g.docente_id = d.id AND g.estado = 'activo') AS grupos_activos
             FROM docentes d
             JOIN personas p ON p.id = d.persona_id
             JOIN sedes s ON s.id = d.sede_id
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
            'SELECT d.*, p.tipo_documento_id, p.numero_documento, p.nombres, p.apellidos,
                    p.fecha_nacimiento, p.email, p.telefono, p.direccion,
                    s.nombre AS sede
             FROM docentes d
             JOIN personas p ON p.id = d.persona_id
             JOIN sedes s ON s.id = d.sede_id
             WHERE d.id = ?',
            [$id]
        );
    }

    /**
     * @param array<string,mixed> $data
     * @return array{docente_id:int,credentials:array<string,mixed>}
     */
    public static function create(array $data): array
    {
        $sedeId = (int) ($data['sede_id'] ?? 0);
        if ($sedeId <= 0) {
            throw new DomainException('RF-05', 'Debe seleccionar la sede del docente.');
        }

        if (!Auth::canAccessSede($sedeId)) {
            throw new DomainException('RN-19', 'Solo puede registrar docentes de su sede.');
        }

        $vinculacion = (string) ($data['tipo_vinculacion'] ?? 'catedra');
        if (!in_array($vinculacion, ['planta', 'catedra', 'contrato'], true)) {
            throw new DomainException('RF-05', 'El tipo de vinculación no es válido.');
        }

        return Database::transaction(static function () use ($data, $sedeId, $vinculacion): array {
            $personaId = PersonService::create($data);

            $docenteId = Database::insert('docentes', [
                'persona_id' => $personaId,
                'sede_id' => $sedeId,
                'titulo' => ($data['titulo'] ?? '') !== '' ? trim((string) $data['titulo']) : null,
                'tipo_vinculacion' => $vinculacion,
                'estado' => 'activo',
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            CodeService::assign('docentes', $docenteId, 'DOC', false, 5);

            $credentials = UserService::createForPersona(
                $personaId,
                6,
                $sedeId,
                (string) ($data['numero_documento'] ?? ''),
                'Credenciales del docente. La contraseña es temporal y se muestra una sola vez.'
            );

            Audit::log('docente_creado', 'docentes', 'docentes', $docenteId, null, [
                'persona_id' => $personaId,
                'sede_id' => $sedeId,
                'tipo_vinculacion' => $vinculacion,
            ]);

            return ['docente_id' => $docenteId, 'credentials' => $credentials];
        });
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function update(int $id, array $data): void
    {
        $before = self::find($id);
        if ($before === null) {
            throw new DomainException('RF-05', 'El docente no existe.');
        }

        if (!Auth::canAccessSede((int) $before['sede_id'])) {
            throw new DomainException('RN-19', 'Solo puede modificar docentes de su sede.');
        }

        $sedeId = (int) ($data['sede_id'] ?? $before['sede_id']);
        if (!Auth::canAccessSede($sedeId)) {
            throw new DomainException('RN-19', 'Solo puede asignar docentes a su sede.');
        }

        $vinculacion = (string) ($data['tipo_vinculacion'] ?? $before['tipo_vinculacion']);
        if (!in_array($vinculacion, ['planta', 'catedra', 'contrato'], true)) {
            throw new DomainException('RF-05', 'El tipo de vinculación no es válido.');
        }

        Database::transaction(static function () use ($id, $before, $data, $sedeId, $vinculacion): void {
            PersonService::update((int) $before['persona_id'], $data);

            Database::update('docentes', [
                'sede_id' => $sedeId,
                'titulo' => ($data['titulo'] ?? '') !== '' ? trim((string) $data['titulo']) : null,
                'tipo_vinculacion' => $vinculacion,
                'estado' => (string) ($data['estado'] ?? $before['estado']),
                'updated_by' => Auth::id(),
            ], ['id' => $id]);

            Audit::log('docente_actualizado', 'docentes', 'docentes', $id, [
                'sede_id' => $before['sede_id'],
                'tipo_vinculacion' => $before['tipo_vinculacion'],
                'estado' => $before['estado'],
            ], [
                'sede_id' => $sedeId,
                'tipo_vinculacion' => $vinculacion,
                'estado' => (string) ($data['estado'] ?? $before['estado']),
            ]);
        });
    }

    /**
     * Carga académica: grupos asignados por periodo.
     *
     * @return list<array<string,mixed>>
     */
    public static function load(int $docenteId): array
    {
        return Database::fetchAll(
            "SELECT g.*, c.nombre AS curso, c.codigo AS curso_codigo,
                    p.nombre AS periodo, p.estado AS periodo_estado,
                    s.nombre AS sede, a.nombre AS aula,
                    (SELECT COUNT(*) FROM inscripciones i WHERE i.grupo_id = g.id AND i.estado = 'activa') AS inscritos
             FROM grupos g
             JOIN cursos c ON c.id = g.curso_id
             JOIN periodos p ON p.id = g.periodo_id
             JOIN sedes s ON s.id = g.sede_id
             JOIN aulas a ON a.id = g.aula_id
             WHERE g.docente_id = ?
             ORDER BY p.fecha_inicio DESC, c.nombre",
            [$docenteId]
        );
    }

    /** @return list<array<string,mixed>> */
    public static function options(?int $sedeId = null): array
    {
        $sql = "SELECT d.id, p.nombres, p.apellidos, p.numero_documento, d.sede_id, s.nombre AS sede
                FROM docentes d
                JOIN personas p ON p.id = d.persona_id
                JOIN sedes s ON s.id = d.sede_id
                WHERE d.estado = 'activo'";
        $params = [];

        if ($sedeId !== null) {
            $sql .= ' AND d.sede_id = ?';
            $params[] = $sedeId;
        }

        $sql .= ' ORDER BY p.apellidos, p.nombres';

        return Database::fetchAll($sql, $params);
    }

    /** @return array<string,mixed>|null */
    public static function byPersona(int $personaId): ?array
    {
        return Database::fetch('SELECT * FROM docentes WHERE persona_id = ?', [$personaId]);
    }
}
