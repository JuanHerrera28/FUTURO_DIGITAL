<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Autenticación, bloqueo por intentos fallidos, permisos y alcance por sede.
 */
final class Auth
{
    /** @var array<string,mixed>|null */
    private static ?array $user = null;

    /** @var list<string>|null */
    private static ?array $permissions = null;

    public static function boot(): void
    {
        $id = Session::get('_user_id');
        if ($id === null) {
            return;
        }

        $user = Database::fetch(
            "SELECT u.id, u.persona_id, u.rol_id, u.sede_id, u.username, u.debe_cambiar_password,
                    u.estado, u.ultimo_acceso, p.nombres, p.apellidos, p.email, p.numero_documento,
                    CONCAT_WS(' ', p.nombres, p.apellidos) AS nombre,
                    r.nombre AS rol, s.nombre AS sede, s.codigo AS sede_codigo
             FROM usuarios u
             JOIN personas p ON p.id = u.persona_id
             JOIN roles r ON r.id = u.rol_id
             LEFT JOIN sedes s ON s.id = u.sede_id
             WHERE u.id = ?
             LIMIT 1",
            [(int) $id]
        );

        if ($user === null || (string) $user['estado'] !== 'activo') {
            Session::remove('_user_id');
            return;
        }

        self::$user = $user;
    }

    /**
     * Verifica credenciales aplicando bloqueo temporal.
     *
     * @return array<string,mixed>
     */
    public static function attempt(string $username, string $password): array
    {
        $username = trim($username);
        $user = Database::fetch(
            "SELECT u.*, CONCAT_WS(' ', p.nombres, p.apellidos) AS nombre
             FROM usuarios u
             JOIN personas p ON p.id = u.persona_id
             WHERE u.username = ?
             LIMIT 1",
            [$username]
        );

        if ($user === null) {
            self::recordAttempt($username, false);
            throw new DomainException('', 'Usuario o contraseña incorrectos.');
        }

        if ((string) $user['estado'] !== 'activo') {
            self::recordAttempt($username, false);
            throw new DomainException('', 'La cuenta está inactiva. Contacte al administrador.');
        }

        if ($user['bloqueado_hasta'] !== null && strtotime((string) $user['bloqueado_hasta']) > time()) {
            self::recordAttempt($username, false);
            throw new DomainException('', 'La cuenta está bloqueada temporalmente hasta las ' . date('H:i', (int) strtotime((string) $user['bloqueado_hasta'])) . '.');
        }

        if (!password_verify($password, (string) $user['password_hash'])) {
            $attempts = (int) $user['intentos_fallidos'] + 1;
            $max = max(1, Param::int('intentos_fallidos', 5));
            $minutes = max(1, Param::int('bloqueo_minutos', 15));
            $blockedUntil = $attempts >= $max ? date('Y-m-d H:i:s', time() + ($minutes * 60)) : null;

            Database::update('usuarios', [
                'intentos_fallidos' => $attempts,
                'bloqueado_hasta' => $blockedUntil,
            ], ['id' => (int) $user['id']]);

            Audit::log('login_fallido', 'seguridad', 'usuarios', (int) $user['id'], null, [
                'intentos_fallidos' => $attempts,
                'bloqueado_hasta' => $blockedUntil,
            ]);

            self::recordAttempt($username, false);

            if ($blockedUntil !== null) {
                throw new DomainException('', 'Se superaron los ' . $max . ' intentos fallidos. La cuenta queda bloqueada por ' . $minutes . ' minutos.');
            }

            throw new DomainException('', 'Usuario o contraseña incorrectos. Intento ' . $attempts . ' de ' . $max . '.');
        }

        Database::update('usuarios', [
            'intentos_fallidos' => 0,
            'bloqueado_hasta' => null,
            'ultimo_acceso' => date('Y-m-d H:i:s'),
        ], ['id' => (int) $user['id']]);

        self::recordAttempt($username, true);
        self::login($user);

        return self::$user ?? $user;
    }

    /** @param array<string,mixed> $user */
    public static function login(array $user): void
    {
        Session::regenerate();
        Session::set('_user_id', (int) $user['id']);
        Session::rotateToken();
        self::$permissions = null;
        self::boot();

        Audit::log('login', 'seguridad', 'usuarios', (int) $user['id']);
    }

