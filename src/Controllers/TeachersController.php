<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Concerns\PeopleForms;
use App\Core\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Session;
use App\Services\PersonService;
use App\Services\TeacherService;

final class TeachersController extends Controller
{
    use PeopleForms;

    public function index(): void
    {
        $filters = [
            'buscar' => Request::string('buscar'),
            'sede_id' => Request::int('sede_id'),
            'estado' => Request::string('estado'),
        ];
        $page = max(1, Request::int('page', 1));
        $result = TeacherService::paginate($filters, $page);

        $this->view('teachers.index', [
            'title' => 'Docentes',
            'rows' => $result['rows'],
            'paginator' => Paginator::of($page, 20, $result['total']),
            'filters' => $filters,
            'sedes' => PersonService::sedes(),
        ]);
    }

    public function create(): void
    {
        $this->view('teachers.create', [
            'title' => 'Nuevo docente',
            'documentTypes' => PersonService::documentTypes(),
            'sedes' => PersonService::sedes(),
        ]);
    }

    public function store(): void
    {
        $this->validate($this->personRules() + [
            'sede_id' => 'required|int',
            'titulo' => 'nullable|max:150',
            'tipo_vinculacion' => 'required|in:planta,catedra,contrato',
        ], [
            'sede_id' => 'sede',
            'tipo_vinculacion' => 'tipo de vinculación',
        ]);

        $data = $this->personData();
        $data['sede_id'] = Request::int('sede_id');
        $data['titulo'] = Request::string('titulo');
        $data['tipo_vinculacion'] = Request::string('tipo_vinculacion', 'catedra');

        $result = TeacherService::create($data);

        Session::flash('success', 'Docente registrado correctamente.');
        Session::flash('credentials', $result['credentials']);
        $this->redirect('/teachers/' . $result['docente_id']);
    }

    public function show(string|int $id): void
    {
        $teacher = TeacherService::find($this->id($id));

        if ($teacher === null) {
            Session::flash('error', 'El docente no existe.');
            $this->redirect('/teachers');
        }

        $this->view('teachers.show', [
            'title' => 'Docente · ' . $teacher['nombres'] . ' ' . $teacher['apellidos'],
            'teacher' => $teacher,
            'load' => TeacherService::load($this->id($id)),
        ]);
    }

    public function edit(string|int $id): void
    {
        $teacher = TeacherService::find($this->id($id));

        if ($teacher === null) {
            Session::flash('error', 'El docente no existe.');
            $this->redirect('/teachers');
        }

        $this->view('teachers.edit', [
            'title' => 'Editar docente',
            'teacher' => $teacher,
            'documentTypes' => PersonService::documentTypes(),
            'sedes' => PersonService::sedes(),
        ]);
    }

    public function update(string|int $id): void
    {
        $teacher = TeacherService::find($this->id($id));

        if ($teacher === null) {
            Session::flash('error', 'El docente no existe.');
            $this->redirect('/teachers');
        }

        $this->validate($this->personRules() + [
            'sede_id' => 'required|int',
            'titulo' => 'nullable|max:150',
            'tipo_vinculacion' => 'required|in:planta,catedra,contrato',
            'estado' => 'nullable|in:activo,inactivo',
        ]);

        $data = $this->personData();
        $data['sede_id'] = Request::int('sede_id');
        $data['titulo'] = Request::string('titulo');
        $data['tipo_vinculacion'] = Request::string('tipo_vinculacion', (string) $teacher['tipo_vinculacion']);
        $data['estado'] = Request::string('estado', (string) $teacher['estado']);

        TeacherService::update($this->id($id), $data);

        Session::flash('success', 'Docente actualizado correctamente.');
        $this->redirect('/teachers/' . $this->id($id));
    }
}
