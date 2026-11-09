<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\DomainException;

final class ClassroomService
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
            $where[] = 'a.nombre LIKE ?';
            $params[] = $term;
        }

        if (!empty($filters['sede_id'])) {
            $where[] = 'a.sede_id = ?';
            $params[] = (int) $filters['sede_id'];
        }

        if (!empty($filters['estado'])) {
            $where[] = 'a.estado = ?';
            $params[] = $filters['estado'];
        }

        $scope = Auth::sedeScope();
        if ($scope !== null) {
            $where[] = 'a.sede_id = ?';
            $params[] = $scope;
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) Database::scalar("SELECT COUNT(*) FROM aulas a WHERE {$whereSql}", $params);
        $offset = max(0, ($page - 1) * $perPage);

        $rows = Database::fetchAll(
            "SELECT a.*, s.nombre AS sede,
                    (SELECT COUNT(*) FROM grupos g WHERE g.aula_id = a.id AND g.estado = 'activo') AS grupos
             FROM aulas a
             JOIN sedes s ON s.id = a.sede_id
             WHERE {$whereSql}
             ORDER BY s.nombre, a.nombre
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::fetch(
            'SELECT a.*, s.nombre AS sede FROM aulas a JOIN sedes s ON s.id = a.sede_id WHERE a.id = ?',
            [$id]
        );
    }

    /** @param array<string,mixed> $data */
    public static function create(array $data): int
    {
        $sedeId = (int) ($data['sede_id'] ?? 0);
        $nombre = trim((string) ($data['nombre'] ?? ''));
        $capacidad = (int) ($data['capacidad'] ?? 0);

        if ($sedeId <= 0 || $nombre === '') {
            throw new DomainException('RF-07', 'La sede y el nombre del aula son obligatorios.');
        }

        if ($capacidad <= 0) {
            throw new DomainException('RF-07', 'La capacidad del aula debe ser mayor que cero.');
        }

        if (!Auth::canAccessSede($sedeId)) {
            throw new DomainException('RN-19', 'Solo puede crear aulas en su sede.');
        }

        if (Database::scalar('SELECT COUNT(*) FROM aulas WHERE sede_id = ? AND nombre = ?', [$sedeId, $nombre]) > 0) {
            throw new DomainException('RF-07', 'Ya existe un aula con ese nombre en la sede.');
        }

        $id = Database::insert('aulas', [
            'sede_id' => $sedeId,
            'nombre' => $nombre,
            'capacidad' => $capacidad,
            'estado' => 'activo',
        ]);

        Audit::log('aula_creada', 'aulas', 'aulas', $id, null, ['sede_id' => $sedeId, 'nombre' => $nombre, 'capacidad' => $capacidad]);

        return $id;
    }

    /** @param array<string,mixed> $data */
    public static function update(int $id, array $data): void
    {
        $before = self::find($id);
        if ($before === null) {
            throw new DomainException('RF-07', 'El aula no existe.');
        }

        if (!Auth::canAccessSede((int) $before['sede_id'])) {
            throw new DomainException('RN-19', 'Solo puede modificar aulas de su sede.');
        }

        $nombre = trim((string) ($data['nombre'] ?? $before['nombre']));
        $capacidad = (int) ($data['capacidad'] ?? $before['capacidad']);

        if ($capacidad <= 0) {
            throw new DomainException('RF-07', 'La capacidad del aula debe ser mayor que cero.');
        }

        $maxGroup = (int) Database::scalar(
            "SELECT COALESCE(MAX(capacidad_max), 0) FROM grupos WHERE aula_id = ? AND estado = 'activo'",
            [$id]
        );

        if ($capacidad < $maxGroup) {
            throw new DomainException('RN-03', 'La nueva capacidad del aula no puede ser menor que la capacidad máxima de sus grupos activos (' . $maxGroup . ').');
        }

        Database::update('aulas', [
            'nombre' => $nombre,
            'capacidad' => $capacidad,
            'estado' => (string) ($data['estado'] ?? $before['estado']),
        ], ['id' => $id]);

        Audit::log('aula_actualizada', 'aulas', 'aulas', $id, [
            'nombre' => $before['nombre'], 'capacidad' => $before['capacidad'], 'estado' => $before['estado'],
        ], ['nombre' => $nombre, 'capacidad' => $capacidad, 'estado' => (string) ($data['estado'] ?? $before['estado'])]);
    }

    /** @return list<array<string,mixed>> */
    public static function options(?int $sedeId = null): array
    {
        $sql = "SELECT a.id, a.nombre, a.capacidad, a.sede_id, s.nombre AS sede
                FROM aulas a JOIN sedes s ON s.id = a.sede_id
                WHERE a.estado = 'activo'";
        $params = [];

        if ($sedeId !== null) {
            $sql .= ' AND a.sede_id = ?';
            $params[] = $sedeId;
        }

        $sql .= ' ORDER BY s.nombre, a.nombre';

        return Database::fetchAll($sql, $params);
    }
}