    public static function logout(): void
    {
        if (self::$user !== null) {
            Audit::log('logout', 'seguridad', 'usuarios', (int) self::$user['id']);
        }

        self::$user = null;
        self::$permissions = null;
        Session::destroy();
    }

    /** @return array<string,mixed>|null */
    public static function user(): ?array
    {
        return self::$user;
    }

    public static function id(): ?int
    {
        return self::$user === null ? null : (int) self::$user['id'];
    }

    public static function check(): bool
    {
        return self::$user !== null;
    }

    public static function role(): string
    {
        return (string) (self::$user['rol'] ?? '');
    }

    public static function roleId(): int
    {
        return (int) (self::$user['rol_id'] ?? 0);
    }

    public static function is(string $role): bool
    {
        return self::role() === $role;
    }

    public static function isDocente(): bool
    {
        return self::roleId() === 6;
    }

    public static function isEstudiante(): bool
    {
        return self::roleId() === 7;
    }

    public static function sedeId(): ?int
    {
        return self::$user === null || self::$user['sede_id'] === null ? null : (int) self::$user['sede_id'];
    }

    public static function sedeNombre(): string
    {
        return (string) (self::$user['sede'] ?? 'Todas');
    }

    /**
     * Alcance de sede. Secretaría (rol 2) solo puede operar en su sede.
     * Los demás roles conservan el alcance definido por sus permisos.
     */
    public static function sedeScope(): ?int
    {
        if (self::roleId() === 2) {
            return self::sedeId();
        }

        return null;
    }

    public static function canAccessSede(?int $sedeId): bool
    {
        $scope = self::sedeScope();

        return $scope === null || $sedeId === null || $scope === $sedeId;
    }

    public static function can(string $permission): bool
    {
        if (!self::check()) {
            return false;
        }

        if (self::$permissions === null) {
            try {
                $rows = Database::fetchAll(
                    'SELECT p.clave FROM permisos p JOIN rol_permiso rp ON rp.permiso_id = p.id WHERE rp.rol_id = ?',
                    [self::roleId()]
                );
                self::$permissions = array_map(static fn (array $row): string => (string) $row['clave'], $rows);
            } catch (Throwable) {
                self::$permissions = [];
            }
        }

        return in_array($permission, self::$permissions, true);
    }

    public static function requirePermission(string $permission): void
    {
        if (!self::can($permission)) {
            throw new HttpException(403, 'No tiene permiso para realizar esta acción.');
        }
    }

    public static function mustChangePassword(): bool
    {
        return self::$user !== null && (int) self::$user['debe_cambiar_password'] === 1;
    }

    public static function changePassword(string $current, string $new): void
    {
        if (!self::check()) {
            throw new HttpException(401, 'Debe iniciar sesión.');
        }

        $user = Database::fetch('SELECT id, password_hash FROM usuarios WHERE id = ?', [self::id()]);

        if ($user === null || !password_verify($current, (string) $user['password_hash'])) {
            throw new DomainException('', 'La contraseña actual no es correcta.');
        }

        if ($current === $new) {
            throw new DomainException('', 'La nueva contraseña debe ser diferente a la actual.');
        }

        $validator = new Validator(['password' => $new], ['password' => 'nueva contraseña']);
        $validator->rules(['password' => 'required|strong_password']);

        if ($validator->fails()) {
            throw new DomainException('', (string) $validator->first());
        }

        Database::update('usuarios', [
            'password_hash' => password_hash($new, PASSWORD_DEFAULT),
            'debe_cambiar_password' => 0,
            'updated_by' => self::id(),
        ], ['id' => self::id()]);

        Audit::log('cambio_password', 'seguridad', 'usuarios', self::id(), null, ['password' => 'actualizada']);

        Session::regenerate();
        Session::rotateToken();
        self::boot();
    }

    private static function recordAttempt(string $username, bool $success): void
    {
        try {
            Database::insert('intentos_login', [
                'username' => substr($username, 0, 80),
                'ip' => Request::ip(),
                'exito' => $success ? 1 : 0,
            ]);
        } catch (Throwable $e) {
            error_log('No se pudo registrar intento de login: ' . $e->getMessage());
        }
    }
}
