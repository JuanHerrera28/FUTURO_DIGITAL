<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Session;
use App\Services\GradeService;
use App\Services\GroupService;
use App\Services\IdentityService;
use App\Services\PeriodService;

final class GradesController extends Controller
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

        $this->view('grades.index', [
            'title' => 'Calificaciones',
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
            $this->redirect('/grades');
        }

        $this->view('grades.show', [
            'title' => 'Calificaciones · ' . $group['codigo'],
            'group' => $group,
            'matrix' => GradeService::matrix($this->id($groupId)),
            'owns' => $this->owns($group),
        ]);
    }

    public function storeEvaluation(string|int $groupId): void
    {
        $this->validate([
            'nombre' => 'required|max:120',
            'peso' => 'required|numeric|minval:0.1|maxval:100',
            'fecha' => 'nullable|date',
        ], [
            'nombre' => 'nombre de la evaluación',
            'peso' => 'peso',
        ]);

        GradeService::addEvaluation(
            $this->id($groupId),
            Request::string('nombre'),
            Request::decimal('peso'),
            Request::string('fecha') !== '' ? Request::string('fecha') : null
        );

        Session::flash('success', 'Evaluación registrada correctamente.');
        $this->redirect('/grades/' . $this->id($groupId));
    }

    public function updateEvaluation(string|int $groupId, string|int $evaluationId): void
    {
        $this->validate([
            'nombre' => 'required|max:120',
            'peso' => 'required|numeric|minval:0.1|maxval:100',
            'fecha' => 'nullable|date',
            'estado' => 'nullable|in:activa,inactiva',
        ]);

        GradeService::updateEvaluation(
            $this->id($evaluationId),
            Request::string('nombre'),
            Request::decimal('peso'),
            Request::string('fecha') !== '' ? Request::string('fecha') : null,
            Request::string('estado', 'activa')
        );

        Session::flash('success', 'Evaluación actualizada.');
        $this->redirect('/grades/' . $this->id($groupId));
    }

    public function deactivateEvaluation(string|int $groupId, string|int $evaluationId): void
    {
        GradeService::deactivateEvaluation($this->id($evaluationId));

        Session::flash('success', 'Evaluación inactivada.');
        $this->redirect('/grades/' . $this->id($groupId));
    }

    public function sheet(string|int $groupId, string|int $evaluationId): void
    {
        $group = GroupService::find($this->id($groupId));

        if ($group === null) {
            Session::flash('error', 'El grupo no existe.');
            $this->redirect('/grades');
        }

        $this->view('grades.sheet', [
            'title' => 'Planilla · ' . $group['codigo'],
            'group' => $group,
            'evaluation' => $this->evaluation($this->id($groupId), $this->id($evaluationId)),
            'rows' => GradeService::gradeSheet($this->id($groupId), $this->id($evaluationId)),
        ]);
    }

    public function saveGrades(string|int $groupId, string|int $evaluationId): void
    {
        GradeService::saveGrades(
            $this->id($groupId),
            $this->id($evaluationId),
            (array) Request::input('notas', []),
            (array) Request::input('observaciones', [])
        );

        Session::flash('success', 'Notas guardadas correctamente.');
        $this->redirect('/grades/' . $this->id($groupId) . '/evaluations/' . $this->id($evaluationId));
    }

    /** @param array<string,mixed> $group */
    private function owns(array $group): bool
    {
        return Auth::isDocente() && IdentityService::docenteId() === (int) $group['docente_id'];
    }

    /** @return array<string,mixed> */
    private function evaluation(int $groupId, int $evaluationId): array
    {
        foreach (GradeService::evaluations($groupId) as $evaluation) {
            if ((int) $evaluation['id'] === $evaluationId) {
                return $evaluation;
            }
        }

        Session::flash('error', 'La evaluación no existe en el grupo.');
        $this->redirect('/grades/' . $groupId);
    }
}
