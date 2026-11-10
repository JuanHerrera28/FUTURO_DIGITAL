<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\DashboardService;

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

        $summary = DashboardService::summary();

        $this->view('dashboard.index', [
            'title' => 'Panel principal',
            'stats' => $summary['stats'],
            'alerts' => $summary['alerts'],
            'period' => $summary['period'],
        ]);
    }
}
