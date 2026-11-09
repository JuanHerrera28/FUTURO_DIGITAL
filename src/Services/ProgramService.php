<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\DomainException;

final class ProgramService
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
            $where[] = '(codigo LIKE ? OR nombre LIKE ?)';
            array_push($params, $term, $term);
        }

        if (!empty($filters['estado'])) {
            $where[] = 'estado = ?';
            $params[] = $filters['estado'];
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) Database::scalar("SELECT COUNT(*) FROM programas WHERE {$whereSql}", $params);
        $offset = max(0, ($page - 1) * $perPage);

        $rows = Database::fetchAll(
            "SELECT p.*, (SELECT COUNT(*) FROM plan_estudios pe WHERE pe.programa_id = p.id AND pe.estado = 'activo') AS cursos,
                    (SELECT COUNT(*) FROM matriculas m WHERE m.programa_id = p.id AND m.estado = 'activa') AS matriculas
             FROM programas p
             WHERE {$whereSql}
             ORDER BY p.nombre
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM programas WHERE id = ?', [$id]);
    }

    /** @param array<string,mixed> $data */
    public static function create(array $data): int
    {
        $codigo = mb_strtoupper(trim((string) ($data['codigo'] ?? '')));
        $nombre = trim((string) ($data['nombre'] ?? ''));

        if ($codigo === '' || $nombre === '') {
            throw new DomainException('RF-02', 'El código y el nombre del programa son obligatorios.');
        }

        if (Database::scalar('SELECT COUNT(*) FROM programas WHERE codigo = ?', [$codigo]) > 0) {
            throw new DomainException('RF-02', 'Ya existe un programa con el código ' . $codigo . '.');
        }

        if (Database::scalar('SELECT COUNT(*) FROM programas WHERE nombre = ?', [$nombre]) > 0) {
            throw new DomainException('RF-02', 'Ya existe un programa con el nombre ' . $nombre . '.');
        }

        $id = Database::insert('programas', [
            'codigo' => $codigo,
            'nombre' => $nombre,
            'duracion_periodos' => max(1, (int) ($data['duracion_periodos'] ?? 1)),
            'estado' => (string) ($data['estado'] ?? 'activo'),
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        Audit::log('programa_creado', 'programas', 'programas', $id, null, ['codigo' => $codigo, 'nombre' => $nombre]);

        return $id;
    }

    /** @param array<string,mixed> $data */
    public static function update(int $id, array $data): void
    {
        $before = self::find($id);
        if ($before === null) {
            throw new DomainException('RF-02', 'El programa no existe.');
        }

        $codigo = mb_strtoupper(trim((string) ($data['codigo'] ?? $before['codigo'])));
        $nombre = trim((string) ($data['nombre'] ?? $before['nombre']));

        if (Database::scalar('SELECT COUNT(*) FROM programas WHERE codigo = ? AND id <> ?', [$codigo, $id]) > 0) {
            throw new DomainException('RF-02', 'Ya existe otro programa con ese código.');
        }

        if (Database::scalar('SELECT COUNT(*) FROM programas WHERE nombre = ? AND id <> ?', [$nombre, $id]) > 0) {
            throw new DomainException('RF-02', 'Ya existe otro programa con ese nombre.');
        }

        Database::update('programas', [
            'codigo' => $codigo,
            'nombre' => $nombre,
            'duracion_periodos' => max(1, (int) ($data['duracion_periodos'] ?? $before['duracion_periodos'])),
            'estado' => (string) ($data['estado'] ?? $before['estado']),
            'updated_by' => Auth::id(),
        ], ['id' => $id]);

        Audit::log('programa_actualizado', 'programas', 'programas', $id, [
            'codigo' => $before['codigo'], 'nombre' => $before['nombre'],
            'duracion_periodos' => $before['duracion_periodos'], 'estado' => $before['estado'],
        ], $data);
    }

    /** @return list<array<string,mixed>> */
    public static function options(): array
    {
        return Database::fetchAll("SELECT id, codigo, nombre FROM programas WHERE estado = 'activo' ORDER BY nombre");
    }

    /** @return list<array<string,mixed>> */
    public static function plan(int $programaId): array
    {
        return Database::fetchAll(
            "SELECT pe.*, c.codigo AS curso_codigo, c.nombre AS curso, c.estado AS curso_estado
             FROM plan_estudios pe
             JOIN cursos c ON c.id = pe.curso_id
             WHERE pe.programa_id = ?
             ORDER BY pe.nivel, c.nombre",
            [$programaId]
        );
    }

    public static function addCourse(int $programaId, int $cursoId, int $nivel, bool $obligatorio): int
    {
        if (self::find($programaId) === null) {
            throw new DomainException('RF-03', 'El programa no existe.');
        }

        if (Database::scalar('SELECT COUNT(*) FROM cursos WHERE id = ?', [$cursoId]) === 0) {
            throw new DomainException('RF-03', 'El curso seleccionado no existe.');
        }

        $existing = Database::fetch(
            'SELECT * FROM plan_estudios WHERE programa_id = ? AND curso_id = ?',
            [$programaId, $cursoId]
        );

        if ($existing !== null) {
            Database::update('plan_estudios', [
                'nivel' => max(1, $nivel),
                'obligatorio' => $obligatorio ? 1 : 0,
                'estado' => 'activo',
            ], ['id' => (int) $existing['id']]);

            Audit::log('plan_actualizado', 'programas', 'plan_estudios', (int) $existing['id'], $existing, [
                'nivel' => $nivel, 'obligatorio' => $obligatorio ? 1 : 0, 'estado' => 'activo',
            ]);

            return (int) $existing['id'];
        }

        $id = Database::insert('plan_estudios', [
            'programa_id' => $programaId,
            'curso_id' => $cursoId,
            'nivel' => max(1, $nivel),
            'obligatorio' => $obligatorio ? 1 : 0,
            'estado' => 'activo',
            'created_by' => Auth::id(),
        ]);

        Audit::log('plan_creado', 'programas', 'plan_estudios', $id, null, [
            'programa_id' => $programaId, 'curso_id' => $cursoId, 'nivel' => $nivel,
        ]);

        return $id;
    }

    public static function removeCourse(int $planId): void
    {
        $item = Database::fetch('SELECT * FROM plan_estudios WHERE id = ?', [$planId]);
        if ($item === null) {
            throw new DomainException('RF-03', 'El curso no está asociado al programa.');
        }

        Database::update('plan_estudios', ['estado' => 'inactivo'], ['id' => $planId]);
        Audit::log('plan_inactivado', 'programas', 'plan_estudios', $planId, ['estado' => $item['estado']], ['estado' => 'inactivo']);
    }

    /** @return list<array<string,mixed>> */
    public static function prerequisites(?int $cursoId = null): array
    {
        $sql = "SELECT pr.*, c.codigo AS curso_codigo, c.nombre AS curso,
                       r.codigo AS requisito_codigo, r.nombre AS requisito
                FROM prerrequisitos pr
                JOIN cursos c ON c.id = pr.curso_id
                JOIN cursos r ON r.id = pr.curso_requisito_id";
        $params = [];

        if ($cursoId !== null) {
            $sql .= ' WHERE pr.curso_id = ?';
            $params[] = $cursoId;
        }

        $sql .= ' ORDER BY c.nombre, r.nombre';

        return Database::fetchAll($sql, $params);
    }

    public static function addPrerequisite(int $cursoId, int $requisitoId, string $tipo): int
    {
        if ($cursoId === $requisitoId) {
            throw new DomainException('RF-04', 'Un curso no puede ser prerrequisito de sí mismo.');
        }

        if (!in_array($tipo, ['aprobado', 'cursado'], true)) {
            throw new DomainException('RF-04', 'El tipo de prerrequisito no es válido.');
        }

        if (Database::scalar('SELECT COUNT(*) FROM cursos WHERE id = ?', [$cursoId]) === 0
            || Database::scalar('SELECT COUNT(*) FROM cursos WHERE id = ?', [$requisitoId]) === 0) {
            throw new DomainException('RF-04', 'Los cursos seleccionados no existen.');
        }

        $existing = Database::fetch(
            'SELECT * FROM prerrequisitos WHERE curso_id = ? AND curso_requisito_id = ?',
            [$cursoId, $requisitoId]
        );

        if ($existing !== null) {
            Database::update('prerrequisitos', ['tipo' => $tipo, 'estado' => 'activo'], ['id' => (int) $existing['id']]);

            return (int) $existing['id'];
        }

        $id = Database::insert('prerrequisitos', [
            'curso_id' => $cursoId,
            'curso_requisito_id' => $requisitoId,
            'tipo' => $tipo,
            'estado' => 'activo',
            'created_by' => Auth::id(),
        ]);

        Audit::log('prerrequisito_creado', 'programas', 'prerrequisitos', $id, null, [
            'curso_id' => $cursoId, 'requisito_id' => $requisitoId, 'tipo' => $tipo,
        ]);

        return $id;
    }

    public static function removePrerequisite(int $id): void
    {
        $item = Database::fetch('SELECT * FROM prerrequisitos WHERE id = ?', [$id]);
        if ($item === null) {
            throw new DomainException('RF-04', 'El prerrequisito no existe.');
        }

        Database::update('prerrequisitos', ['estado' => 'inactivo'], ['id' => $id]);
        Audit::log('prerrequisito_inactivado', 'programas', 'prerrequisitos', $id, ['estado' => $item['estado']], ['estado' => 'inactivo']);
    }

    /** @return list<array<string,mixed>> */
    public static function activePrerequisites(int $cursoId): array
    {
        return Database::fetchAll(
            "SELECT pr.*, r.nombre AS requisito, r.codigo AS requisito_codigo
             FROM prerrequisitos pr
             JOIN cursos r ON r.id = pr.curso_requisito_id
             WHERE pr.curso_id = ? AND pr.estado = 'activo'",
            [$cursoId]
        );
    }

    public static function assertCourseInPlan(int $programaId, int $cursoId): void
    {
        $inPlan = Database::scalar(
            "SELECT COUNT(*) FROM plan_estudios WHERE programa_id = ? AND curso_id = ? AND estado = 'activo'",
            [$programaId, $cursoId]
        );

        if ((int) $inPlan === 0) {
            throw new DomainException('RN-07', 'El curso no pertenece al plan de estudios del programa del estudiante.');
        }
    }
}
