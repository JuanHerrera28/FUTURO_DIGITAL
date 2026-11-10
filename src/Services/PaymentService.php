<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\DomainException;
use App\Core\Param;

final class PaymentService
{
    /**
     * Resuelve la tarifa más específica para concepto, programa y periodo.
     *
     * @return array<string,mixed>|null
     */
    public static function tariffFor(string $conceptoCode, ?int $programaId = null, ?int $periodoId = null): ?array
    {
        return Database::fetch(
            "SELECT t.*, cc.codigo, cc.nombre AS concepto
             FROM tarifas t
             JOIN conceptos_cargo cc ON cc.id = t.concepto_id
             WHERE cc.codigo = ?
               AND cc.estado = 'activo'
               AND t.estado = 'activo'
               AND (t.programa_id = ? OR t.programa_id IS NULL)
               AND (t.periodo_id = ? OR t.periodo_id IS NULL)
             ORDER BY (t.programa_id IS NOT NULL) DESC, (t.periodo_id IS NOT NULL) DESC, t.id DESC
             LIMIT 1",
            [$conceptoCode, $programaId ?? 0, $periodoId ?? 0]
        );
    }

    /** @return list<array<string,mixed>> */
    public static function concepts(): array
    {
        return Database::fetchAll("SELECT * FROM conceptos_cargo WHERE estado = 'activo' ORDER BY nombre");
    }

    public static function createCharge(
        int $estudianteId,
        int $conceptoId,
        string $descripcion,
        float $valor,
        string $fechaVencimiento,
        ?int $matriculaId = null
    ): int {
        if ($valor < 0) {
            throw new DomainException('RF-16', 'El valor del cargo no puede ser negativo.');
        }

        $id = Database::insert('cargos', [
            'estudiante_id' => $estudianteId,
            'matricula_id' => $matriculaId,
            'concepto_id' => $conceptoId,
            'descripcion' => substr(trim($descripcion), 0, 180),
            'valor_total' => round($valor, 2),
            'fecha_emision' => date('Y-m-d'),
            'fecha_vencimiento' => $fechaVencimiento,
            'estado' => 'pendiente',
            'created_by' => Auth::id(),
        ]);

        Audit::log('cargo_creado', 'pagos', 'cargos', $id, null, [
            'estudiante_id' => $estudianteId,
            'concepto_id' => $conceptoId,
            'valor_total' => round($valor, 2),
            'fecha_vencimiento' => $fechaVencimiento,
        ]);

        return $id;
    }

