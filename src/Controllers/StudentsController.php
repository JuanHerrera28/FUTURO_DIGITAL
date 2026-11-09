<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Concerns\PeopleForms;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Session;
use App\Services\PersonService;
use App\Services\StudentService;

final class StudentsController extends Controller
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
        $result = StudentService::paginate($filters, $page);

        $this->view('students.index', [
            'title' => 'Estudiantes',
            'rows' => $result['rows'],
            'paginator' => Paginator::of($page, 20, $result['total']),
            'filters' => $filters,
            'sedes' => PersonService::sedes(),
        ]);
    }

    public function create(): void
    {
        $this->view('students.create', [
            'title' => 'Nuevo estudiante',
            'documentTypes' => PersonService::documentTypes(),
            'sedes' => PersonService::sedes(),
        ]);
    }

    public function store(): void
    {
        $this->validate($this->personRules() + [
            'sede_id' => 'required|int',
            'autorizacion_datos' => 'nullable',
        ], [
            'tipo_documento_id' => 'tipo de documento',
            'numero_documento' => 'número de documento',
            'nombres' => 'nombres',
            'apellidos' => 'apellidos',
            'sede_id' => 'sede',
        ]);

        $data = $this->personData();
        $data['sede_id'] = Request::int('sede_id');
        $data['estado'] = 'activo';

        $id = StudentService::create($data);

        Session::flash('success', 'Estudiante registrado correctamente.');
        $this->redirect('/students/' . $id);
    }

    public function show(string|int $id): void
    {
        $student = StudentService::find($this->id($id));

        if ($student === null) {
            Session::flash('error', 'El estudiante no existe.');
            $this->redirect('/students');
        }

        $this->view('students.show', [
            'title' => 'Estudiante · ' . $student['nombres'] . ' ' . $student['apellidos'],
            'student' => $student,
            'guardians' => StudentService::guardians($this->id($id)),
            'history' => StudentService::academicHistory($this->id($id)),
            'matriculas' => StudentService::enrollmentOptions($this->id($id)),
            'documentTypes' => PersonService::documentTypes(),
        ]);
    }

    public function edit(string|int $id): void
    {
        $student = StudentService::find($this->id($id));

        if ($student === null) {
            Session::flash('error', 'El estudiante no existe.');
            $this->redirect('/students');
        }

        $this->view('students.edit', [
            'title' => 'Editar estudiante',
            'student' => $student,
            'documentTypes' => PersonService::documentTypes(),
            'sedes' => PersonService::sedes(),
        ]);
    }

    public function update(string|int $id): void
    {
        $student = StudentService::find($this->id($id));

        if ($student === null) {
            Session::flash('error', 'El estudiante no existe.');
            $this->redirect('/students');
        }

        $this->validate($this->personRules() + [
            'sede_id' => 'required|int',
            'estado' => 'nullable|in:activo,inactivo,retirado,graduado',
        ]);

        $data = $this->personData();
        $data['sede_id'] = Request::int('sede_id');
        $data['estado'] = Request::string('estado', (string) $student['estado']);

        StudentService::update($this->id($id), $data);

        Session::flash('success', 'Estudiante actualizado correctamente.');
        $this->redirect('/students/' . $this->id($id));
    }

    public function storeGuardian(string|int $id): void
    {
        $this->validate([
            'nombre' => 'required|max:150',
            'documento' => 'nullable|max:30',
            'telefono' => 'nullable|max:30',
            'parentesco' => 'nullable|max:60',
        ], [
            'nombre' => 'nombre del acudiente',
        ]);

        StudentService::addGuardian($this->id($id), [
            'nombre' => Request::string('nombre'),
            'documento' => Request::string('documento'),
            'telefono' => Request::string('telefono'),
            'parentesco' => Request::string('parentesco'),
        ]);

        Session::flash('success', 'Acudiente agregado.');
        $this->redirect('/students/' . $this->id($id) . '#acudientes');
    }

    public function toggleGuardian(string|int $id): void
    {
        StudentService::toggleGuardian($this->id($id));

        Session::flash('success', 'Estado del acudiente actualizado.');
        $this->back('/students');
    }
}
