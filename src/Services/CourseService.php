<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\DomainException;

final class CourseService
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
        $total = (int) Database::scalar("SELECT COUNT(*) FROM cursos WHERE {$whereSql}", $params);
        $offset = max(0, ($page - 1) * $perPage);

        $rows = Database::fetchAll(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM plan_estudios pe WHERE pe.curso_id = c.id AND pe.estado = 'activo') AS programas,
                    (SELECT COUNT(*) FROM grupos g WHERE g.curso_id = c.id AND g.estado = 'activo') AS grupos,
                    (SELECT COUNT(*) FROM prerrequisitos pr WHERE pr.curso_id = c.id AND pr.estado = 'activo') AS prerrequisitos
             FROM cursos c
             WHERE {$whereSql}
             ORDER BY c.nombre
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM cursos WHERE id = ?', [$id]);
    }

    /** @param array<string,mixed> $data */
    public static function create(array $data): int
    {
        $codigo = mb_strtoupper(trim((string) ($data['codigo'] ?? '')));
        $nombre = trim((string) ($data['nombre'] ?? ''));

        if ($codigo === '' || $nombre === '') {
            throw new DomainException('RF-03', 'El código y el nombre del curso son obligatorios.');
        }

        if (Database::scalar('SELECT COUNT(*) FROM cursos WHERE codigo = ?', [$codigo]) > 0) {
            throw new DomainException('RF-03', 'Ya existe un curso con el código ' . $codigo . '.');
        }

        if (Database::scalar('SELECT COUNT(*) FROM cursos WHERE nombre = ?', [$nombre]) > 0) {
            throw new DomainException('RF-03', 'Ya existe un curso con el nombre ' . $nombre . '.');
        }

        $id = Database::insert('cursos', [
            'codigo' => $codigo,
            'nombre' => $nombre,
            'estado' => (string) ($data['estado'] ?? 'activo'),
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        Audit::log('curso_creado', 'cursos', 'cursos', $id, null, ['codigo' => $codigo, 'nombre' => $nombre]);

        return $id;
    }

    /** @param array<string,mixed> $data */
    public static function update(int $id, array $data): void
    {
        $before = self::find($id);
        if ($before === null) {
            throw new DomainException('RF-03', 'El curso no existe.');
        }

        $codigo = mb_strtoupper(trim((string) ($data['codigo'] ?? $before['codigo'])));
        $nombre = trim((string) ($data['nombre'] ?? $before['nombre']));

        if (Database::scalar('SELECT COUNT(*) FROM cursos WHERE codigo = ? AND id <> ?', [$codigo, $id]) > 0) {
            throw new DomainException('RF-03', 'Ya existe otro curso con ese código.');
        }

        if (Database::scalar('SELECT COUNT(*) FROM cursos WHERE nombre = ? AND id <> ?', [$nombre, $id]) > 0) {
            throw new DomainException('RF-03', 'Ya existe otro curso con ese nombre.');
        }

        Database::update('cursos', [
            'codigo' => $codigo,
            'nombre' => $nombre,
            'estado' => (string) ($data['estado'] ?? $before['estado']),
            'updated_by' => Auth::id(),
        ], ['id' => $id]);

        Audit::log('curso_actualizado', 'cursos', 'cursos', $id, [
            'codigo' => $before['codigo'], 'nombre' => $before['nombre'], 'estado' => $before['estado'],
        ], ['codigo' => $codigo, 'nombre' => $nombre, 'estado' => (string) ($data['estado'] ?? $before['estado'])]);
    }

    /** @return list<array<string,mixed>> */
    public static function options(): array
    {
        return Database::fetchAll("SELECT id, codigo, nombre FROM cursos WHERE estado = 'activo' ORDER BY nombre");
    }

    /** @return list<array<string,mixed>> */
    public static function programsOf(int $cursoId): array
    {
        return Database::fetchAll(
            "SELECT pe.*, p.nombre AS programa
             FROM plan_estudios pe
             JOIN programas p ON p.id = pe.programa_id
             WHERE pe.curso_id = ? AND pe.estado = 'activo'
             ORDER BY p.nombre",
            [$cursoId]
        );
    }
}
