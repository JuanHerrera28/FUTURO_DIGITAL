<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Lector sencillo de archivos .env (clave=valor).
 */
final class Env
{
    /** @var array<string,string> */
    private static array $values = [];

    public static function load(string $path): void
    {
        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            self::$values[$key] = $value;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$values[$key] ?? $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        if (!array_key_exists($key, self::$values)) {
            return $default;
        }

        return in_array(strtolower((string) self::$values[$key]), ['1', 'true', 'yes', 'si', 'sí', 'on'], true);
    }
}
