<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\DashboardService;
use App\Services\GroupService;
use App\Services\IdentityService;
use App\Services\PeriodService;

final class TeachingController extends Controller
{
    public function index(): void
    {
        $docente = IdentityService::docente();

        if ($docente === null) {
            Session::flash('error', 'El módulo docente solo está disponible para usuarios con ficha de docente.');
            $this->redirect('/dashboard');
        }

        $period = DashboardService::currentPeriod();
        $periodoId = Request::int('periodo_id', $period === null ? 0 : (int) $period['id']);

        $this->view('teaching.index', [
            'title' => 'Mi carga académica',
            'docente' => $docente,
            'periods' => PeriodService::options(),
            'periodoId' => $periodoId,
            'groups' => GroupService::forTeacher((int) $docente['id'], $periodoId > 0 ? $periodoId : null),
        ]);
    }
}
