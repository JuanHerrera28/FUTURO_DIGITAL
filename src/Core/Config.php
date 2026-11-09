<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Acceso a configuración con notación de puntos: app.debug
 */
final class Config
{
    /** @var array<string,mixed> */
    private static array $items = [];

    /** @param array<string,mixed> $items */
    public static function setAll(array $items): void
    {
        self::$items = $items;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$items;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $target = &self::$items;

        foreach ($segments as $segment) {
            if (!isset($target[$segment]) || !is_array($target[$segment])) {
                $target[$segment] = [];
            }
            $target = &$target[$segment];
        }

        $target = $value;
    }
}
