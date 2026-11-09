<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Registro de auditoría de cambios críticos.
 */
final class Audit
{
    /**
     * @param array<string,mixed>|null $anterior
     * @param array<string,mixed>|null $nuevo
     */
    public static function log(
        string $accion,
        string $modulo,
        string $entidad,
        int|string|null $entidadId,
        ?array $anterior = null,
        ?array $nuevo = null
    ): void {
        try {
            Database::insert('auditoria', [
                'usuario_id' => Auth::id(),
                'accion' => substr($accion, 0, 30),
                'modulo' => substr($modulo, 0, 60),
                'entidad' => substr($entidad, 0, 60),
                'entidad_id' => $entidadId === null ? null : (string) $entidadId,
                'valor_anterior' => $anterior === null ? null : json_encode($anterior, JSON_UNESCAPED_UNICODE),
                'valor_nuevo' => $nuevo === null ? null : json_encode($nuevo, JSON_UNESCAPED_UNICODE),
                'ip' => Request::ip(),
                'user_agent' => Request::userAgent(),
            ]);
        } catch (Throwable $e) {
            error_log('Auditoría no registrada: ' . $e->getMessage());
        }
    }
}
