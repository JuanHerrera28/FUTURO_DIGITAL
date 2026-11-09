<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;

final class DashboardController extends Controller
{
    public function index(): void
    {
        if (!Auth::check()) {
            $this->redirect('/login');
        }

        if (Auth::isEstudiante()) {
            $this->redirect('/portal');
        }

        if (Auth::isDocente()) {
            $this->redirect('/teaching');
        }

        $this->view('dashboard.index', [
            'title' => 'Panel principal',
        ]);
    }
}
