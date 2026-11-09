<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Config;
use App\Core\Controller;
use App\Core\DomainException;
use App\Core\Session;

final class AuthController extends Controller
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            $this->redirect('/dashboard');
        }

        $this->view('auth.login', ['title' => 'Iniciar sesión'], 'layouts.auth');
    }

    public function login(): void
    {
        $data = $this->validate([
            'username' => 'required|max:80',
            'password' => 'required|max:255',
        ], [
            'username' => 'usuario',
            'password' => 'contraseña',
        ]);

        try {
            Auth::attempt((string) $data['username'], (string) $data['password']);
        } catch (DomainException $e) {
            Session::set('_old', ['username' => $data['username']]);
            Session::flash('error', $e->fullMessage());
            $this->redirect('/login');
        }

        Session::flash('success', 'Bienvenido(a), ' . (Auth::user()['nombre'] ?? '') . '.');

        if (Auth::mustChangePassword()) {
            Session::flash('warning', 'Por seguridad, debe cambiar la contraseña temporal.');
            $this->redirect('/change-password');
        }

        $intended = (string) Session::pull('_intended', '');
        if ($intended !== '' && str_starts_with($intended, '/') && !str_starts_with($intended, '//')) {
            $this->redirect($intended);
        }

        $this->redirect('/dashboard');
    }

    public function logout(): void
    {
        Auth::logout();
        Session::start((array) Config::get('session', []));
        Session::flash('success', 'La sesión se cerró correctamente.');
        $this->redirect('/login');
    }

    public function showChangePassword(): void
    {
        $this->view('auth.change-password', [
            'title' => 'Cambiar contraseña',
            'obligatorio' => Auth::mustChangePassword(),
        ]);
    }

    public function changePassword(): void
    {
        $data = $this->validate([
            'actual' => 'required|max:255',
            'nueva' => 'required|strong_password|max:255',
            'nueva_confirmed' => 'required|max:255',
        ], [
            'actual' => 'contraseña actual',
            'nueva' => 'nueva contraseña',
            'nueva_confirmed' => 'confirmación',
        ]);

        if ($data['nueva'] !== $data['nueva_confirmed']) {
            $this->fail('La confirmación de la nueva contraseña no coincide.', '', [
                'actual' => '',
                'nueva' => '',
                'nueva_confirmed' => '',
            ], '/change-password');
        }

        try {
            Auth::changePassword((string) $data['actual'], (string) $data['nueva']);
        } catch (DomainException $e) {
            Session::flash('error', $e->fullMessage());
            $this->redirect('/change-password');
        }

        Session::flash('success', 'La contraseña se actualizó correctamente.');
        $this->redirect('/dashboard');
    }
}
