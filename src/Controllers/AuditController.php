<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Paginator;
use App\Core\Request;
use App\Services\AuditService;

final class AuditController extends Controller
{
    public function index(): void
    {
        $filters = [
            'buscar' => Request::string('buscar'),
            'modulo' => Request::string('modulo'),
            'accion' => Request::string('accion'),
            'desde' => Request::string('desde'),
            'hasta' => Request::string('hasta'),
        ];
        $page = max(1, Request::int('page', 1));
        $result = AuditService::paginate($filters, $page);

        $this->view('audit.index', [
            'title' => 'Auditoría',
            'rows' => $result['rows'],
            'paginator' => Paginator::of($page, 30, $result['total']),
            'filters' => $filters,
            'modules' => AuditService::modules(),
            'actions' => AuditService::actions(),
        ]);
    }
}
