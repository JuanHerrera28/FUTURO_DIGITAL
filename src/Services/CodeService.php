<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Generación de códigos legibles y únicos. Se asigna después del INSERT
 * usando el id autoincremental, dentro de la misma transacción.
 */
final class CodeService
{
    public static function assign(string $table, int $id, string $prefix, bool $withYear = true, int $padding = 5): string
    {
        $code = $prefix . ($withYear ? '-' . date('Y') : '') . '-' . str_pad((string) $id, $padding, '0', STR_PAD_LEFT);
        Database::update($table, ['codigo' => $code], ['id' => $id]);

        return $code;
    }

    public static function receipt(int $paymentId): string
    {
        $number = 'REC-' . date('Y') . '-' . str_pad((string) $paymentId, 6, '0', STR_PAD_LEFT);
        Database::update('comprobantes', ['numero' => $number], ['pago_id' => $paymentId]);

        return $number;
    }

    public static function username(string $base): string
    {
        $base = mb_strtolower(trim($base));
        $base = preg_replace('/[^a-z0-9._-]/', '', $base) ?: 'usuario';

        $candidate = $base;
        $suffix = 1;

        while (Database::scalar('SELECT COUNT(*) FROM usuarios WHERE username = ?', [$candidate]) > 0) {
            $suffix++;
            $candidate = $base . '-' . $suffix;
        }

        return $candidate;
    }
}
