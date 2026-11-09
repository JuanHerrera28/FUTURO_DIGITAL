<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\DomainException;
use App\Core\Param;

final class AlertService
{
    /**
     * Crea una alerta evitando duplicados abiertos para la misma inscripción
     * y tipo. Devuelve el id de la alerta nueva o de la existente.
     */
    public static function create(
        int $estudianteId,
        ?int $inscripcionId,
        string $tipo,
        string $nivel,
        string $mensaje
    ): int {
        if (!in_array($tipo, ['inasistencia', 'bajo_rendimiento', 'mora'], true)) {
            throw new DomainException('RF-17', 'El tipo de alerta no es válido.');
        }

        if (!in_array($nivel, ['informativa', 'advertencia', 'critica'], true)) {
            $nivel = 'advertencia';
        }

        $existing = Database::fetch(
            "SELECT id FROM alertas
             WHERE estudiante_id = ?
               AND tipo = ?
               AND estado = 'nueva'
               AND ((inscripcion_id IS NULL AND ? IS NULL) OR inscripcion_id = ?)
               AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
             ORDER BY id DESC LIMIT 1",
            [$estudianteId, $tipo, $inscripcionId, $inscripcionId ?? 0]
        );

        if ($existing !== null) {
            Database::update('alertas', [
                'nivel' => $nivel,
                'mensaje' => substr(trim($mensaje), 0, 255),
            ], ['id' => (int) $existing['id']]);

            return (int) $existing['id'];
        }

        $id = Database::insert('alertas', [
            'estudiante_id' => $estudianteId,
            'inscripcion_id' => $inscripcionId,
            'tipo' => $tipo,
            'nivel' => $nivel,
            'mensaje' => substr(trim($mensaje), 0, 255),
            'estado' => 'nueva',
        ]);

        Audit::log('alerta_creada', 'alertas', 'alertas', $id, null, [
            'estudiante_id' => $estudianteId,
            'inscripcion_id' => $inscripcionId,
            'tipo' => $tipo,
            'nivel' => $nivel,
        ]);

        return $id;
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{rows:list<array<string,mixed>>,total:int}
     */
    public static function paginate(array $filters, int $page = 1, int $perPage = 25): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['buscar'])) {
            $term = '%' . $filters['buscar'] . '%';
            $where[] = '(p.nombres LIKE ? OR p.apellidos LIKE ? OR p.numero_documento LIKE ? OR a.mensaje LIKE ?)';
            array_push($params, $term, $term, $term, $term);
        }

        if (!empty($filters['tipo'])) {
            $where[] = 'a.tipo = ?';
            $params[] = $filters['tipo'];
        }

        if (!empty($filters['nivel'])) {
            $where[] = 'a.nivel = ?';
            $params[] = $filters['nivel'];
        }

        if (!empty($filters['estado'])) {
            $where[] = 'a.estado = ?';
            $params[] = $filters['estado'];
        }

        if (!empty($filters['sede_id'])) {
            $where[] = 'e.sede_id = ?';
            $params[] = (int) $filters['sede_id'];
        }

        $scope = Auth::sedeScope();
        if ($scope !== null) {
            $where[] = 'e.sede_id = ?';
            $params[] = $scope;
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) Database::scalar(
            "SELECT COUNT(*) FROM alertas a
             JOIN estudiantes e ON e.id = a.estudiante_id
             JOIN personas p ON p.id = e.persona_id
             WHERE {$whereSql}",
            $params
        );

        $offset = max(0, ($page - 1) * $perPage);
        $rows = Database::fetchAll(
            "SELECT a.*, e.codigo AS estudiante_codigo, e.sede_id,
                    p.nombres, p.apellidos, p.numero_documento,
                    s.nombre AS sede, c.nombre AS curso, g.codigo AS grupo,
                    CONCAT_WS(' ', ua.nombres, ua.apellidos) AS atendida_por_nombre
             FROM alertas a
             JOIN estudiantes e ON e.id = a.estudiante_id
             JOIN personas p ON p.id = e.persona_id
             JOIN sedes s ON s.id = e.sede_id
             LEFT JOIN inscripciones i ON i.id = a.inscripcion_id
             LEFT JOIN grupos g ON g.id = i.grupo_id
             LEFT JOIN cursos c ON c.id = g.curso_id
             LEFT JOIN usuarios u ON u.id = a.atendida_por
             LEFT JOIN personas ua ON ua.id = u.persona_id
             WHERE {$whereSql}
             ORDER BY CASE a.estado WHEN 'nueva' THEN 0 ELSE 1 END,
                      CASE a.nivel WHEN 'critica' THEN 0 WHEN 'advertencia' THEN 1 ELSE 2 END,
                      a.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total];
    }

    public static function attend(int $id): void
    {
        $alert = Database::fetch('SELECT * FROM alertas WHERE id = ?', [$id]);

        if ($alert === null) {
            throw new DomainException('RF-17', 'La alerta no existe.');
        }

        if ((string) $alert['estado'] === 'atendida') {
            return;
        }

        $student = Database::fetch('SELECT sede_id FROM estudiantes WHERE id = ?', [(int) $alert['estudiante_id']]);
        if ($student !== null && !Auth::canAccessSede((int) $student['sede_id'])) {
            throw new DomainException('RN-19', 'Solo puede atender alertas de estudiantes de su sede.');
        }

        Database::update('alertas', [
            'estado' => 'atendida',
            'atendida_por' => Auth::id(),
            'atendida_at' => date('Y-m-d H:i:s'),
        ], ['id' => $id]);

        Audit::log('alerta_atendida', 'alertas', 'alertas', $id, ['estado' => $alert['estado']], ['estado' => 'atendida']);
    }

    /** @return list<array<string,mixed>> */
    public static function recent(int $limit = 6): array
    {
        $where = "a.estado = 'nueva'";
        $params = [];

        $scope = Auth::sedeScope();
        if ($scope !== null) {
            $where .= ' AND e.sede_id = ?';
            $params[] = $scope;
        }

        return Database::fetchAll(
            "SELECT a.*, CONCAT_WS(' ', p.nombres, p.apellidos) AS estudiante
             FROM alertas a
             JOIN estudiantes e ON e.id = a.estudiante_id
             JOIN personas p ON p.id = e.persona_id
             WHERE {$where}
             ORDER BY CASE a.nivel WHEN 'critica' THEN 0 WHEN 'advertencia' THEN 1 ELSE 2 END, a.created_at DESC
             LIMIT {$limit}",
            $params
        );
    }

    public static function openCount(): int
    {
        $where = "a.estado = 'nueva'";
        $params = [];

        $scope = Auth::sedeScope();
        if ($scope !== null) {
            $where .= ' AND e.sede_id = ?';
            $params[] = $scope;
        }

        return (int) Database::scalar(
            "SELECT COUNT(*) FROM alertas a JOIN estudiantes e ON e.id = a.estudiante_id WHERE {$where}",
            $params
        );
    }

    /**
     * Genera alertas de mora para cargos vencidos que aún no tienen alerta
     * abierta. Es idempotente: puede ejecutarse al abrir el módulo.
     */
    public static function syncMora(int $limit = 200): int
    {
        $moraDays = max(0, Param::int('mora_dias', 30));
        $limitDate = date('Y-m-d', strtotime('-' . $moraDays . ' days'));

        $where = "c.estado = 'pendiente' AND c.fecha_vencimiento < ?";
        $params = [$limitDate];

        $scope = Auth::sedeScope();
        if ($scope !== null) {
            $where .= ' AND e.sede_id = ?';
            $params[] = $scope;
        }

        $rows = Database::fetchAll(
            "SELECT c.id, c.estudiante_id, c.descripcion, c.valor_total, c.fecha_vencimiento,
                    e.sede_id,
                    c.valor_total - COALESCE((SELECT SUM(pa.valor) FROM pago_aplicaciones pa WHERE pa.cargo_id = c.id AND pa.estado = 'activa'), 0) AS saldo,
                    CONCAT_WS(' ', p.nombres, p.apellidos) AS estudiante
             FROM cargos c
             JOIN estudiantes e ON e.id = c.estudiante_id
             JOIN personas p ON p.id = e.persona_id
             WHERE {$where}
               AND c.valor_total > COALESCE((SELECT SUM(pa.valor) FROM pago_aplicaciones pa WHERE pa.cargo_id = c.id AND pa.estado = 'activa'), 0)
             ORDER BY c.fecha_vencimiento
             LIMIT {$limit}",
            $params
        );

        $created = 0;

        foreach ($rows as $row) {
            $exists = (int) Database::scalar(
                "SELECT COUNT(*) FROM alertas
                 WHERE estudiante_id = ? AND tipo = 'mora' AND estado = 'nueva'
                   AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
                [(int) $row['estudiante_id']]
            );

            if ($exists > 0) {
                continue;
            }

            self::create(
                (int) $row['estudiante_id'],
                null,
                'mora',
                'critica',
                'Cartera vencida: ' . $row['descripcion'] . ' con saldo ' . money((float) $row['saldo'])
                . ' y vencimiento ' . fecha($row['fecha_vencimiento']) . '.'
            );

            $created++;
        }

        return $created;
    }
}
