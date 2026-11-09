<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\IdentityService;
use App\Services\PortalService;

final class PortalController extends Controller
{
    public function index(): void
    {
        $estudianteId = IdentityService::requireEstudiante();
        $data = PortalService::dashboard($estudianteId);

        if ($data === []) {
            Session::flash('error', 'No se encontró la ficha del estudiante asociada a su usuario.');
            $this->redirect('/dashboard');
        }

        $this->view('portal.index', [
            'title' => 'Mi portal',
            'student' => $data['student'],
            'matricula' => $data['matricula'],
            'grades' => $data['grades'],
            'attendance' => $data['attendance'],
            'statement' => $data['statement'],
            'schedule' => $data['schedule'],
            'alerts' => $data['alerts'],
            'guardians' => $data['guardians'],
        ]);
    }
}
