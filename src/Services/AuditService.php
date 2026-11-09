<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class AuditService
{
    /**
     * @param array<string,mixed> $filters
     * @return array{rows:list<array<string,mixed>>,total:int}
     */
    public static function paginate(array $filters, int $page = 1, int $perPage = 30): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['buscar'])) {
            $term = '%' . $filters['buscar'] . '%';
            $where[] = '(a.accion LIKE ? OR a.entidad LIKE ? OR a.entidad_id LIKE ? OR a.modulo LIKE ? OR p.nombres LIKE ? OR p.apellidos LIKE ?)';
            array_push($params, $term, $term, $term, $term, $term, $term);
        }

        if (!empty($filters['modulo'])) {
            $where[] = 'a.modulo = ?';
            $params[] = $filters['modulo'];
        }

        if (!empty($filters['accion'])) {
            $where[] = 'a.accion = ?';
            $params[] = $filters['accion'];
        }

        if (!empty($filters['desde'])) {
            $where[] = 'a.created_at >= ?';
            $params[] = $filters['desde'] . ' 00:00:00';
        }

        if (!empty($filters['hasta'])) {
            $where[] = 'a.created_at <= ?';
            $params[] = $filters['hasta'] . ' 23:59:59';
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) Database::scalar(
            "SELECT COUNT(*) FROM auditoria a
             LEFT JOIN usuarios u ON u.id = a.usuario_id
             LEFT JOIN personas p ON p.id = u.persona_id
             WHERE {$whereSql}",
            $params
        );

        $offset = max(0, ($page - 1) * $perPage);
        $rows = Database::fetchAll(
            "SELECT a.*, u.username,
                    CONCAT_WS(' ', p.nombres, p.apellidos) AS usuario,
                    r.nombre AS rol
             FROM auditoria a
             LEFT JOIN usuarios u ON u.id = a.usuario_id
             LEFT JOIN personas p ON p.id = u.persona_id
             LEFT JOIN roles r ON r.id = u.rol_id
             WHERE {$whereSql}
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /** @return list<string> */
    public static function modules(): array
    {
        $rows = Database::fetchAll('SELECT DISTINCT modulo FROM auditoria ORDER BY modulo');

        return array_map(static fn (array $row): string => (string) $row['modulo'], $rows);
    }

    /** @return list<string> */
    public static function actions(): array
    {
        $rows = Database::fetchAll('SELECT DISTINCT accion FROM auditoria ORDER BY accion');

        return array_map(static fn (array $row): string => (string) $row['accion'], $rows);
    }
}
