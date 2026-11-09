<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\RoleService;

final class RolesController extends Controller
{
    public function index(): void
    {
        $this->view('roles.index', [
            'title' => 'Roles y permisos',
            'rows' => RoleService::all(),
        ]);
    }

    public function create(): void
    {
        $this->view('roles.create', ['title' => 'Nuevo rol']);
    }

    public function store(): void
    {
        $this->validate([
            'nombre' => 'required|max:60',
            'descripcion' => 'nullable|max:255',
        ], [
            'nombre' => 'nombre',
            'descripcion' => 'descripción',
        ]);

        $id = RoleService::create(Request::string('nombre'), Request::string('descripcion'));

        Session::flash('success', 'Rol creado. Asigne ahora sus permisos.');
        $this->redirect('/roles/' . $id . '/edit');
    }

    public function edit(string|int $id): void
    {
        $role = RoleService::find($this->id($id));

        if ($role === null) {
            Session::flash('error', 'El rol no existe.');
            $this->redirect('/roles');
        }

        $this->view('roles.edit', [
            'title' => 'Rol · ' . $role['nombre'],
            'role' => $role,
            'permissions' => RoleService::permissionsByModule(),
            'assigned' => RoleService::permissionIds($this->id($id)),
        ]);
    }

    public function update(string|int $id): void
    {
        $this->validate([
            'nombre' => 'required|max:60',
            'descripcion' => 'nullable|max:255',
            'estado' => 'required|in:activo,inactivo',
        ], [
            'nombre' => 'nombre',
            'descripcion' => 'descripción',
            'estado' => 'estado',
        ]);

        RoleService::update(
            $this->id($id),
            Request::string('nombre'),
            Request::string('descripcion'),
            Request::string('estado', 'activo')
        );

        Session::flash('success', 'Rol actualizado correctamente.');
        $this->redirect('/roles');
    }

    public function updatePermissions(string|int $id): void
    {
        $permissions = (array) Request::input('permisos', []);

        RoleService::updatePermissions($this->id($id), $permissions);

        Session::flash('success', 'Permisos del rol actualizados. Los usuarios verán los cambios en su próxima sesión.');
        $this->redirect('/roles/' . $this->id($id) . '/edit');
    }
}
