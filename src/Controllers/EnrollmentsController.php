<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Session;
use App\Services\EnrollmentService;
use App\Services\GroupService;
use App\Services\PeriodService;
use App\Services\PersonService;
use App\Services\ProgramService;
use App\Services\StudentService;

final class EnrollmentsController extends Controller
{
    public function index(): void
    {
        $filters = [
            'buscar' => Request::string('buscar'),
            'periodo_id' => Request::int('periodo_id'),
            'programa_id' => Request::int('programa_id'),
            'sede_id' => Request::int('sede_id'),
            'estado' => Request::string('estado'),
        ];
        $page = max(1, Request::int('page', 1));
        $result = EnrollmentService::matriculasPaginate($filters, $page);

        $this->view('enrollments.index', [
            'title' => 'Matrículas',
            'rows' => $result['rows'],
            'paginator' => Paginator::of($page, 20, $result['total']),
            'filters' => $filters,
            'periods' => PeriodService::options(),
            'programs' => ProgramService::options(),
            'sedes' => PersonService::sedes(),
        ]);
    }

    public function create(): void
    {
        $students = StudentService::paginate([], 1, 500)['rows'];

        $this->view('enrollments.create', [
            'title' => 'Nueva matrícula',
            'students' => $students,
            'programs' => ProgramService::options(),
            'periods' => PeriodService::options(),
        ]);
    }

    public function store(): void
    {
        $this->validate([
            'estudiante_id' => 'required|int',
            'programa_id' => 'required|int',
            'periodo_id' => 'required|int',
        ], [
            'estudiante_id' => 'estudiante',
            'programa_id' => 'programa',
            'periodo_id' => 'periodo',
        ]);

        $result = EnrollmentService::createEnrollment(
            Request::int('estudiante_id'),
            Request::int('programa_id'),
            Request::int('periodo_id')
        );

        Session::flash('success', 'Matrícula ' . $result['codigo'] . ' creada correctamente.');
        if ($result['credentials'] !== null) {
            Session::flash('credentials', $result['credentials']);
        }

        $this->redirect('/enrollments/' . $result['matricula_id']);
    }

    public function show(string|int $id): void
    {
        $matricula = EnrollmentService::findMatricula($this->id($id));

        if ($matricula === null) {
            Session::flash('error', 'La matrícula no existe.');
            $this->redirect('/enrollments');
        }

        $inscriptions = EnrollmentService::inscriptions($this->id($id));

        foreach ($inscriptions as &$inscription) {
            $inscription['movimientos'] = EnrollmentService::movements((int) $inscription['id']);
        }
        unset($inscription);

        $this->view('enrollments.show', [
            'title' => 'Matrícula · ' . $matricula['codigo'],
            'matricula' => $matricula,
            'inscriptions' => $inscriptions,
            'availableGroups' => GroupService::forEnrollment($this->id($id)),
        ]);
    }

    public function enroll(string|int $id): void
    {
        $this->validate([
            'grupo_id' => 'required|int',
        ], [
            'grupo_id' => 'grupo',
        ]);

        $result = EnrollmentService::enroll($this->id($id), Request::int('grupo_id'));

        Session::flash('success', 'Inscripción realizada en el grupo ' . $result['grupo'] . '.');
        $this->redirect('/enrollments/' . $this->id($id));
    }

    public function changeGroup(string|int $inscripcionId): void
    {
        $this->validate([
            'grupo_id' => 'required|int',
            'motivo' => 'required|max:255',
        ], [
            'grupo_id' => 'nuevo grupo',
            'motivo' => 'motivo',
        ]);

        $inscription = EnrollmentService::findInscription($this->id($inscripcionId));

        if ($inscription === null) {
            Session::flash('error', 'La inscripción no existe.');
            $this->redirect('/enrollments');
        }

        $result = EnrollmentService::changeGroup(
            $this->id($inscripcionId),
            Request::int('grupo_id'),
            Request::string('motivo')
        );

        Session::flash('success', 'Cambio de grupo registrado. Nueva inscripción en ' . $result['grupo'] . '.');
        $this->redirect('/enrollments/' . (int) $inscription['matricula_id']);
    }

    public function cancel(string|int $inscripcionId): void
    {
        $this->validate([
            'motivo' => 'required|max:255',
        ], [
            'motivo' => 'motivo',
        ]);

        $inscription = EnrollmentService::findInscription($this->id($inscripcionId));

        if ($inscription === null) {
            Session::flash('error', 'La inscripción no existe.');
            $this->redirect('/enrollments');
        }

        EnrollmentService::cancel($this->id($inscripcionId), Request::string('motivo'));

        Session::flash('success', 'Inscripción cancelada correctamente.');
        $this->redirect('/enrollments/' . (int) $inscription['matricula_id']);
    }
}
