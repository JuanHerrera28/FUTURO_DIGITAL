<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Acceso saneado a los datos de la petición HTTP.
 */
final class Request
{
    public static function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    public static function isPost(): bool
    {
        return self::method() === 'POST';
    }

    public static function path(): string
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = (string) (parse_url($uri, PHP_URL_PATH) ?: '/');

        $basePath = (string) (parse_url((string) Config::get('app.url', ''), PHP_URL_PATH) ?: '');
        if ($basePath !== '' && $basePath !== '/' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath));
        }

        if (str_starts_with($path, '/index.php')) {
            $path = substr($path, strlen('/index.php')) ?: '/';
        }

        $path = '/' . trim($path, '/');

        return $path === '//' || $path === '' ? '/' : $path;
    }

    /** @return array<string,mixed> */
    public static function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    public static function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $_POST)) {
            return $_POST[$key];
        }

        return $_GET[$key] ?? $default;
    }

    public static function string(string $key, string $default = ''): string
    {
        $value = self::input($key, $default);

        return is_string($value) ? trim($value) : $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::input($key, $default);

        return filter_var($value, FILTER_VALIDATE_INT) !== false ? (int) $value : $default;
    }

    public static function decimal(string $key, float $default = 0.0): float
    {
        $value = self::input($key, $default);

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (!is_string($value)) {
            return $default;
        }

        $value = trim($value);

        if ($value === '') {
            return $default;
        }

        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        } elseif (str_contains($value, ',')) {
            $value = str_replace(',', '.', $value);
        } elseif (substr_count($value, '.') > 1) {
            $value = str_replace('.', '', $value);
        }

        return is_numeric($value) ? (float) $value : $default;
    }

    public static function bool(string $key): bool
    {
        $value = self::input($key, null);

        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'on', 'yes', 'si', 'sí'], true);
    }

    /**
     * @param list<string> $keys
     * @return array<string,mixed>
     */
    public static function only(array $keys): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = self::input($key);
        }

        return $result;
    }

    public static function ip(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    }

    public static function userAgent(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    public static function referer(): string
    {
        return (string) ($_SERVER['HTTP_REFERER'] ?? '');
    }
}
