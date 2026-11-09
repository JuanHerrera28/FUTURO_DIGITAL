<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\DomainException;

final class UserService
{
    /**
     * Crea un usuario para una persona existente y devuelve las credenciales
     * temporales para mostrarlas una única vez.
     *
     * @return array{username:string,password:string,rol:string,sede:?string,mensaje:string}
     */
    public static function createForPersona(
        int $personaId,
        int $rolId,
        ?int $sedeId,
        ?string $usernameBase = null,
        string $mensaje = 'Entregue estas credenciales a la persona.'
    ): array {
        $persona = Database::fetch('SELECT * FROM personas WHERE id = ?', [$personaId]);
        if ($persona === null) {
            throw new DomainException('RF-18', 'La persona no existe.');
        }

        $role = Database::fetch('SELECT * FROM roles WHERE id = ? AND estado = ?', [$rolId, 'activo']);
        if ($role === null) {
            throw new DomainException('RF-20', 'El rol seleccionado no existe o está inactivo.');
        }

        if ($sedeId !== null) {
            $sede = Database::fetch('SELECT * FROM sedes WHERE id = ? AND estado = ?', [$sedeId, 'activo']);
            if ($sede === null) {
                throw new DomainException('RF-20', 'La sede seleccionada no existe o está inactiva.');
            }
        }

        $existing = Database::fetch('SELECT id FROM usuarios WHERE persona_id = ? AND rol_id = ? LIMIT 1', [$personaId, $rolId]);
        if ($existing !== null) {
            throw new DomainException('RF-18', 'La persona ya tiene un usuario con ese rol.');
        }

        $base = $usernameBase !== null && trim($usernameBase) !== ''
            ? $usernameBase
            : (string) $persona['numero_documento'];
        $username = CodeService::username($base);
        $password = generar_password(10);

        $userId = Database::insert('usuarios', [
            'persona_id' => $personaId,
            'rol_id' => $rolId,
            'sede_id' => $sedeId,
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'debe_cambiar_password' => 1,
            'estado' => 'activo',
            'created_by' => Auth::id(),
        ]);

        $sedeNombre = $sedeId === null ? null : (string) Database::scalar('SELECT nombre FROM sedes WHERE id = ?', [$sedeId]);

        Audit::log('usuario_creado', 'usuarios', 'usuarios', $userId, null, [
            'persona_id' => $personaId,
            'username' => $username,
            'rol_id' => $rolId,
            'sede_id' => $sedeId,
        ]);

        return [
            'username' => $username,
            'password' => $password,
            'rol' => (string) $role['nombre'],
            'sede' => $sedeNombre,
            'mensaje' => $mensaje,
        ];
    }

