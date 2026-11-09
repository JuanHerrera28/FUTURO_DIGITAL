<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Core\Session;
use App\Services\AlertService;
use App\Services\PersonService;

final class AlertsController extends Controller
{
    public function index(): void
    {
        $filters = [
            'buscar' => Request::string('buscar'),
            'tipo' => Request::string('tipo'),
            'nivel' => Request::string('nivel'),
            'estado' => Request::string('estado'),
            'sede_id' => Request::int('sede_id'),
        ];
        $page = max(1, Request::int('page', 1));

        AlertService::syncMora();
        $result = AlertService::paginate($filters, $page);

        $this->view('alerts.index', [
            'title' => 'Alertas',
            'rows' => $result['rows'],
            'paginator' => Paginator::of($page, 25, $result['total']),
            'filters' => $filters,
            'sedes' => PersonService::sedes(),
        ]);
    }

    public function attend(string|int $id): void
    {
        AlertService::attend($this->id($id));

        Session::flash('success', 'Alerta marcada como atendida.');
        $this->back('/alerts');
    }
}
