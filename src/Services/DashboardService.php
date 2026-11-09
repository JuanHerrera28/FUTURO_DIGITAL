<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;

final class DashboardService
{
    /**
     * @return array{stats:array<string,mixed>,alerts:list<array<string,mixed>>,period:?array<string,mixed>}
     */
    public static function summary(): array
    {
        $scope = Auth::sedeScope();
        $period = self::currentPeriod();

        $studentWhere = "e.estado = 'activo'";
        $studentParams = [];
        if ($scope !== null) {
            $studentWhere .= ' AND e.sede_id = ?';
            $studentParams[] = $scope;
        }

        $estudiantes = (int) Database::scalar(
            "SELECT COUNT(*) FROM estudiantes e WHERE {$studentWhere}",
            $studentParams
        );

        $matriculas = 0;
        $grupos = 0;

        if ($period !== null) {
            $where = "m.periodo_id = ? AND m.estado = 'activa'";
            $params = [(int) $period['id']];
            if ($scope !== null) {
                $where .= ' AND m.sede_id = ?';
                $params[] = $scope;
            }
            $matriculas = (int) Database::scalar("SELECT COUNT(*) FROM matriculas m WHERE {$where}", $params);

            $groupWhere = "g.periodo_id = ? AND g.estado = 'activo'";
            $groupParams = [(int) $period['id']];
            if ($scope !== null) {
                $groupWhere .= ' AND g.sede_id = ?';
                $groupParams[] = $scope;
            }
            $grupos = (int) Database::scalar("SELECT COUNT(*) FROM grupos g WHERE {$groupWhere}", $groupParams);
        }

        $cartera = null;
        if (Auth::can('payments.view')) {
            $where = "c.estado <> 'anulado'";
            $params = [];
            if ($scope !== null) {
                $where .= ' AND e.sede_id = ?';
                $params[] = $scope;
            }

            $cartera = (float) Database::scalar(
                "SELECT COALESCE(SUM(c.valor_total - COALESCE((
                            SELECT SUM(pa.valor) FROM pago_aplicaciones pa
                            WHERE pa.cargo_id = c.id AND pa.estado = 'activa'
                        ), 0)), 0)
                 FROM cargos c
                 JOIN estudiantes e ON e.id = c.estudiante_id
                 WHERE {$where}",
                $params
            );
        }

        return [
            'stats' => [
                'estudiantes' => $estudiantes,
                'matriculas' => $matriculas,
                'grupos' => $grupos,
                'cartera' => $cartera,
                'alertas' => AlertService::openCount(),
            ],
            'alerts' => AlertService::recent(6),
            'period' => $period,
        ];
    }

    /** @return array<string,mixed>|null */
    public static function currentPeriod(): ?array
    {
        $period = Database::fetch(
            "SELECT * FROM periodos
             WHERE estado IN ('matricula_abierta','en_curso')
             ORDER BY CASE estado WHEN 'matricula_abierta' THEN 0 ELSE 1 END, fecha_inicio DESC
             LIMIT 1"
        );

        if ($period !== null) {
            return $period;
        }

        return Database::fetch('SELECT * FROM periodos ORDER BY fecha_inicio DESC LIMIT 1');
    }
}