    /**
     * Crea el cargo de matrícula según la tarifa configurada.
     */
    public static function createEnrollmentCharge(int $estudianteId, int $matriculaId, int $programaId, int $periodoId, string $programaNombre, string $periodoNombre): int
    {
        $tariff = self::tariffFor('MAT', $programaId, $periodoId);

        if ($tariff === null) {
            throw new DomainException('RF-09', 'No existe una tarifa activa de matrícula para el programa y periodo. Configure la tarifa antes de matricular.');
        }

        $days = max(1, Param::int('dias_vencimiento_matricula', 30));

        return self::createCharge(
            $estudianteId,
            (int) $tariff['concepto_id'],
            'Matrícula ' . $programaNombre . ' · ' . $periodoNombre,
            (float) $tariff['valor'],
            date('Y-m-d', strtotime('+' . $days . ' days')),
            $matriculaId
        );
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{rows:list<array<string,mixed>>,total:int}
     */
    public static function chargesPaginate(array $filters, int $page = 1, int $perPage = 20): array
    {
        $where = ["c.estado <> 'anulado'"];
        $params = [];

        if (!empty($filters['buscar'])) {
            $term = '%' . $filters['buscar'] . '%';
            $where[] = '(p.nombres LIKE ? OR p.apellidos LIKE ? OR p.numero_documento LIKE ? OR c.descripcion LIKE ?)';
            array_push($params, $term, $term, $term, $term);
        }

        if (!empty($filters['estudiante_id'])) {
            $where[] = 'c.estudiante_id = ?';
            $params[] = (int) $filters['estudiante_id'];
        }

        if (!empty($filters['estado'])) {
            if ($filters['estado'] === 'vencido') {
                $where[] = "c.estado = 'pendiente' AND c.fecha_vencimiento < ?";
                $params[] = date('Y-m-d', strtotime('-' . max(0, Param::int('mora_dias', 30)) . ' days'));
            } else {
                $where[] = 'c.estado = ?';
                $params[] = $filters['estado'];
            }
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
            "SELECT COUNT(*) FROM cargos c JOIN estudiantes e ON e.id = c.estudiante_id JOIN personas p ON p.id = e.persona_id WHERE {$whereSql}",
            $params
        );
        $offset = max(0, ($page - 1) * $perPage);

        $rows = Database::fetchAll(
            "SELECT c.*, cc.nombre AS concepto, e.codigo AS estudiante_codigo, e.sede_id,
                    p.nombres, p.apellidos, p.numero_documento,
                    (SELECT COALESCE(SUM(pa.valor),0) FROM pago_aplicaciones pa WHERE pa.cargo_id = c.id AND pa.estado = 'activa') AS pagado
             FROM cargos c
             JOIN estudiantes e ON e.id = c.estudiante_id
             JOIN personas p ON p.id = e.persona_id
             JOIN conceptos_cargo cc ON cc.id = c.concepto_id
             WHERE {$whereSql}
             ORDER BY c.fecha_vencimiento, c.id
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{rows:list<array<string,mixed>>,total:int}
     */
    public static function paymentsPaginate(array $filters, int $page = 1, int $perPage = 20): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['buscar'])) {
            $term = '%' . $filters['buscar'] . '%';
            $where[] = '(p.nombres LIKE ? OR p.apellidos LIKE ? OR p.numero_documento LIKE ? OR pg.referencia LIKE ?)';
            array_push($params, $term, $term, $term, $term);
        }

        if (!empty($filters['estudiante_id'])) {
            $where[] = 'pg.estudiante_id = ?';
            $params[] = (int) $filters['estudiante_id'];
        }

        if (!empty($filters['estado'])) {
            $where[] = 'pg.estado = ?';
            $params[] = $filters['estado'];
        }

        if (!empty($filters['desde'])) {
            $where[] = 'pg.fecha >= ?';
            $params[] = $filters['desde'];
        }

        if (!empty($filters['hasta'])) {
            $where[] = 'pg.fecha <= ?';
            $params[] = $filters['hasta'];
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
            "SELECT COUNT(*) FROM pagos pg JOIN estudiantes e ON e.id = pg.estudiante_id JOIN personas p ON p.id = e.persona_id WHERE {$whereSql}",
            $params
        );
        $offset = max(0, ($page - 1) * $perPage);

        $rows = Database::fetchAll(
            "SELECT pg.*, e.codigo AS estudiante_codigo, e.sede_id,
                    p.nombres, p.apellidos, p.numero_documento,
                    cp.numero AS comprobante, cp.estado AS comprobante_estado
             FROM pagos pg
             JOIN estudiantes e ON e.id = pg.estudiante_id
             JOIN personas p ON p.id = e.persona_id
             LEFT JOIN comprobantes cp ON cp.pago_id = pg.id
             WHERE {$whereSql}
             ORDER BY pg.fecha DESC, pg.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Estado de cuenta calculado (no se almacena saldo).
     *
     * @return array{charges:list<array<string,mixed>>,total:float,pagado:float,saldo:float,vencido:float}
     */
    public static function statement(int $estudianteId): array
    {
        $moraDays = max(0, Param::int('mora_dias', 30));

        $charges = Database::fetchAll(
            "SELECT c.*, cc.nombre AS concepto,
                    (SELECT COALESCE(SUM(pa.valor),0) FROM pago_aplicaciones pa WHERE pa.cargo_id = c.id AND pa.estado = 'activa') AS pagado
             FROM cargos c
             JOIN conceptos_cargo cc ON cc.id = c.concepto_id
             WHERE c.estudiante_id = ? AND c.estado <> 'anulado'
             ORDER BY c.fecha_vencimiento, c.id",
            [$estudianteId]
        );

        $total = 0.0;
        $paid = 0.0;
        $overdue = 0.0;
        $limit = date('Y-m-d', strtotime('-' . $moraDays . ' days'));

        foreach ($charges as &$charge) {
            $value = (float) $charge['valor_total'];
            $paidValue = (float) $charge['pagado'];
            $balance = max(0.0, $value - $paidValue);

            $charge['saldo'] = round($balance, 2);
            $charge['vencido'] = $balance > 0.01 && (string) $charge['fecha_vencimiento'] < $limit;
            $charge['estado_visual'] = $balance <= 0.01 ? 'pagado' : ($charge['vencido'] ? 'vencido' : 'pendiente');

            $total += $value;
            $paid += $paidValue;
            if ($charge['vencido']) {
                $overdue += $balance;
            }
        }
        unset($charge);

        return [
            'charges' => $charges,
            'total' => round($total, 2),
            'pagado' => round($paid, 2),
            'saldo' => round(max(0.0, $total - $paid), 2),
            'vencido' => round($overdue, 2),
        ];
    }

    public static function hasOverdueDebt(int $estudianteId): bool
    {
        $moraDays = max(0, Param::int('mora_dias', 30));
        $limit = date('Y-m-d', strtotime('-' . $moraDays . ' days'));

        return (float) Database::scalar(
            "SELECT COALESCE(SUM(c.valor_total - COALESCE((SELECT SUM(pa.valor) FROM pago_aplicaciones pa WHERE pa.cargo_id = c.id AND pa.estado = 'activa'), 0)), 0)
             FROM cargos c
             WHERE c.estudiante_id = ?
               AND c.estado = 'pendiente'
               AND c.fecha_vencimiento < ?
               AND c.valor_total > COALESCE((SELECT SUM(pa.valor) FROM pago_aplicaciones pa WHERE pa.cargo_id = c.id AND pa.estado = 'activa'), 0)",
            [$estudianteId, $limit]
        ) > 0.01;
    }

    /**
     * Registra un pago y lo aplica a cargos. Si no se indican aplicaciones,
     * se abona a los cargos más antiguos primero.
     *
     * @param array<int,float> $allocations cargo_id => valor
     * @return array{pago_id:int,comprobante:string,aplicado:float,saldo_favor:float}
     */
    public static function registerPayment(
        int $estudianteId,
        string $fecha,
        float $valor,
        string $medio,
        ?string $referencia = null,
        array $allocations = []
    ): array {
        $valor = round($valor, 2);

        if ($valor <= 0) {
            throw new DomainException('RF-16', 'El valor del pago debe ser mayor que cero.');
        }

        if (!in_array($medio, ['efectivo', 'transferencia', 'tarjeta', 'otro'], true)) {
            throw new DomainException('RF-16', 'El medio de pago no es válido.');
        }

        return Database::transaction(static function () use ($estudianteId, $fecha, $valor, $medio, $referencia, $allocations): array {
            $student = Database::fetch('SELECT id, sede_id FROM estudiantes WHERE id = ? FOR UPDATE', [$estudianteId]);

            if ($student === null) {
                throw new DomainException('RF-16', 'El estudiante no existe.');
            }

            if (!Auth::canAccessSede((int) $student['sede_id'])) {
                throw new DomainException('RN-19', 'Solo puede registrar pagos de estudiantes de su sede.');
            }

            $charges = Database::fetchAll(
                "SELECT c.*,
                        COALESCE((SELECT SUM(pa.valor) FROM pago_aplicaciones pa WHERE pa.cargo_id = c.id AND pa.estado = 'activa'), 0) AS pagado
                 FROM cargos c
                 WHERE c.estudiante_id = ? AND c.estado = 'pendiente'
                 ORDER BY c.fecha_vencimiento, c.id
                 FOR UPDATE",
                [$estudianteId]
            );

            $balances = [];
            $totalDebt = 0.0;
            foreach ($charges as $charge) {
                $balance = round((float) $charge['valor_total'] - (float) $charge['pagado'], 2);
                if ($balance > 0) {
                    $balances[(int) $charge['id']] = $balance;
                    $totalDebt = round($totalDebt + $balance, 2);
                }
            }

            if ($totalDebt <= 0) {
                throw new DomainException('RF-16', 'El estudiante no tiene cargos pendientes para aplicar el pago.');
            }

            if ($valor > $totalDebt) {
                throw new DomainException('RF-16', 'El pago (' . money($valor) . ') supera el saldo pendiente (' . money($totalDebt) . '). Registre un valor menor o igual.');
            }

            $plan = [];
            $remaining = $valor;

            if ($allocations !== []) {
                foreach ($allocations as $cargoId => $amount) {
                    $cargoId = (int) $cargoId;
                    $amount = round((float) $amount, 2);

                    if ($amount <= 0) {
                        continue;
                    }

                    if (!isset($balances[$cargoId])) {
                        throw new DomainException('RF-16', 'Uno de los cargos seleccionados no existe o ya está pagado.');
                    }

                    if ($amount > $balances[$cargoId]) {
                        throw new DomainException('RF-16', 'El valor aplicado a un cargo supera su saldo pendiente.');
                    }

                    if ($amount > $remaining) {
                        throw new DomainException('RF-16', 'La suma de las aplicaciones supera el valor del pago.');
                    }

                    $plan[] = ['cargo_id' => $cargoId, 'valor' => $amount];
                    $remaining = round($remaining - $amount, 2);
                }

                if ($remaining > 0.005) {
                    throw new DomainException('RF-16', 'Debe distribuir todo el valor del pago entre los cargos seleccionados.');
                }
            } else {
                foreach ($balances as $cargoId => $balance) {
                    if ($remaining <= 0.005) {
                        break;
                    }

                    $amount = min($balance, $remaining);
                    $plan[] = ['cargo_id' => $cargoId, 'valor' => round($amount, 2)];
                    $remaining = round($remaining - $amount, 2);
                }
            }

            $pagoId = Database::insert('pagos', [
                'estudiante_id' => $estudianteId,
                'fecha' => $fecha,
                'valor' => round($valor, 2),
                'medio' => $medio,
                'referencia' => $referencia !== null && trim($referencia) !== '' ? substr(trim($referencia), 0, 100) : null,
                'estado' => 'registrado',
                'created_by' => Auth::id(),
            ]);

            $comprobanteId = Database::insert('comprobantes', [
                'pago_id' => $pagoId,
                'numero' => 'TMP-' . bin2hex(random_bytes(5)),
                'fecha' => $fecha,
                'valor' => round($valor, 2),
                'estado' => 'vigente',
            ]);

            CodeService::receipt($pagoId);
            $numero = (string) Database::scalar('SELECT numero FROM comprobantes WHERE id = ?', [$comprobanteId]);

            foreach ($plan as $item) {
                Database::insert('pago_aplicaciones', [
                    'pago_id' => $pagoId,
                    'cargo_id' => $item['cargo_id'],
                    'valor' => $item['valor'],
                    'estado' => 'activa',
                ]);

                $charge = Database::fetch(
                    'SELECT valor_total FROM cargos WHERE id = ?',
                    [$item['cargo_id']]
                );
                $paid = (float) Database::scalar(
                    "SELECT COALESCE(SUM(valor),0) FROM pago_aplicaciones WHERE cargo_id = ? AND estado = 'activa'",
                    [$item['cargo_id']]
                );

                if ($paid + 0.01 >= (float) $charge['valor_total']) {
                    Database::update('cargos', ['estado' => 'pagado'], ['id' => $item['cargo_id']]);
                }
            }

            Audit::log('pago_registrado', 'pagos', 'pagos', $pagoId, null, [
                'estudiante_id' => $estudianteId,
                'valor' => round($valor, 2),
                'medio' => $medio,
                'comprobante' => $numero,
                'aplicaciones' => $plan,
            ]);

            return [
                'pago_id' => $pagoId,
                'comprobante' => $numero,
                'aplicado' => round($valor - $remaining, 2),
                'saldo_favor' => round($remaining, 2),
            ];
        });
    }

    public static function annulPayment(int $pagoId, string $motivo): void
    {
        Database::transaction(static function () use ($pagoId, $motivo): void {
            $payment = Database::fetch('SELECT * FROM pagos WHERE id = ? FOR UPDATE', [$pagoId]);

            if ($payment === null) {
                throw new DomainException('RF-16', 'El pago no existe.');
            }

            if ((string) $payment['estado'] === 'anulado') {
                throw new DomainException('RF-16', 'El pago ya está anulado.');
            }

            $student = Database::fetch('SELECT sede_id FROM estudiantes WHERE id = ?', [(int) $payment['estudiante_id']]);
            if ($student !== null && !Auth::canAccessSede((int) $student['sede_id'])) {
                throw new DomainException('RN-19', 'Solo puede anular pagos de estudiantes de su sede.');
            }

            $applications = Database::fetchAll(
                "SELECT * FROM pago_aplicaciones WHERE pago_id = ? AND estado = 'activa'",
                [$pagoId]
            );

            Database::update('pagos', [
                'estado' => 'anulado',
                'motivo_anulacion' => substr(trim($motivo), 0, 255),
                'anulado_por' => Auth::id(),
                'anulado_at' => date('Y-m-d H:i:s'),
            ], ['id' => $pagoId]);

            Database::update('pago_aplicaciones', ['estado' => 'anulada'], ['pago_id' => $pagoId]);
            Database::update('comprobantes', ['estado' => 'anulado'], ['pago_id' => $pagoId]);

            foreach ($applications as $application) {
                $cargoId = (int) $application['cargo_id'];
                $paid = (float) Database::scalar(
                    "SELECT COALESCE(SUM(valor),0) FROM pago_aplicaciones WHERE cargo_id = ? AND estado = 'activa'",
                    [$cargoId]
                );
                $total = (float) Database::scalar('SELECT valor_total FROM cargos WHERE id = ?', [$cargoId]);
                Database::update('cargos', [
                    'estado' => $paid + 0.01 >= $total ? 'pagado' : 'pendiente',
                ], ['id' => $cargoId]);
            }

            Audit::log('pago_anulado', 'pagos', 'pagos', $pagoId, ['estado' => 'registrado'], [
                'estado' => 'anulado',
                'motivo' => $motivo,
            ]);
        });
    }

    /** @return array<string,mixed>|null */
    public static function findPayment(int $pagoId): ?array
    {
        return Database::fetch(
            "SELECT pg.*, e.codigo AS estudiante_codigo, e.sede_id,
                    p.nombres, p.apellidos, p.numero_documento,
                    cp.numero AS comprobante, cp.estado AS comprobante_estado
             FROM pagos pg
             JOIN estudiantes e ON e.id = pg.estudiante_id
             JOIN personas p ON p.id = e.persona_id
             LEFT JOIN comprobantes cp ON cp.pago_id = pg.id
             WHERE pg.id = ?",
            [$pagoId]
        );
    }

    /** @return list<array<string,mixed>> */
    public static function applications(int $pagoId): array
    {
        return Database::fetchAll(
            "SELECT pa.*, c.descripcion, cc.nombre AS concepto, c.fecha_vencimiento
             FROM pago_aplicaciones pa
             JOIN cargos c ON c.id = pa.cargo_id
             JOIN conceptos_cargo cc ON cc.id = c.concepto_id
             WHERE pa.pago_id = ?
             ORDER BY pa.id",
            [$pagoId]
        );
    }

    public static function annulCharge(int $cargoId, string $motivo): void
    {
        $charge = Database::fetch('SELECT * FROM cargos WHERE id = ?', [$cargoId]);
        if ($charge === null) {
            throw new DomainException('RF-16', 'El cargo no existe.');
        }

        if ((string) $charge['estado'] === 'pagado') {
            throw new DomainException('RF-16', 'No se puede anular un cargo que ya tiene pagos aplicados. Anule primero los pagos.');
        }

        Database::update('cargos', ['estado' => 'anulado'], ['id' => $cargoId]);
        Audit::log('cargo_anulado', 'pagos', 'cargos', $cargoId, ['estado' => $charge['estado']], ['estado' => 'anulado', 'motivo' => $motivo]);
    }
}
