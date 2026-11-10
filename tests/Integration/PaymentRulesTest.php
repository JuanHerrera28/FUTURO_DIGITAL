<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Param;
use App\Services\PaymentService;
use Tests\Support\AuthContext;
use Tests\Support\DatabaseTestCase;
use Tests\Support\Fixtures;

/**
 * RF-16, RN-16 y RN-19: cargos, pagos, aplicaciones, comprobantes,
 * anulaciones, estado de cuenta y tarifas.
 */
final class PaymentRulesTest extends DatabaseTestCase
{
    public function testTariffResolutionPrefersTheMostSpecificConfiguration(): void
    {
        $scenario = $this->paymentScenario();

        $global = PaymentService::tariffFor('MAT', (int) $scenario['programa_id'], (int) $scenario['periodo_id']);
        $this->assertNotNull($global);
        $this->assertSame('350000.00', (string) $global['valor']);

        $periodTariff = Database::insert('tarifas', [
            'concepto_id' => 1,
            'programa_id' => null,
            'periodo_id' => (int) $scenario['periodo_id'],
            'valor' => 450000,
            'estado' => 'activo',
        ]);
        $programTariff = Database::insert('tarifas', [
            'concepto_id' => 1,
            'programa_id' => (int) $scenario['programa_id'],
            'periodo_id' => null,
            'valor' => 400000,
            'estado' => 'activo',
        ]);
        $specificTariff = Database::insert('tarifas', [
            'concepto_id' => 1,
            'programa_id' => (int) $scenario['programa_id'],
            'periodo_id' => (int) $scenario['periodo_id'],
            'valor' => 500000,
            'estado' => 'activo',
        ]);

        $this->assertSame('500000.00', (string) PaymentService::tariffFor('MAT', (int) $scenario['programa_id'], (int) $scenario['periodo_id'])['valor']);

        Database::update('tarifas', ['estado' => 'inactivo'], ['id' => $specificTariff]);
        $this->assertSame('400000.00', (string) PaymentService::tariffFor('MAT', (int) $scenario['programa_id'], (int) $scenario['periodo_id'])['valor']);

        Database::update('tarifas', ['estado' => 'inactivo'], ['id' => $programTariff]);
        $this->assertSame('450000.00', (string) PaymentService::tariffFor('MAT', (int) $scenario['programa_id'], (int) $scenario['periodo_id'])['valor']);

        Database::update('tarifas', ['estado' => 'inactivo'], ['id' => $periodTariff]);
        $this->assertSame('350000.00', (string) PaymentService::tariffFor('MAT', (int) $scenario['programa_id'], (int) $scenario['periodo_id'])['valor']);

        $this->assertNull(PaymentService::tariffFor('NOEXISTE', (int) $scenario['programa_id'], (int) $scenario['periodo_id']));
    }

    public function testPartialPaymentIsAppliedToTheOldestChargesFirst(): void
    {
        $studentId = Fixtures::estudiante(1)['id'];
        $first = Fixtures::cargo($studentId, 1000.0, null, date('Y-m-d', strtotime('+5 days')));
        $second = Fixtures::cargo($studentId, 2000.0, null, date('Y-m-d', strtotime('+10 days')));

        $result = PaymentService::registerPayment($studentId, date('Y-m-d'), 1500.0, 'efectivo');

        $this->assertSame(1500.0, $result['aplicado']);
        $this->assertSame(0.0, $result['saldo_favor']);
        $this->assertMatchesRegularExpression('/^REC-\d{4}-\d{6}$/', $result['comprobante']);

        $this->assertSame('pagado', (string) Database::scalar('SELECT estado FROM cargos WHERE id = ?', [$first]));
        $this->assertSame('pendiente', (string) Database::scalar('SELECT estado FROM cargos WHERE id = ?', [$second]));

        $applications = PaymentService::applications($result['pago_id']);
        $this->assertCount(2, $applications);
        $this->assertSame((float) $first, (float) $applications[0]['cargo_id']);
        $this->assertSame('1000.00', (string) $applications[0]['valor']);
        $this->assertSame('500.00', (string) $applications[1]['valor']);

        $statement = PaymentService::statement($studentId);
        $this->assertSame(3000.0, $statement['total']);
        $this->assertSame(1500.0, $statement['pagado']);
        $this->assertSame(1500.0, $statement['saldo']);
        $this->assertSame('vigente', (string) Database::scalar(
            'SELECT estado FROM comprobantes WHERE pago_id = ?',
            [$result['pago_id']]
        ));
        $this->assertDatabaseCount('auditoria', 1, "accion = 'pago_registrado' AND entidad_id = ?", [(string) $result['pago_id']]);
    }

