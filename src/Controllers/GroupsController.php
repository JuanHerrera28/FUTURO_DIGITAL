<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\HttpException;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Session;
use App\Services\ClassroomService;
use App\Services\CourseService;
use App\Services\GroupService;
use App\Services\IdentityService;
use App\Services\PeriodService;
use App\Services\PersonService;
use App\Services\TeacherService;

final class GroupsController extends Controller
{
    public function index(): void
    {
        $filters = [
            'buscar' => Request::string('buscar'),
            'periodo_id' => Request::int('periodo_id'),
            'sede_id' => Request::int('sede_id'),
            'estado' => Request::string('estado'),
        ];
        $page = max(1, Request::int('page', 1));

        // El docente solo ve sus propios grupos.
        if (Auth::isDocente()) {
            $filters['docente_id'] = IdentityService::docenteId() ?? -1;
        }

        $result = GroupService::paginate($filters, $page);

        $this->view('groups.index', [
            'title' => 'Grupos',
            'rows' => $result['rows'],
            'paginator' => Paginator::of($page, 20, $result['total']),
            'filters' => $filters,
            'periods' => PeriodService::options(),
            'sedes' => PersonService::sedes(),
        ]);
    }

    public function create(): void
    {
        $this->view('groups.create', [
            'title' => 'Nuevo grupo',
            'group' => null,
            'schedules' => [],
            'courses' => CourseService::options(),
            'teachers' => TeacherService::options(),
            'periods' => PeriodService::options(),
            'classrooms' => ClassroomService::options(),
            'sedes' => PersonService::sedes(),
        ]);
    }

    public function store(): void
    {
        $this->validate([
            'curso_id' => 'required|int',
            'docente_id' => 'required|int',
            'periodo_id' => 'required|int',
            'sede_id' => 'required|int',
            'aula_id' => 'required|int',
            'capacidad_max' => 'required|int|minval:1|maxval:500',
            'cupo_minimo' => 'nullable|int|minval:1|maxval:500',
        ], [
            'curso_id' => 'curso',
            'docente_id' => 'docente',
            'periodo_id' => 'periodo',
            'sede_id' => 'sede',
            'aula_id' => 'aula',
            'capacidad_max' => 'capacidad máxima',
        ]);

        $id = GroupService::create(
            $this->data(),
            (array) Request::input('days', []),
            (array) Request::input('starts', []),
            (array) Request::input('ends', [])
        );

        Session::flash('success', 'Grupo creado correctamente.');
        $this->redirect('/groups/' . $id);
    }

    public function show(string|int $id): void
    {
        $group = GroupService::find($this->id($id));

        if ($group === null) {
            Session::flash('error', 'El grupo no existe.');
            $this->redirect('/groups');
        }

        if (Auth::isDocente() && (int) $group['docente_id'] !== (int) IdentityService::docenteId()) {
            throw new HttpException(403, 'Solo puede consultar sus propios grupos.');
        }

        $this->view('groups.show', [
            'title' => 'Grupo · ' . $group['codigo'],
            'group' => $group,
            'schedules' => GroupService::schedules($this->id($id)),
            'students' => GroupService::students($this->id($id)),
            'seats' => GroupService::availableSeats($this->id($id)),
        ]);
    }

    public function edit(string|int $id): void
    {
        $group = GroupService::find($this->id($id));

        if ($group === null) {
            Session::flash('error', 'El grupo no existe.');
            $this->redirect('/groups');
        }

        $this->view('groups.edit', [
            'title' => 'Editar grupo',
            'group' => $group,
            'schedules' => GroupService::schedules($this->id($id)),
            'courses' => CourseService::options(),
            'teachers' => TeacherService::options(),
            'periods' => PeriodService::options(),
            'classrooms' => ClassroomService::options(),
            'sedes' => PersonService::sedes(),
        ]);
    }

    public function update(string|int $id): void
    {
        $this->validate([
            'curso_id' => 'required|int',
            'docente_id' => 'required|int',
            'periodo_id' => 'required|int',
            'sede_id' => 'required|int',
            'aula_id' => 'required|int',
            'capacidad_max' => 'required|int|minval:1|maxval:500',
            'cupo_minimo' => 'nullable|int|minval:1|maxval:500',
            'estado' => 'nullable|in:activo,inactivo,cancelado',
        ]);

        GroupService::update(
            $this->id($id),
            $this->data() + ['estado' => Request::string('estado', 'activo')],
            (array) Request::input('days', []),
            (array) Request::input('starts', []),
            (array) Request::input('ends', [])
        );

        Session::flash('success', 'Grupo actualizado correctamente.');
        $this->redirect('/groups/' . $this->id($id));
    }

    /** @return array<string,mixed> */
    private function data(): array
    {
        return [
            'curso_id' => Request::int('curso_id'),
            'docente_id' => Request::int('docente_id'),
            'periodo_id' => Request::int('periodo_id'),
            'sede_id' => Request::int('sede_id'),
            'aula_id' => Request::int('aula_id'),
            'capacidad_max' => Request::int('capacidad_max'),
            'cupo_minimo' => Request::int('cupo_minimo', 5),
        ];
    }
}
