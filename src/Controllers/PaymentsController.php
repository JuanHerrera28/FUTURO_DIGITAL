<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Session;
use App\Services\PaymentService;
use App\Services\PersonService;
use App\Services\StudentService;

final class PaymentsController extends Controller
{
    public function index(): void
    {
        $filters = [
            'buscar' => Request::string('buscar'),
            'estado' => Request::string('estado'),
            'sede_id' => Request::int('sede_id'),
        ];
        $page = max(1, Request::int('page', 1));
        $result = PaymentService::chargesPaginate($filters, $page);

        $this->view('payments.index', [
            'title' => 'Cartera',
            'rows' => $result['rows'],
            'paginator' => Paginator::of($page, 20, $result['total']),
            'filters' => $filters,
            'sedes' => PersonService::sedes(),
        ]);
    }

    public function history(): void
    {
        $filters = [
            'buscar' => Request::string('buscar'),
            'estado' => Request::string('estado'),
            'desde' => Request::string('desde'),
            'hasta' => Request::string('hasta'),
            'sede_id' => Request::int('sede_id'),
        ];
        $page = max(1, Request::int('page', 1));
        $result = PaymentService::paymentsPaginate($filters, $page);

        $this->view('payments.history', [
            'title' => 'Pagos registrados',
            'rows' => $result['rows'],
            'paginator' => Paginator::of($page, 20, $result['total']),
            'filters' => $filters,
            'sedes' => PersonService::sedes(),
        ]);
    }

    public function create(): void
    {
        $studentId = Request::int('estudiante_id');
        $statement = $studentId > 0 ? PaymentService::statement($studentId) : null;

        $this->view('payments.create', [
            'title' => 'Registrar pago',
            'students' => StudentService::paginate([], 1, 500)['rows'],
            'studentId' => $studentId,
            'statement' => $statement,
        ]);
    }

    public function store(): void
    {
        $this->validate([
            'estudiante_id' => 'required|int',
            'fecha' => 'required|date',
            'valor' => 'required|numeric|minval:0.01',
            'medio' => 'required|in:efectivo,transferencia,tarjeta,otro',
            'referencia' => 'nullable|max:100',
        ], [
            'estudiante_id' => 'estudiante',
            'fecha' => 'fecha del pago',
            'valor' => 'valor',
            'medio' => 'medio de pago',
        ]);

        $allocations = $this->allocations();

        $result = PaymentService::registerPayment(
            Request::int('estudiante_id'),
            Request::string('fecha'),
            Request::decimal('valor'),
            Request::string('medio', 'efectivo'),
            Request::string('referencia') !== '' ? Request::string('referencia') : null,
            $allocations
        );

        Session::flash('success', 'Pago registrado. Comprobante ' . $result['comprobante'] . ' por ' . money(Request::decimal('valor')) . '.');
        $this->redirect('/payments/receipts/' . $result['pago_id']);
    }

    public function show(string|int $id): void
    {
        $payment = PaymentService::findPayment($this->id($id));

        if ($payment === null) {
            Session::flash('error', 'El pago no existe.');
            $this->redirect('/payments/history');
        }

        $this->view('payments.show', [
            'title' => 'Pago · ' . $payment['comprobante'],
            'payment' => $payment,
            'applications' => PaymentService::applications($this->id($id)),
        ]);
    }

    public function annul(string|int $id): void
    {
        $this->validate([
            'motivo' => 'required|max:255',
        ], [
            'motivo' => 'motivo de anulación',
        ]);

        PaymentService::annulPayment($this->id($id), Request::string('motivo'));

        Session::flash('success', 'Pago anulado correctamente. El comprobante quedó sin vigencia.');
        $this->redirect('/payments/receipts/' . $this->id($id));
    }

    public function statement(string|int $estudianteId): void
    {
        $student = StudentService::find($this->id($estudianteId));

        if ($student === null) {
            Session::flash('error', 'El estudiante no existe.');
            $this->redirect('/payments');
        }

        $this->view('payments.statement', [
            'title' => 'Estado de cuenta · ' . $student['nombres'] . ' ' . $student['apellidos'],
            'student' => $student,
            'statement' => PaymentService::statement($this->id($estudianteId)),
        ]);
    }

    public function createCharge(): void
    {
        $this->view('payments.charge-create', [
            'title' => 'Nuevo cargo',
            'students' => StudentService::paginate([], 1, 500)['rows'],
            'concepts' => PaymentService::concepts(),
        ]);
    }

    public function storeCharge(): void
    {
        $this->validate([
            'estudiante_id' => 'required|int',
            'concepto_id' => 'required|int',
            'descripcion' => 'required|max:180',
            'valor' => 'required|numeric|minval:0',
            'fecha_vencimiento' => 'required|date',
            'matricula_id' => 'nullable|int',
        ], [
            'estudiante_id' => 'estudiante',
            'concepto_id' => 'concepto',
            'descripcion' => 'descripción',
            'valor' => 'valor',
            'fecha_vencimiento' => 'fecha de vencimiento',
        ]);

        $matriculaId = Request::int('matricula_id');

        PaymentService::createCharge(
            Request::int('estudiante_id'),
            Request::int('concepto_id'),
            Request::string('descripcion'),
            Request::decimal('valor'),
            Request::string('fecha_vencimiento'),
            $matriculaId > 0 ? $matriculaId : null
        );

        Session::flash('success', 'Cargo registrado correctamente.');
        $this->redirect('/payments/statement/' . Request::int('estudiante_id'));
    }

    public function annulCharge(string|int $id): void
    {
        $this->validate([
            'motivo' => 'required|max:255',
        ], [
            'motivo' => 'motivo de anulación',
        ]);

        PaymentService::annulCharge($this->id($id), Request::string('motivo'));

        Session::flash('success', 'Cargo anulado correctamente.');
        $this->back('/payments');
    }

    /** @return array<int,float> */
    private function allocations(): array
    {
        $cargoIds = (array) Request::input('cargo_id', []);
        $amounts = (array) Request::input('monto', []);
        $allocations = [];

        foreach ($cargoIds as $index => $cargoId) {
            $cargoId = (int) $cargoId;
            $raw = (string) ($amounts[$index] ?? '');
            $amount = (float) str_replace(',', '.', trim($raw));

            if ($cargoId > 0 && $amount > 0) {
                $allocations[$cargoId] = $amount;
            }
        }

        return $allocations;
    }
}
