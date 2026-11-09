<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\DomainException;
use App\Core\Param;

final class ParamService
{
    /**
     * @return array<string,list<array<string,mixed>>>
     */
    public static function grouped(): array
    {
        $rows = Database::fetchAll('SELECT * FROM parametros ORDER BY grupo, clave');
        $groups = [];

        foreach ($rows as $row) {
            $groups[(string) $row['grupo']][] = $row;
        }

        return $groups;
    }

    /** @param array<string,mixed> $values clave => valor */
    public static function updateMany(array $values): int
    {
        $rows = Database::fetchAll('SELECT * FROM parametros');
        $byKey = [];

        foreach ($rows as $row) {
            $byKey[(string) $row['clave']] = $row;
        }

        $changes = [];

        foreach ($values as $key => $value) {
            $key = (string) $key;

            if (!isset($byKey[$key])) {
                continue;
            }

            $row = $byKey[$key];
            $type = (string) $row['tipo'];
            $normalized = self::normalize($type, $value, $key);

            if ((string) $row['valor'] === $normalized) {
                continue;
            }

            $changes[] = [
                'id' => (int) $row['id'],
                'clave' => $key,
                'anterior' => (string) $row['valor'],
                'nuevo' => $normalized,
            ];
        }

        if ($changes === []) {
            return 0;
        }

        Database::transaction(static function () use ($changes): void {
            foreach ($changes as $change) {
                Database::update('parametros', [
                    'valor' => $change['nuevo'],
                    'updated_by' => Auth::id(),
                ], ['id' => $change['id']]);
            }

            Audit::log('parametros_actualizados', 'parametros', 'parametros', null, null, [
                'cambios' => array_map(static fn (array $change): array => [
                    'clave' => $change['clave'],
                    'anterior' => $change['anterior'],
                    'nuevo' => $change['nuevo'],
                ], $changes),
            ]);
        });

        Param::refresh();

        return count($changes);
    }

    private static function normalize(string $type, mixed $value, string $key): string
    {
        $string = is_scalar($value) ? trim((string) $value) : '';

        return match ($type) {
            'int' => self::intValue($string, $key),
            'decimal' => self::decimalValue($string, $key),
            'bool' => in_array(strtolower($string), ['1', 'true', 'si', 'sí', 'yes', 'on'], true) ? '1' : '0',
            'json' => self::jsonValue($string, $key),
            default => $string,
        };
    }

    private static function intValue(string $value, string $key): string
    {
        if (!preg_match('/^-?\d+$/', $value)) {
            throw new DomainException('RF-21', 'El parámetro ' . $key . ' debe ser un número entero.');
        }

        return (string) (int) $value;
    }

    private static function decimalValue(string $value, string $key): string
    {
        $normalized = str_replace(',', '.', $value);

        if (!is_numeric($normalized)) {
            throw new DomainException('RF-21', 'El parámetro ' . $key . ' debe ser numérico.');
        }

        return rtrim(rtrim(number_format((float) $normalized, 4, '.', ''), '0'), '.') ?: '0';
    }

    private static function jsonValue(string $value, string $key): string
    {
        $decoded = json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new DomainException('RF-21', 'El parámetro ' . $key . ' debe contener un JSON válido.');
        }

        return (string) json_encode($decoded, JSON_UNESCAPED_UNICODE);
    }
}
