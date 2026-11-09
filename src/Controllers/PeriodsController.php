<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\DomainException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Session;
use App\Services\PeriodService;

final class PeriodsController extends Controller
{
    public function index(): void
    {
        $filters = [
            'buscar' => Request::string('buscar'),
            'estado' => Request::string('estado'),
        ];
        $page = max(1, Request::int('page', 1));
        $result = PeriodService::paginate($filters, $page);

        $this->view('periods.index', [
            'title' => 'Periodos académicos',
            'rows' => $result['rows'],
            'paginator' => Paginator::of($page, 20, $result['total']),
            'filters' => $filters,
        ]);
    }

    public function create(): void
    {
        $this->view('periods.create', ['title' => 'Nuevo periodo']);
    }

    public function store(): void
    {
        $this->validate($this->rules(), [
            'codigo' => 'código',
            'nombre' => 'nombre',
            'fecha_inicio' => 'fecha de inicio',
            'fecha_fin' => 'fecha de fin',
            'matricula_inicio' => 'apertura de matrícula',
            'matricula_fin' => 'cierre de matrícula',
        ]);

        $id = PeriodService::create($this->data());

        Session::flash('success', 'Periodo creado correctamente.');
        $this->redirect('/periods/' . $id);
    }

    public function show(string|int $id): void
    {
        $period = PeriodService::find($this->id($id));

        if ($period === null) {
            Session::flash('error', 'El periodo no existe.');
            $this->redirect('/periods');
        }

        $this->view('periods.show', [
            'title' => 'Periodo · ' . $period['nombre'],
            'period' => $period,
            'lowGroups' => PeriodService::groupsBelowMinimum($this->id($id)),
        ]);
    }

    public function edit(string|int $id): void
    {
        $period = PeriodService::find($this->id($id));

        if ($period === null) {
            Session::flash('error', 'El periodo no existe.');
            $this->redirect('/periods');
        }

        $this->view('periods.edit', [
            'title' => 'Editar periodo',
            'period' => $period,
        ]);
    }

    public function update(string|int $id): void
    {
        $this->validate($this->rules(), [
            'codigo' => 'código',
            'nombre' => 'nombre',
            'fecha_inicio' => 'fecha de inicio',
            'fecha_fin' => 'fecha de fin',
            'matricula_inicio' => 'apertura de matrícula',
            'matricula_fin' => 'cierre de matrícula',
        ]);

        PeriodService::update($this->id($id), $this->data());

        Session::flash('success', 'Periodo actualizado correctamente.');
        $this->redirect('/periods/' . $this->id($id));
    }

    public function openEnrollment(string|int $id): void
    {
        $confirm = Request::bool('confirmar');
        $note = Request::string('observacion');

        try {
            $result = PeriodService::openEnrollment($this->id($id), $confirm, $note !== '' ? $note : null);
        } catch (DomainException $e) {
            Session::flash('error', $e->fullMessage());
            $this->redirect('/periods/' . $this->id($id));
        }

        $message = 'Matrícula abierta correctamente.';
        if ($result['lowGroups'] !== []) {
            $message .= ' Se registraron ' . count($result['lowGroups']) . ' grupos por debajo del cupo mínimo con confirmación auditada.';
        }

        Session::flash('success', $message);
        $this->redirect('/periods/' . $this->id($id));
    }

    public function start(string|int $id): void
    {
        try {
            PeriodService::start($this->id($id));
            Session::flash('success', 'El periodo pasó a curso.');
        } catch (DomainException $e) {
            Session::flash('error', $e->fullMessage());
        }

        $this->redirect('/periods/' . $this->id($id));
    }

    public function close(string|int $id): void
    {
        $force = Request::bool('forzar');

        try {
            $result = PeriodService::close($this->id($id), $force);
            Session::flash('success', 'Periodo ' . $result['periodo'] . ' cerrado. Inscripciones consolidadas: ' . $result['inscripciones'] . '.');
        } catch (DomainException $e) {
            Session::flash('error', $e->fullMessage());
        }

        $this->redirect('/periods/' . $this->id($id));
    }

    /** @return array<string,string> */
    private function rules(): array
    {
        return [
            'codigo' => 'required|max:20',
            'nombre' => 'required|max:100',
            'fecha_inicio' => 'required|date',
            'fecha_fin' => 'required|date',
            'matricula_inicio' => 'required|date',
            'matricula_fin' => 'required|date',
        ];
    }

    /** @return array<string,mixed> */
    private function data(): array
    {
        return [
            'codigo' => Request::string('codigo'),
            'nombre' => Request::string('nombre'),
            'fecha_inicio' => Request::string('fecha_inicio'),
            'fecha_fin' => Request::string('fecha_fin'),
            'matricula_inicio' => Request::string('matricula_inicio'),
            'matricula_fin' => Request::string('matricula_fin'),
        ];
    }
}
