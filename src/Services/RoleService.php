<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\DomainException;

final class RoleService
{
    /** @return list<array<string,mixed>> */
    public static function all(): array
    {
        return Database::fetchAll(
            "SELECT r.*,
                    (SELECT COUNT(*) FROM usuarios u WHERE u.rol_id = r.id) AS usuarios,
                    (SELECT COUNT(*) FROM rol_permiso rp WHERE rp.rol_id = r.id) AS permisos
             FROM roles r
             ORDER BY r.id"
        );
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM roles WHERE id = ?', [$id]);
    }

    /**
     * Permisos agrupados por prefijo (módulo).
     *
     * @return array<string,list<array<string,mixed>>>
     */
    public static function permissionsByModule(): array
    {
        $rows = Database::fetchAll('SELECT * FROM permisos ORDER BY clave');
        $groups = [];

        foreach ($rows as $row) {
            $prefix = str_contains((string) $row['clave'], '.')
                ? explode('.', (string) $row['clave'])[0]
                : 'general';

            $labels = [
                'dashboard' => 'Panel',
                'students' => 'Estudiantes',
                'teachers' => 'Docentes',
                'programs' => 'Programas y plan de estudios',
                'courses' => 'Cursos',
                'periods' => 'Periodos',
                'classrooms' => 'Aulas',
                'groups' => 'Grupos',
                'enrollment' => 'Matrículas e inscripciones',
                'grades' => 'Calificaciones',
                'attendance' => 'Asistencia',
                'payments' => 'Pagos y cartera',
                'alerts' => 'Alertas',
                'reports' => 'Informes',
                'users' => 'Usuarios',
                'roles' => 'Roles y permisos',
                'params' => 'Parámetros',
                'audit' => 'Auditoría',
                'portal' => 'Portal del estudiante',
            ];

            $groups[$labels[$prefix] ?? ucfirst($prefix)][] = $row;
        }

        return $groups;
    }

    /** @return list<int> */
    public static function permissionIds(int $rolId): array
    {
        $rows = Database::fetchAll('SELECT permiso_id FROM rol_permiso WHERE rol_id = ?', [$rolId]);

        return array_map(static fn (array $row): int => (int) $row['permiso_id'], $rows);
    }

    /** @param array<int,mixed> $permissionIds */
    public static function updatePermissions(int $rolId, array $permissionIds): void
    {
        $role = self::find($rolId);

        if ($role === null) {
            throw new DomainException('RF-20', 'El rol no existe.');
        }

        $before = self::permissionIds($rolId);
        $valid = Database::fetchAll('SELECT id FROM permisos');
        $validIds = array_map(static fn (array $row): int => (int) $row['id'], $valid);

        $newIds = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $permissionIds),
            static fn (int $id): bool => in_array($id, $validIds, true)
        )));

        Database::transaction(static function () use ($rolId, $newIds, $before): void {
            Database::execute('DELETE FROM rol_permiso WHERE rol_id = ?', [$rolId]);

            foreach ($newIds as $permissionId) {
                Database::insert('rol_permiso', [
                    'rol_id' => $rolId,
                    'permiso_id' => $permissionId,
                ]);
            }

            Audit::log('permisos_rol_actualizados', 'roles', 'roles', $rolId, [
                'permisos' => $before,
            ], [
                'permisos' => $newIds,
            ]);
        });
    }

    public static function create(string $nombre, string $descripcion): int
    {
        $nombre = trim($nombre);

        if ($nombre === '') {
            throw new DomainException('RF-20', 'El nombre del rol es obligatorio.');
        }

        if (Database::scalar('SELECT COUNT(*) FROM roles WHERE nombre = ?', [$nombre]) > 0) {
            throw new DomainException('RF-20', 'Ya existe un rol con ese nombre.');
        }

        $id = Database::insert('roles', [
            'nombre' => substr($nombre, 0, 60),
            'descripcion' => $descripcion !== '' ? substr(trim($descripcion), 0, 255) : null,
            'estado' => 'activo',
        ]);

        Audit::log('rol_creado', 'roles', 'roles', $id, null, ['nombre' => $nombre]);

        return $id;
    }

    public static function update(int $id, string $nombre, string $descripcion, string $estado): void
    {
        $before = self::find($id);

        if ($before === null) {
            throw new DomainException('RF-20', 'El rol no existe.');
        }

        if ((int) $before['id'] === 1 && ($estado !== 'activo' || $nombre !== (string) $before['nombre'])) {
            throw new DomainException('RF-20', 'El rol Administrador no puede desactivarse ni cambiar de nombre.');
        }

        $nombre = trim($nombre);

        if (Database::scalar('SELECT COUNT(*) FROM roles WHERE nombre = ? AND id <> ?', [$nombre, $id]) > 0) {
            throw new DomainException('RF-20', 'Ya existe otro rol con ese nombre.');
        }

        Database::update('roles', [
            'nombre' => substr($nombre, 0, 60),
            'descripcion' => $descripcion !== '' ? substr(trim($descripcion), 0, 255) : null,
            'estado' => $estado === 'inactivo' ? 'inactivo' : 'activo',
        ], ['id' => $id]);

        Audit::log('rol_actualizado', 'roles', 'roles', $id, [
            'nombre' => $before['nombre'],
            'estado' => $before['estado'],
        ], [
            'nombre' => $nombre,
            'estado' => $estado,
        ]);
    }
}
