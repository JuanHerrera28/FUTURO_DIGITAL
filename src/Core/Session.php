<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Sesión segura con expiración por inactividad y mensajes flash.
 */
final class Session
{
    public static function start(array $config): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_name((string) ($config['name'] ?? 'futuro_digital_sid'));

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => (bool) ($config['secure'] ?? false),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();

        $minutes = max(1, (int) ($config['lifetime'] ?? 30));
        $now = time();
        $last = isset($_SESSION['_last_activity']) ? (int) $_SESSION['_last_activity'] : null;

        if ($last !== null && ($now - $last) > ($minutes * 60)) {
            $_SESSION = [];
            session_regenerate_id(true);
            $_SESSION['_last_activity'] = $now;
            $_SESSION['_flash']['warning'][] = 'La sesión expiró por inactividad. Ingrese nuevamente.';
            return;
        }

        $_SESSION['_last_activity'] = $now;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, $_SESSION);
    }

    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function pull(string $key, mixed $default = null): mixed
    {
        if (!array_key_exists($key, $_SESSION)) {
            return $default;
        }

        $value = $_SESSION[$key];
        unset($_SESSION[$key]);

        return $value;
    }

    public static function flash(string $type, mixed $value): void
    {
        $_SESSION['_flash'][$type][] = $value;
    }

    /** @return array<string,list<mixed>> */
    public static function flashes(): array
    {
        $flashes = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);

        return is_array($flashes) ? $flashes : [];
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool) $params['secure'],
                (bool) $params['httponly']
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function token(): string
    {
        if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf'];
    }

    public static function rotateToken(): void
    {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
}
