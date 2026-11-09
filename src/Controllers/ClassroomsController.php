<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Session;
use App\Services\ClassroomService;
use App\Services\PersonService;

final class ClassroomsController extends Controller
{
    public function index(): void
    {
        $filters = [
            'buscar' => Request::string('buscar'),
            'sede_id' => Request::int('sede_id'),
            'estado' => Request::string('estado'),
        ];
        $page = max(1, Request::int('page', 1));
        $result = ClassroomService::paginate($filters, $page);

        $this->view('classrooms.index', [
            'title' => 'Aulas',
            'rows' => $result['rows'],
            'paginator' => Paginator::of($page, 20, $result['total']),
            'filters' => $filters,
            'sedes' => PersonService::sedes(),
        ]);
    }

    public function create(): void
    {
        $this->view('classrooms.create', [
            'title' => 'Nueva aula',
            'sedes' => PersonService::sedes(),
        ]);
    }

    public function store(): void
    {
        $this->validate([
            'sede_id' => 'required|int',
            'nombre' => 'required|max:80',
            'capacidad' => 'required|int|minval:1|maxval:500',
        ], [
            'sede_id' => 'sede',
            'nombre' => 'nombre',
            'capacidad' => 'capacidad',
        ]);

        ClassroomService::create([
            'sede_id' => Request::int('sede_id'),
            'nombre' => Request::string('nombre'),
            'capacidad' => Request::int('capacidad'),
        ]);

        Session::flash('success', 'Aula creada correctamente.');
        $this->redirect('/classrooms');
    }

    public function edit(string|int $id): void
    {
        $classroom = ClassroomService::find($this->id($id));

        if ($classroom === null) {
            Session::flash('error', 'El aula no existe.');
            $this->redirect('/classrooms');
        }

        $this->view('classrooms.edit', [
            'title' => 'Editar aula',
            'classroom' => $classroom,
            'sedes' => PersonService::sedes(),
        ]);
    }

    public function update(string|int $id): void
    {
        $this->validate([
            'nombre' => 'required|max:80',
            'capacidad' => 'required|int|minval:1|maxval:500',
            'estado' => 'nullable|in:activo,inactivo',
        ]);

        ClassroomService::update($this->id($id), [
            'nombre' => Request::string('nombre'),
            'capacidad' => Request::int('capacidad'),
            'estado' => Request::string('estado', 'activo'),
        ]);

        Session::flash('success', 'Aula actualizada correctamente.');
        $this->redirect('/classrooms');
    }
}