    /**
     * @param array<string,mixed> $personaData
     * @return array{user_id:int,credentials:array<string,mixed>}
     */
    public static function createStaff(array $personaData, int $rolId, ?int $sedeId): array
    {
        return Database::transaction(static function () use ($personaData, $rolId, $sedeId): array {
            $personaId = PersonService::create($personaData);
            $credentials = self::createForPersona(
                $personaId,
                $rolId,
                $sedeId,
                (string) ($personaData['numero_documento'] ?? ''),
                'Entregue estas credenciales al usuario administrativo.'
            );

            return [
                'user_id' => (int) Database::scalar('SELECT id FROM usuarios WHERE username = ?', [$credentials['username']]),
                'credentials' => $credentials,
            ];
        });
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{rows:list<array<string,mixed>>,total:int,page:int,perPage:int}
     */
    public static function paginate(array $filters, int $page = 1, int $perPage = 20): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['buscar'])) {
            $term = '%' . $filters['buscar'] . '%';
            $where[] = '(p.nombres LIKE ? OR p.apellidos LIKE ? OR p.numero_documento LIKE ? OR u.username LIKE ?)';
            array_push($params, $term, $term, $term, $term);
        }

        if (!empty($filters['rol_id'])) {
            $where[] = 'u.rol_id = ?';
            $params[] = (int) $filters['rol_id'];
        }

        if (!empty($filters['estado'])) {
            $where[] = 'u.estado = ?';
            $params[] = $filters['estado'];
        }

        if (!empty($filters['sede_id'])) {
            $where[] = 'u.sede_id = ?';
            $params[] = (int) $filters['sede_id'];
        }

        $scope = Auth::sedeScope();
        if ($scope !== null) {
            $where[] = '(u.sede_id = ? OR u.sede_id IS NULL)';
            $params[] = $scope;
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) Database::scalar(
            "SELECT COUNT(*) FROM usuarios u JOIN personas p ON p.id = u.persona_id WHERE {$whereSql}",
            $params
        );

        $offset = max(0, ($page - 1) * $perPage);

        $rows = Database::fetchAll(
            "SELECT u.*, p.nombres, p.apellidos, p.numero_documento, p.email,
                    r.nombre AS rol, s.nombre AS sede
             FROM usuarios u
             JOIN personas p ON p.id = u.persona_id
             JOIN roles r ON r.id = u.rol_id
             LEFT JOIN sedes s ON s.id = u.sede_id
             WHERE {$whereSql}
             ORDER BY u.estado, p.apellidos, p.nombres
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'perPage' => $perPage];
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::fetch(
            "SELECT u.*, p.nombres, p.apellidos, p.numero_documento, p.email, p.telefono,
                    r.nombre AS rol, s.nombre AS sede
             FROM usuarios u
             JOIN personas p ON p.id = u.persona_id
             JOIN roles r ON r.id = u.rol_id
             LEFT JOIN sedes s ON s.id = u.sede_id
             WHERE u.id = ?",
            [$id]
        );
    }

    public static function update(int $id, int $rolId, ?int $sedeId, string $estado): void
    {
        $before = self::find($id);
        if ($before === null) {
            throw new DomainException('RF-20', 'El usuario no existe.');
        }

        if ($id === Auth::id() && ($estado !== 'activo' || $rolId !== (int) $before['rol_id'])) {
            throw new DomainException('RF-20', 'No puede desactivar su propio usuario ni cambiar su propio rol.');
        }

        $role = Database::fetch('SELECT * FROM roles WHERE id = ? AND estado = ?', [$rolId, 'activo']);
        if ($role === null) {
            throw new DomainException('RF-20', 'El rol seleccionado no es válido.');
        }

        if ($sedeId !== null && Database::scalar('SELECT COUNT(*) FROM sedes WHERE id = ? AND estado = ?', [$sedeId, 'activo']) === 0) {
            throw new DomainException('RF-20', 'La sede seleccionada no es válida.');
        }

        if ((int) $before['rol_id'] === 1 && ($rolId !== 1 || $estado !== 'activo')) {
            $otherAdmins = (int) Database::scalar(
                "SELECT COUNT(*) FROM usuarios WHERE rol_id = 1 AND estado = 'activo' AND id <> ?",
                [$id]
            );
            if ($otherAdmins === 0) {
                throw new DomainException('RF-20', 'Debe existir al menos un administrador activo.');
            }
        }

        Database::update('usuarios', [
            'rol_id' => $rolId,
            'sede_id' => $sedeId,
            'estado' => $estado,
            'updated_by' => Auth::id(),
        ], ['id' => $id]);

        Audit::log('usuario_actualizado', 'usuarios', 'usuarios', $id, [
            'rol_id' => $before['rol_id'],
            'sede_id' => $before['sede_id'],
            'estado' => $before['estado'],
        ], [
            'rol_id' => $rolId,
            'sede_id' => $sedeId,
            'estado' => $estado,
        ]);
    }

    /**
     * @return array{username:string,password:string,rol:string,sede:?string,mensaje:string}
     */
    public static function resetPassword(int $id): array
    {
        $user = self::find($id);
        if ($user === null) {
            throw new DomainException('RF-19', 'El usuario no existe.');
        }

        $password = generar_password(10);

        Database::update('usuarios', [
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'debe_cambiar_password' => 1,
            'intentos_fallidos' => 0,
            'bloqueado_hasta' => null,
            'estado' => 'activo',
            'updated_by' => Auth::id(),
        ], ['id' => $id]);

        Audit::log('password_restablecida', 'usuarios', 'usuarios', $id, null, ['debe_cambiar_password' => 1]);

        return [
            'username' => (string) $user['username'],
            'password' => $password,
            'rol' => (string) $user['rol'],
            'sede' => $user['sede'] === null ? null : (string) $user['sede'],
            'mensaje' => 'Contraseña temporal restablecida. Se mostrará una sola vez.',
        ];
    }

    /** @return list<array<string,mixed>> */
    public static function roles(bool $staffOnly = false): array
    {
        $sql = 'SELECT * FROM roles WHERE estado = ?';
        if ($staffOnly) {
            $sql .= ' AND id NOT IN (6,7)';
        }
        $sql .= ' ORDER BY id';

        return Database::fetchAll($sql, ['activo']);
    }
}