    public function testPaymentCannotExceedThePendingDebt(): void
    {
        $studentId = Fixtures::estudiante(1)['id'];
        Fixtures::cargo($studentId, 1000.0);

        $this->expectDomain('RF-16', fn () => PaymentService::registerPayment(
            $studentId,
            date('Y-m-d'),
            1000.01,
            'efectivo'
        ), 'supera el saldo pendiente');
    }

    public function testPaymentRequiresPendingCharges(): void
    {
        $studentId = Fixtures::estudiante(1)['id'];

        $this->expectDomain('RF-16', fn () => PaymentService::registerPayment(
            $studentId,
            date('Y-m-d'),
            1000.0,
            'efectivo'
        ), 'no tiene cargos pendientes');
    }

    public function testPaymentValidatesAmountAndMedium(): void
    {
        $studentId = Fixtures::estudiante(1)['id'];
        Fixtures::cargo($studentId, 1000.0);

        $this->expectDomain('RF-16', fn () => PaymentService::registerPayment(
            $studentId,
            date('Y-m-d'),
            0.0,
            'efectivo'
        ), 'mayor que cero');

        $this->expectDomain('RF-16', fn () => PaymentService::registerPayment(
            $studentId,
            date('Y-m-d'),
            100.0,
            'bitcoin'
        ), 'medio de pago');
    }

    public function testExplicitAllocationsAreHonored(): void
    {
        $studentId = Fixtures::estudiante(1)['id'];
        $first = Fixtures::cargo($studentId, 1000.0);
        $second = Fixtures::cargo($studentId, 1000.0);

        PaymentService::registerPayment(
            $studentId,
            date('Y-m-d'),
            1000.0,
            'transferencia',
            'TRX-1',
            [$second => 1000.0]
        );

        $this->assertSame('pendiente', (string) Database::scalar('SELECT estado FROM cargos WHERE id = ?', [$first]));
        $this->assertSame('pagado', (string) Database::scalar('SELECT estado FROM cargos WHERE id = ?', [$second]));
    }

    public function testExplicitAllocationsMustCoverTheWholePayment(): void
    {
        $studentId = Fixtures::estudiante(1)['id'];
        $charge = Fixtures::cargo($studentId, 1000.0);

        $this->expectDomain('RF-16', fn () => PaymentService::registerPayment(
            $studentId,
            date('Y-m-d'),
            1000.0,
            'efectivo',
            null,
            [$charge => 500.0]
        ), 'distribuir todo el valor');
    }

    public function testExplicitAllocationsCannotExceedAChargeOrUseUnknownCharges(): void
    {
        $studentId = Fixtures::estudiante(1)['id'];
        $charge = Fixtures::cargo($studentId, 1000.0);

        $this->expectDomain('RF-16', fn () => PaymentService::registerPayment(
            $studentId,
            date('Y-m-d'),
            1500.0,
            'efectivo',
            null,
            [$charge => 1500.0]
        ), 'supera el saldo pendiente');

        $this->expectDomain('RF-16', fn () => PaymentService::registerPayment(
            $studentId,
            date('Y-m-d'),
            1000.0,
            'efectivo',
            null,
            [999999 => 1000.0]
        ), 'no existe o ya está pagado');
    }

    public function testAnnulPaymentRestoresChargesAndReceipt(): void
    {
        $studentId = Fixtures::estudiante(1)['id'];
        $charge = Fixtures::cargo($studentId, 1000.0);
        $payment = PaymentService::registerPayment($studentId, date('Y-m-d'), 400.0, 'efectivo');

        PaymentService::annulPayment($payment['pago_id'], 'Pago duplicado');

        $this->assertSame('anulado', (string) Database::scalar('SELECT estado FROM pagos WHERE id = ?', [$payment['pago_id']]));
        $this->assertSame('anulado', (string) Database::scalar('SELECT estado FROM comprobantes WHERE pago_id = ?', [$payment['pago_id']]));
        $this->assertSame('anulada', (string) Database::scalar('SELECT estado FROM pago_aplicaciones WHERE pago_id = ?', [$payment['pago_id']]));
        $this->assertSame('pendiente', (string) Database::scalar('SELECT estado FROM cargos WHERE id = ?', [$charge]));

        $statement = PaymentService::statement($studentId);
        $this->assertSame(0.0, $statement['pagado']);
        $this->assertSame(1000.0, $statement['saldo']);

        $this->assertDatabaseCount('auditoria', 1, "accion = 'pago_anulado' AND entidad_id = ?", [(string) $payment['pago_id']]);

        $this->expectDomain('RF-16', fn () => PaymentService::annulPayment($payment['pago_id'], 'Otra vez'), 'ya está anulado');
    }

