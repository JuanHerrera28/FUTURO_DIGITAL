<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Services\ParamService;

final class ParamsController extends Controller
{
    public function index(): void
    {
        $this->view('params.index', [
            'title' => 'Parámetros del sistema',
            'groups' => ParamService::grouped(),
        ]);
    }

    public function update(): void
    {
        $values = (array) Request::input('parametros', []);
        $groups = ParamService::grouped();

        foreach ($groups as $params) {
            foreach ($params as $param) {
                if ((string) $param['tipo'] === 'bool' && !array_key_exists((string) $param['clave'], $values)) {
                    $values[(string) $param['clave']] = '0';
                }
            }
        }

        $changed = ParamService::updateMany($values);

        Session::flash('success', $changed === 0
            ? 'No hubo cambios en los parámetros.'
            : 'Se actualizaron ' . $changed . ' parámetro(s). Los cambios quedaron auditados.');

        $this->redirect('/params');
    }
}
