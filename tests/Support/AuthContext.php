<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Session;
use ReflectionClass;
use RuntimeException;

/**
 * Establece un contexto autenticado para probar servicios que dependen de
 * Auth (docente propietario, alcance por sede, permisos) sin pasar por HTTP.
 */
final class AuthContext
{
    public static function login(int $userId): void
    {
        $_SESSION = [];
        Session::set('_user_id', $userId);
        self::clearPermissions();
        Auth::boot();

        if (!Auth::check()) {
            throw new RuntimeException('No se pudo autenticar el usuario de prueba ' . $userId);
        }
    }

    public static function clear(): void
    {
        $_SESSION = [];
        self::setStatic('user', null);
        self::clearPermissions();
    }

    public static function userRow(int $userId): array
    {
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
            [$userId]
        );

        if ($user === null) {
            throw new RuntimeException('El usuario de prueba no existe: ' . $userId);
        }

        return $user;
    }

    private static function clearPermissions(): void
    {
        self::setStatic('permissions', null);
    }

    private static function setStatic(string $property, mixed $value): void
    {
        $reflection = new ReflectionClass(Auth::class);
        $target = $reflection->getProperty($property);
        $target->setAccessible(true);
        $target->setValue(null, $value);
    }
}
