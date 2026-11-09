<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\PersonService;
use App\Services\ProgramService;
use App\Services\ReportService;

final class ReportsController extends Controller
{
    public function index(): void
    {
        $this->view('reports.index', [
            'title' => 'Informes',
            'catalog' => ReportService::catalog(),
        ]);
    }

    public function run(): void
    {
        $key = Request::string('reporte', 'estudiantes');
        $filters = $this->filters();
        $report = ReportService::run($key, $filters);

        $this->view('reports.show', [
            'title' => 'Informe · ' . $report['title'],
            'catalog' => ReportService::catalog(),
            'report' => $report,
            'filters' => $filters,
            'sedes' => PersonService::sedes(),
            'periods' => \App\Services\PeriodService::options(),
            'programs' => ProgramService::options(),
            'groups' => \App\Services\GroupService::options(),
        ]);
    }

    public function export(): void
    {
        $key = Request::string('reporte', 'estudiantes');
        $report = ReportService::run($key, $this->filters());

        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            $this->back('/reports');
        }

        fputcsv($handle, array_values($report['columns']));

        foreach ($report['rows'] as $row) {
            $line = [];
            foreach (array_keys($report['columns']) as $column) {
                $line[] = $row[$column] ?? '';
            }
            fputcsv($handle, $line);
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        $filename = 'informe_' . $report['key'] . '_' . date('Ymd_His') . '.csv';

        Response::download($filename, "\xEF\xBB\xBF" . $csv);
    }

    /** @return array<string,mixed> */
    private function filters(): array
    {
        return [
            'sede_id' => Request::int('sede_id'),
            'periodo_id' => Request::int('periodo_id'),
            'programa_id' => Request::int('programa_id'),
            'grupo_id' => Request::int('grupo_id'),
            'estado' => Request::string('estado'),
            'buscar' => Request::string('buscar'),
            'tipo' => Request::string('tipo'),
            'nivel' => Request::string('nivel'),
            'desde' => Request::string('desde'),
            'hasta' => Request::string('hasta'),
            'medio' => Request::string('medio'),
        ];
    }
}
