<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Session;
use App\Services\CourseService;
use App\Services\ProgramService;

final class ProgramsController extends Controller
{
    public function index(): void
    {
        $filters = [
            'buscar' => Request::string('buscar'),
            'estado' => Request::string('estado'),
        ];
        $page = max(1, Request::int('page', 1));
        $result = ProgramService::paginate($filters, $page);

        $this->view('programs.index', [
            'title' => 'Programas',
            'rows' => $result['rows'],
            'paginator' => Paginator::of($page, 20, $result['total']),
            'filters' => $filters,
        ]);
    }

    public function create(): void
    {
        $this->view('programs.create', ['title' => 'Nuevo programa']);
    }

    public function store(): void
    {
        $this->validate([
            'codigo' => 'required|max:20',
            'nombre' => 'required|max:120',
            'duracion_periodos' => 'required|int|minval:1|maxval:20',
        ], [
            'codigo' => 'código',
            'nombre' => 'nombre',
            'duracion_periodos' => 'duración en periodos',
        ]);

        $id = ProgramService::create([
            'codigo' => Request::string('codigo'),
            'nombre' => Request::string('nombre'),
            'duracion_periodos' => Request::int('duracion_periodos', 1),
            'estado' => 'activo',
        ]);

        Session::flash('success', 'Programa creado correctamente.');
        $this->redirect('/programs/' . $id);
    }

    public function show(string|int $id): void
    {
        $program = ProgramService::find($this->id($id));

        if ($program === null) {
            Session::flash('error', 'El programa no existe.');
            $this->redirect('/programs');
        }

        $this->view('programs.show', [
            'title' => 'Programa · ' . $program['nombre'],
            'program' => $program,
            'plan' => ProgramService::plan($this->id($id)),
            'prerequisites' => ProgramService::prerequisites(),
            'courses' => CourseService::options(),
        ]);
    }

    public function edit(string|int $id): void
    {
        $program = ProgramService::find($this->id($id));

        if ($program === null) {
            Session::flash('error', 'El programa no existe.');
            $this->redirect('/programs');
        }

        $this->view('programs.edit', [
            'title' => 'Editar programa',
            'program' => $program,
        ]);
    }

    public function update(string|int $id): void
    {
        $this->validate([
            'codigo' => 'required|max:20',
            'nombre' => 'required|max:120',
            'duracion_periodos' => 'required|int|minval:1|maxval:20',
            'estado' => 'nullable|in:activo,inactivo',
        ]);

        ProgramService::update($this->id($id), [
            'codigo' => Request::string('codigo'),
            'nombre' => Request::string('nombre'),
            'duracion_periodos' => Request::int('duracion_periodos', 1),
            'estado' => Request::string('estado', 'activo'),
        ]);

        Session::flash('success', 'Programa actualizado.');
        $this->redirect('/programs/' . $this->id($id));
    }

    public function addCourse(string|int $id): void
    {
        $this->validate([
            'curso_id' => 'required|int',
            'nivel' => 'required|int|minval:1|maxval:20',
        ], [
            'curso_id' => 'curso',
            'nivel' => 'nivel',
        ]);

        ProgramService::addCourse(
            $this->id($id),
            Request::int('curso_id'),
            Request::int('nivel', 1),
            Request::bool('obligatorio')
        );

        Session::flash('success', 'Curso agregado al plan de estudios.');
        $this->redirect('/programs/' . $this->id($id) . '#plan');
    }

    public function removeCourse(string|int $id, string|int $planId): void
    {
        ProgramService::removeCourse($this->id($planId));

        Session::flash('success', 'Curso retirado del plan de estudios.');
        $this->redirect('/programs/' . $this->id($id) . '#plan');
    }

    public function addPrerequisite(string|int $id): void
    {
        $this->validate([
            'curso_id' => 'required|int',
            'requisito_id' => 'required|int',
            'tipo' => 'required|in:aprobado,cursado',
        ], [
            'curso_id' => 'curso',
            'requisito_id' => 'curso requisito',
            'tipo' => 'tipo de prerrequisito',
        ]);

        ProgramService::addPrerequisite(
            Request::int('curso_id'),
            Request::int('requisito_id'),
            Request::string('tipo', 'aprobado')
        );

        Session::flash('success', 'Prerrequisito registrado.');
        $this->redirect('/programs/' . $this->id($id) . '#prerrequisitos');
    }

    public function removePrerequisite(string|int $id, string|int $prerequisiteId): void
    {
        ProgramService::removePrerequisite($this->id($prerequisiteId));

        Session::flash('success', 'Prerrequisito inactivado.');
        $this->redirect('/programs/' . $this->id($id) . '#prerrequisitos');
    }
}
