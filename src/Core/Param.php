<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Parámetros configurables del sistema (tabla parametros).
 * Los umbrales de negocio nunca se escriben directamente en el código.
 */
final class Param
{
    /** @var array<string,mixed>|null */
    private static ?array $cache = null;

    /** @return array<string,mixed> */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $values = [];

        try {
            $rows = Database::fetchAll('SELECT clave, valor, tipo FROM parametros');
            foreach ($rows as $row) {
                $values[(string) $row['clave']] = self::cast((string) $row['valor'], (string) $row['tipo']);
            }
        } catch (Throwable) {
            $values = [];
        }

        self::$cache = $values;

        return self::$cache;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $values = self::all();

        return array_key_exists($key, $values) ? $values[$key] : $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        return (int) self::get($key, $default);
    }

    public static function float(string $key, float $default = 0.0): float
    {
        return (float) self::get($key, $default);
    }

    public static function bool(string $key, bool $default = false): bool
    {
        return (bool) self::get($key, $default);
    }

    public static function string(string $key, string $default = ''): string
    {
        return (string) self::get($key, $default);
    }

    public static function refresh(): void
    {
        self::$cache = null;
    }

    private static function cast(string $value, string $type): mixed
    {
        return match ($type) {
            'int' => (int) $value,
            'decimal' => (float) $value,
            'bool' => in_array(strtolower($value), ['1', 'true', 'si', 'sí', 'yes', 'on'], true),
            'json' => json_decode($value, true) ?? [],
            default => $value,
        };
    }
}