    public function testAnnulChargeRequiresNoAppliedPayments(): void
    {
        $studentId = Fixtures::estudiante(1)['id'];
        $paid = Fixtures::cargo($studentId, 1000.0);
        PaymentService::registerPayment($studentId, date('Y-m-d'), 1000.0, 'efectivo');

        $this->expectDomain('RF-16', fn () => PaymentService::annulCharge($paid, 'Error'), 'ya tiene pagos aplicados');

        $pending = Fixtures::cargo($studentId, 500.0);
        PaymentService::annulCharge($pending, 'Cargo creado por error');

        $this->assertSame('anulado', (string) Database::scalar('SELECT estado FROM cargos WHERE id = ?', [$pending]));
        $this->assertDatabaseCount('auditoria', 1, "accion = 'cargo_anulado' AND entidad_id = ?", [(string) $pending]);
    }

    public function testStatementCalculatesOverdueBalanceUsingMoraDays(): void
    {
        $studentId = Fixtures::estudiante(1)['id'];
        $overdue = Fixtures::cargo($studentId, 1000.0, null, date('Y-m-d', strtotime('-31 days')));
        $current = Fixtures::cargo($studentId, 500.0, null, date('Y-m-d', strtotime('-10 days')));

        $statement = PaymentService::statement($studentId);

        $this->assertSame(1500.0, $statement['total']);
        $this->assertSame(1000.0, $statement['vencido']);
        $this->assertSame('vencido', $statement['charges'][0]['estado_visual']);
        $this->assertSame('pendiente', $statement['charges'][1]['estado_visual']);
        $this->assertSame((float) $overdue, (float) $statement['charges'][0]['id']);
        $this->assertSame((float) $current, (float) $statement['charges'][1]['id']);

        $this->assertTrue(PaymentService::hasOverdueDebt($studentId));
    }

    public function testOverdueDebtIgnoresChargesInsideTheGracePeriod(): void
    {
        $studentId = Fixtures::estudiante(1)['id'];
        Fixtures::cargo($studentId, 1000.0, null, date('Y-m-d', strtotime('-30 days')));

        $this->assertFalse(PaymentService::hasOverdueDebt($studentId));
    }

    public function testPaymentOperationsAreScopedToTheSecretariaSede(): void
    {
        $otherStudent = Fixtures::estudiante(2);
        $charge = Fixtures::cargo((int) $otherStudent['id'], 1000.0);
        $payment = PaymentService::registerPayment((int) $otherStudent['id'], date('Y-m-d'), 100.0, 'efectivo');

        $userId = Fixtures::usuario(Fixtures::persona(), 2, 1, 'secretaria-pagos');
        AuthContext::login($userId);

        $this->expectDomain('RN-19', fn () => PaymentService::registerPayment(
            (int) $otherStudent['id'],
            date('Y-m-d'),
            100.0,
            'efectivo'
        ), 'su sede');

        $this->expectDomain('RN-19', fn () => PaymentService::annulPayment($payment['pago_id'], 'Anulación'), 'su sede');
        $this->assertSame('pendiente', (string) Database::scalar('SELECT estado FROM cargos WHERE id = ?', [$charge]));
    }

    public function testCreateChargeRejectsNegativeValues(): void
    {
        $studentId = Fixtures::estudiante(1)['id'];

        $this->expectDomain('RF-16', fn () => PaymentService::createCharge(
            $studentId,
            1,
            'Cargo inválido',
            -1.0,
            date('Y-m-d')
        ), 'no puede ser negativo');
    }

    /**
     * @return array{programa_id:int,periodo_id:int,estudiante_id:int}
     */
    private function paymentScenario(): array
    {
        return [
            'programa_id' => Fixtures::programa(),
            'periodo_id' => Fixtures::periodo(),
            'estudiante_id' => Fixtures::estudiante(1)['id'],
        ];
    }
}
