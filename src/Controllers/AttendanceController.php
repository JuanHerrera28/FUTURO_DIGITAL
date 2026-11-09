<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Session;
use App\Services\AttendanceService;
use App\Services\GroupService;
use App\Services\IdentityService;
use App\Services\PeriodService;

final class AttendanceController extends Controller
{
    public function index(): void
    {
        $filters = [
            'buscar' => Request::string('buscar'),
            'periodo_id' => Request::int('periodo_id'),
            'sede_id' => Request::int('sede_id'),
        ];
        $page = max(1, Request::int('page', 1));

        if (Auth::isDocente()) {
            $docenteId = IdentityService::docenteId();
            $rows = $docenteId === null
                ? []
                : GroupService::forTeacher($docenteId, $filters['periodo_id'] ?: null);
            $paginator = Paginator::of(1, max(1, count($rows)), count($rows));
        } else {
            $result = GroupService::paginate($filters, $page);
            $rows = $result['rows'];
            $paginator = Paginator::of($page, 20, $result['total']);
        }

        $this->view('attendance.index', [
            'title' => 'Asistencia',
            'rows' => $rows,
            'paginator' => $paginator,
            'filters' => $filters,
            'periods' => PeriodService::options(),
        ]);
    }

    public function show(string|int $groupId): void
    {
        $group = GroupService::find($this->id($groupId));

        if ($group === null) {
            Session::flash('error', 'El grupo no existe.');
            $this->redirect('/attendance');
        }

        $this->view('attendance.show', [
            'title' => 'Asistencia · ' . $group['codigo'],
            'group' => $group,
            'sessions' => AttendanceService::sessions($this->id($groupId)),
            'summary' => AttendanceService::summary($this->id($groupId)),
            'owns' => $this->owns($group),
        ]);
    }

    public function storeSession(string|int $groupId): void
    {
        $this->validate([
            'fecha' => 'required|date',
            'hora_inicio' => 'required',
            'hora_fin' => 'required',
            'tema' => 'nullable|max:180',
        ], [
            'fecha' => 'fecha',
            'hora_inicio' => 'hora de inicio',
            'hora_fin' => 'hora de fin',
        ]);

        $sessionId = AttendanceService::createSession(
            $this->id($groupId),
            Request::string('fecha'),
            Request::string('hora_inicio'),
            Request::string('hora_fin'),
            Request::string('tema') !== '' ? Request::string('tema') : null
        );

        Session::flash('success', 'Sesión creada. Registre la asistencia.');
        $this->redirect('/attendance/' . $this->id($groupId) . '/sessions/' . $sessionId);
    }

    public function sheet(string|int $groupId, string|int $sessionId): void
    {
        $group = GroupService::find($this->id($groupId));

        if ($group === null) {
            Session::flash('error', 'El grupo no existe.');
            $this->redirect('/attendance');
        }

        $this->view('attendance.sheet', [
            'title' => 'Asistencia · ' . $group['codigo'],
            'group' => $group,
            'session' => AttendanceService::findSession($this->id($sessionId)),
            'rows' => AttendanceService::sheet($this->id($sessionId)),
        ]);
    }

    public function save(string|int $groupId, string|int $sessionId): void
    {
        AttendanceService::save(
            $this->id($sessionId),
            (array) Request::input('estados', []),
            (array) Request::input('observaciones', [])
        );

        Session::flash('success', 'Asistencia guardada correctamente.');
        $this->redirect('/attendance/' . $this->id($groupId));
    }

    /** @param array<string,mixed> $group */
    private function owns(array $group): bool
    {
        return Auth::isDocente() && IdentityService::docenteId() === (int) $group['docente_id'];
    }
}
