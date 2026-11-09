<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Controllers\Concerns\PeopleForms;
use App\Core\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Session;
use App\Services\PersonService;
use App\Services\UserService;

final class UsersController extends Controller
{
    use PeopleForms;

    public function index(): void
    {
        $filters = [
            'buscar' => Request::string('buscar'),
            'rol_id' => Request::int('rol_id'),
            'sede_id' => Request::int('sede_id'),
            'estado' => Request::string('estado'),
        ];
        $page = max(1, Request::int('page', 1));
        $result = UserService::paginate($filters, $page);

        $this->view('users.index', [
            'title' => 'Usuarios',
            'rows' => $result['rows'],
            'paginator' => Paginator::of($page, 20, $result['total']),
            'filters' => $filters,
            'roles' => UserService::roles(),
            'sedes' => PersonService::sedes(),
        ]);
    }

    public function create(): void
    {
        $this->view('users.create', [
            'title' => 'Nuevo usuario',
            'roles' => UserService::roles(true),
            'sedes' => PersonService::sedes(),
            'documentTypes' => PersonService::documentTypes(),
        ]);
    }

    public function store(): void
    {
        $this->validate($this->personRules() + [
            'rol_id' => 'required|int',
            'sede_id' => 'nullable|int',
        ], [
            'rol_id' => 'rol',
            'sede_id' => 'sede',
        ]);

        $sedeId = Request::int('sede_id');

        $result = UserService::createStaff(
            $this->personData(),
            Request::int('rol_id'),
            $sedeId > 0 ? $sedeId : null
        );

        Session::flash('success', 'Usuario creado correctamente.');
        Session::flash('credentials', $result['credentials']);
        $this->redirect('/users');
    }

    public function edit(string|int $id): void
    {
        $user = UserService::find($this->id($id));

        if ($user === null) {
            Session::flash('error', 'El usuario no existe.');
            $this->redirect('/users');
        }

        $this->view('users.edit', [
            'title' => 'Editar usuario',
            'user' => $user,
            'roles' => UserService::roles(),
            'sedes' => PersonService::sedes(),
        ]);
    }

    public function update(string|int $id): void
    {
        $this->validate([
            'rol_id' => 'required|int',
            'sede_id' => 'nullable|int',
            'estado' => 'required|in:activo,inactivo',
        ], [
            'rol_id' => 'rol',
            'sede_id' => 'sede',
            'estado' => 'estado',
        ]);

        $sedeId = Request::int('sede_id');

        UserService::update(
            $this->id($id),
            Request::int('rol_id'),
            $sedeId > 0 ? $sedeId : null,
            Request::string('estado', 'activo')
        );

        Session::flash('success', 'Usuario actualizado correctamente.');
        $this->redirect('/users');
    }

    public function resetPassword(string|int $id): void
    {
        $credentials = UserService::resetPassword($this->id($id));

        Session::flash('success', 'Contraseña temporal restablecida.');
        Session::flash('credentials', $credentials);
        $this->redirect('/users');
    }
}
