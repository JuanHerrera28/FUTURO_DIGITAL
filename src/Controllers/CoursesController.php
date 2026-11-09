<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Session;
use App\Services\CourseService;

final class CoursesController extends Controller
{
    public function index(): void
    {
        $filters = [
            'buscar' => Request::string('buscar'),
            'estado' => Request::string('estado'),
        ];
        $page = max(1, Request::int('page', 1));
        $result = CourseService::paginate($filters, $page);

        $this->view('courses.index', [
            'title' => 'Cursos',
            'rows' => $result['rows'],
            'paginator' => Paginator::of($page, 20, $result['total']),
            'filters' => $filters,
        ]);
    }

    public function create(): void
    {
        $this->view('courses.create', ['title' => 'Nuevo curso']);
    }

    public function store(): void
    {
        $this->validate([
            'codigo' => 'required|max:20',
            'nombre' => 'required|max:150',
        ], [
            'codigo' => 'código',
            'nombre' => 'nombre',
        ]);

        $id = CourseService::create([
            'codigo' => Request::string('codigo'),
            'nombre' => Request::string('nombre'),
            'estado' => 'activo',
        ]);

        Session::flash('success', 'Curso creado correctamente.');
        $this->redirect('/courses/' . $id);
    }

    public function show(string|int $id): void
    {
        $course = CourseService::find($this->id($id));

        if ($course === null) {
            Session::flash('error', 'El curso no existe.');
            $this->redirect('/courses');
        }

        $this->view('courses.show', [
            'title' => 'Curso · ' . $course['nombre'],
            'course' => $course,
            'programs' => CourseService::programsOf($this->id($id)),
        ]);
    }

    public function edit(string|int $id): void
    {
        $course = CourseService::find($this->id($id));

        if ($course === null) {
            Session::flash('error', 'El curso no existe.');
            $this->redirect('/courses');
        }

        $this->view('courses.edit', [
            'title' => 'Editar curso',
            'course' => $course,
        ]);
    }

    public function update(string|int $id): void
    {
        $this->validate([
            'codigo' => 'required|max:20',
            'nombre' => 'required|max:150',
            'estado' => 'nullable|in:activo,inactivo',
        ]);

        CourseService::update($this->id($id), [
            'codigo' => Request::string('codigo'),
            'nombre' => Request::string('nombre'),
            'estado' => Request::string('estado', 'activo'),
        ]);

        Session::flash('success', 'Curso actualizado correctamente.');
        $this->redirect('/courses/' . $this->id($id));
    }
}
