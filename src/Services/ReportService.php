<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Param;

final class ReportService
{
    /**
     * @return array<string,array<string,mixed>>
     */
    public static function catalog(): array
    {
        return [
            'estudiantes' => [
                'title' => 'Estudiantes por sede',
                'description' => 'Listado de estudiantes con su sede, documento y estado.',
                'icon' => 'people',
                'filters' => ['sede', 'estado', 'buscar'],
            ],
            'matriculas' => [
                'title' => 'Matrículas por periodo',
                'description' => 'Matrículas registradas por periodo, programa y sede.',
                'icon' => 'person-plus',
                'filters' => ['periodo', 'programa', 'sede', 'estado'],
            ],
            'grupos' => [
                'title' => 'Ocupación de grupos',
                'description' => 'Capacidad, inscritos y cupos disponibles por grupo.',
                'icon' => 'collection',
                'filters' => ['periodo', 'sede'],
            ],
            'notas' => [
                'title' => 'Consolidado de calificaciones',
                'description' => 'Notas por evaluación y nota final de un grupo.',
                'icon' => 'clipboard-check',
                'filters' => ['grupo'],
            ],
            'asistencia' => [
                'title' => 'Consolidado de asistencia',
                'description' => 'Inasistencias y porcentaje por estudiante de un grupo.',
                'icon' => 'calendar-check',
                'filters' => ['grupo'],
            ],
            'cartera' => [
                'title' => 'Cartera y saldos',
                'description' => 'Cargos, pagos aplicados, saldo y vencimiento por estudiante.',
                'icon' => 'cash-coin',
                'filters' => ['sede', 'estado', 'buscar'],
            ],
            'pagos' => [
                'title' => 'Pagos registrados',
                'description' => 'Pagos y comprobantes en un rango de fechas.',
                'icon' => 'receipt',
                'filters' => ['sede', 'desde', 'hasta', 'medio', 'estado'],
            ],
            'docentes' => [
                'title' => 'Carga docente',
                'description' => 'Grupos y estudiantes asignados a cada docente.',
                'icon' => 'person-badge',
                'filters' => ['sede', 'estado'],
            ],
            'riesgo' => [
                'title' => 'Alertas y riesgo académico',
                'description' => 'Alertas abiertas por inasistencia, bajo rendimiento y mora.',
                'icon' => 'exclamation-triangle',
                'filters' => ['sede', 'tipo', 'nivel'],
            ],
        ];
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{key:string,title:string,description:string,columns:array<string,string>,rows:list<array<string,mixed>>,summary:array<string,mixed>}
     */
    public static function run(string $key, array $filters): array
    {
        $catalog = self::catalog();

        if (!isset($catalog[$key])) {
            $key = 'estudiantes';
        }

        $report = match ($key) {
            'matriculas' => self::matriculas($filters),
            'grupos' => self::grupos($filters),
            'notas' => self::notas($filters),
            'asistencia' => self::asistencia($filters),
            'cartera' => self::cartera($filters),
            'pagos' => self::pagos($filters),
            'docentes' => self::docentes($filters),
            'riesgo' => self::riesgo($filters),
            default => self::estudiantes($filters),
        };

        return array_merge([
            'key' => $key,
            'title' => (string) $catalog[$key]['title'],
            'description' => (string) $catalog[$key]['description'],
        ], $report);
    }

    /** @param array<string,mixed> $filters */
    private static function estudiantes(array $filters): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['buscar'])) {
            $term = '%' . $filters['buscar'] . '%';
            $where[] = '(p.nombres LIKE ? OR p.apellidos LIKE ? OR p.numero_documento LIKE ? OR e.codigo LIKE ?)';
            array_push($params, $term, $term, $term, $term);
        }

        if (!empty($filters['sede_id'])) {
            $where[] = 'e.sede_id = ?';
            $params[] = (int) $filters['sede_id'];
        }

        if (!empty($filters['estado'])) {
            $where[] = 'e.estado = ?';
            $params[] = $filters['estado'];
        }

        $scope = Auth::sedeScope();
        if ($scope !== null) {
            $where[] = 'e.sede_id = ?';
            $params[] = $scope;
        }

        $rows = Database::fetchAll(
            "SELECT e.codigo, p.numero_documento, p.apellidos, p.nombres, s.nombre AS sede,
                    e.estado, p.telefono, p.email,
                    (SELECT pr.nombre FROM matriculas m
                     JOIN programas pr ON pr.id = m.programa_id
                     WHERE m.estudiante_id = e.id
                     ORDER BY m.fecha DESC LIMIT 1) AS programa
             FROM estudiantes e
             JOIN personas p ON p.id = e.persona_id
             JOIN sedes s ON s.id = e.sede_id
             WHERE " . implode(' AND ', $where) . '
             ORDER BY s.nombre, p.apellidos, p.nombres',
            $params
        );

        return [
            'columns' => [
                'codigo' => 'Código',
                'numero_documento' => 'Documento',
                'apellidos' => 'Apellidos',
                'nombres' => 'Nombres',
                'sede' => 'Sede',
                'programa' => 'Último programa',
                'estado' => 'Estado',
                'telefono' => 'Teléfono',
                'email' => 'Correo',
            ],
            'rows' => $rows,
            'summary' => ['Total estudiantes' => count($rows)],
        ];
    }

    /** @param array<string,mixed> $filters */
    private static function matriculas(array $filters): array
    {
        $where = ['1=1'];
        $params = [];

        $periodId = (int) ($filters['periodo_id'] ?? 0);
        if ($periodId <= 0) {
            $period = DashboardService::currentPeriod();
            $periodId = $period === null ? 0 : (int) $period['id'];
        }

        if ($periodId > 0) {
            $where[] = 'm.periodo_id = ?';
            $params[] = $periodId;
        }

        if (!empty($filters['programa_id'])) {
            $where[] = 'm.programa_id = ?';
            $params[] = (int) $filters['programa_id'];
        }

        if (!empty($filters['sede_id'])) {
            $where[] = 'm.sede_id = ?';
            $params[] = (int) $filters['sede_id'];
        }

        if (!empty($filters['estado'])) {
            $where[] = 'm.estado = ?';
            $params[] = $filters['estado'];
        }

        $scope = Auth::sedeScope();
        if ($scope !== null) {
            $where[] = 'm.sede_id = ?';
            $params[] = $scope;
        }

        $rows = Database::fetchAll(
            "SELECT m.codigo, p.numero_documento, p.apellidos, p.nombres,
                    pr.nombre AS programa, pe.nombre AS periodo, s.nombre AS sede,
                    m.fecha, m.estado,
                    (SELECT COUNT(*) FROM inscripciones i WHERE i.matricula_id = m.id AND i.estado = 'activa') AS cursos
             FROM matriculas m
             JOIN estudiantes e ON e.id = m.estudiante_id
             JOIN personas p ON p.id = e.persona_id
             JOIN programas pr ON pr.id = m.programa_id
             JOIN periodos pe ON pe.id = m.periodo_id
             JOIN sedes s ON s.id = m.sede_id
             WHERE " . implode(' AND ', $where) . '
             ORDER BY s.nombre, pr.nombre, p.apellidos, p.nombres',
            $params
        );

        $active = count(array_filter($rows, static fn (array $row): bool => $row['estado'] === 'activa'));

        return [
            'columns' => [
                'codigo' => 'Matrícula',
                'numero_documento' => 'Documento',
                'apellidos' => 'Apellidos',
                'nombres' => 'Nombres',
                'programa' => 'Programa',
                'periodo' => 'Periodo',
                'sede' => 'Sede',
                'fecha' => 'Fecha',
                'cursos' => 'Cursos activos',
                'estado' => 'Estado',
            ],
            'rows' => $rows,
            'summary' => ['Total matrículas' => count($rows), 'Activas' => $active],
        ];
    }

    /** @param array<string,mixed> $filters */
    private static function grupos(array $filters): array
    {
        $where = ['1=1'];
        $params = [];

        $periodId = (int) ($filters['periodo_id'] ?? 0);
        if ($periodId <= 0) {
            $period = DashboardService::currentPeriod();
            $periodId = $period === null ? 0 : (int) $period['id'];
        }

        if ($periodId > 0) {
            $where[] = 'g.periodo_id = ?';
            $params[] = $periodId;
        }

        if (!empty($filters['sede_id'])) {
            $where[] = 'g.sede_id = ?';
            $params[] = (int) $filters['sede_id'];
        }

        $scope = Auth::sedeScope();
        if ($scope !== null) {
            $where[] = 'g.sede_id = ?';
            $params[] = $scope;
        }

        $minimum = max(1, Param::int('cupo_minimo_grupo', 5));

        $rows = Database::fetchAll(
            "SELECT g.codigo, c.nombre AS curso,
                    CONCAT_WS(' ', pd.nombres, pd.apellidos) AS docente,
                    s.nombre AS sede, a.nombre AS aula, pe.nombre AS periodo,
                    g.capacidad_max, g.cupo_minimo,
                    (SELECT COUNT(*) FROM inscripciones i WHERE i.grupo_id = g.id AND i.estado = 'activa') AS inscritos
             FROM grupos g
             JOIN cursos c ON c.id = g.curso_id
             JOIN docentes d ON d.id = g.docente_id
             JOIN personas pd ON pd.id = d.persona_id
             JOIN sedes s ON s.id = g.sede_id
             JOIN aulas a ON a.id = g.aula_id
             JOIN periodos pe ON pe.id = g.periodo_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY s.nombre, c.nombre, g.codigo",
            $params
        );

        $capacity = 0;
        $enrolled = 0;
        $below = 0;

        foreach ($rows as &$row) {
            $row['disponibles'] = max(0, (int) $row['capacidad_max'] - (int) $row['inscritos']);
            $row['ocupacion'] = porcentaje((int) $row['inscritos'], (int) $row['capacidad_max']);
            $row['bajo_minimo'] = (int) $row['inscritos'] < (int) $row['cupo_minimo'] ? 'Sí' : 'No';
            $capacity += (int) $row['capacidad_max'];
            $enrolled += (int) $row['inscritos'];
            if ((int) $row['inscritos'] < $minimum) {
                $below++;
            }
        }
        unset($row);

        return [
            'columns' => [
                'codigo' => 'Grupo',
                'curso' => 'Curso',
                'docente' => 'Docente',
                'sede' => 'Sede',
                'aula' => 'Aula',
                'periodo' => 'Periodo',
                'capacidad_max' => 'Capacidad',
                'inscritos' => 'Inscritos',
                'disponibles' => 'Disponibles',
                'ocupacion' => 'Ocupación %',
                'bajo_minimo' => 'Bajo mínimo',
            ],
            'rows' => $rows,
            'summary' => [
                'Grupos' => count($rows),
                'Capacidad total' => $capacity,
                'Inscritos' => $enrolled,
                'Grupos bajo mínimo (' . $minimum . ')' => $below,
            ],
        ];
    }

    /** @param array<string,mixed> $filters */
    private static function notas(array $filters): array
    {
        $groupId = (int) ($filters['grupo_id'] ?? 0);

        if ($groupId <= 0) {
            return [
                'columns' => ['aviso' => 'Seleccione un grupo'],
                'rows' => [],
                'summary' => ['Aviso' => 'Debe seleccionar un grupo para generar el consolidado.'],
            ];
        }

        $matrix = GradeService::matrix($groupId);
        $columns = [
            'estudiante_codigo' => 'Código',
            'numero_documento' => 'Documento',
            'apellidos' => 'Apellidos',
            'nombres' => 'Nombres',
        ];

        foreach ($matrix['evaluations'] as $evaluation) {
            if ((string) $evaluation['estado'] !== 'activa') {
                continue;
            }

            $columns['eval_' . (int) $evaluation['id']] = $evaluation['nombre'] . ' (' . number_format((float) $evaluation['peso'], 1, ',', '.') . '%)';
        }

        $columns['nota_final'] = 'Nota final';
        $columns['peso_evaluado'] = 'Peso evaluado %';
        $columns['estado'] = 'Estado';

        $rows = [];
        $approved = 0;
        $failed = 0;
        $sum = 0.0;
        $count = 0;
        $minimum = Param::float('nota_minima', 3.0);

        foreach ($matrix['rows'] as $row) {
            $item = [
                'estudiante_codigo' => $row['estudiante_codigo'],
                'numero_documento' => $row['numero_documento'],
                'apellidos' => $row['apellidos'],
                'nombres' => $row['nombres'],
                'nota_final' => $row['final']['nota'],
                'peso_evaluado' => $row['final']['peso_evaluado'],
                'estado' => $row['inscripcion_estado'],
            ];

            foreach ($matrix['evaluations'] as $evaluation) {
                if ((string) $evaluation['estado'] !== 'activa') {
                    continue;
                }
                $item['eval_' . (int) $evaluation['id']] = $row['notas'][(int) $evaluation['id']] ?? null;
            }

            if ($row['final']['nota'] !== null) {
                $sum += (float) $row['final']['nota'];
                $count++;
                if ((float) $row['final']['nota'] >= $minimum) {
                    $approved++;
                } else {
                    $failed++;
                }
            }

            $rows[] = $item;
        }

        return [
            'columns' => $columns,
            'rows' => $rows,
            'summary' => [
                'Estudiantes' => count($rows),
                'Peso total configurado %' => $matrix['weight'],
                'Aprobados' => $approved,
                'Reprobados' => $failed,
                'Promedio' => $count > 0 ? round($sum / $count, 1) : null,
            ],
        ];
    }

    /** @param array<string,mixed> $filters */
    private static function asistencia(array $filters): array
    {
        $groupId = (int) ($filters['grupo_id'] ?? 0);

        if ($groupId <= 0) {
            return [
                'columns' => ['aviso' => 'Seleccione un grupo'],
                'rows' => [],
                'summary' => ['Aviso' => 'Debe seleccionar un grupo para generar el consolidado.'],
            ];
        }

        $summary = AttendanceService::summary($groupId);
        $rows = [];

        foreach ($summary['rows'] as $row) {
            $rows[] = [
                'estudiante_codigo' => $row['estudiante_codigo'],
                'numero_documento' => $row['numero_documento'],
                'apellidos' => $row['apellidos'],
                'nombres' => $row['nombres'],
                'sesiones' => $summary['sessions'],
                'presentes' => $row['presentes'],
                'ausentes' => $row['ausentes'],
                'excusas' => $row['excusas'],
                'tardes' => $row['tardes'],
                'porcentaje_inasistencia' => $row['porcentaje_inasistencia'],
                'estado_asistencia' => $row['estado_asistencia'],
                'inscripcion_estado' => $row['inscripcion_estado'],
            ];
        }

        return [
            'columns' => [
                'estudiante_codigo' => 'Código',
                'numero_documento' => 'Documento',
                'apellidos' => 'Apellidos',
                'nombres' => 'Nombres',
                'sesiones' => 'Sesiones',
                'presentes' => 'Presentes',
                'ausentes' => 'Ausentes',
                'excusas' => 'Excusas',
                'tardes' => 'Tardes',
                'porcentaje_inasistencia' => 'Inasistencia %',
                'estado_asistencia' => 'Estado asistencia',
                'inscripcion_estado' => 'Estado inscripción',
            ],
            'rows' => $rows,
            'summary' => [
                'Sesiones cerradas' => $summary['sessions'],
                'Umbral de alerta %' => $summary['alerta'],
                'Umbral de pérdida %' => $summary['perdida'],
            ],
        ];
    }

    /** @param array<string,mixed> $filters */
    private static function cartera(array $filters): array
    {
        $where = ["c.estado <> 'anulado'"];
        $params = [];

        if (!empty($filters['buscar'])) {
            $term = '%' . $filters['buscar'] . '%';
            $where[] = '(p.nombres LIKE ? OR p.apellidos LIKE ? OR p.numero_documento LIKE ? OR c.descripcion LIKE ?)';
            array_push($params, $term, $term, $term, $term);
        }

        if (!empty($filters['sede_id'])) {
            $where[] = 'e.sede_id = ?';
            $params[] = (int) $filters['sede_id'];
        }

        $scope = Auth::sedeScope();
        if ($scope !== null) {
            $where[] = 'e.sede_id = ?';
            $params[] = $scope;
        }

        $moraDays = max(0, Param::int('mora_dias', 30));
        $limitDate = date('Y-m-d', strtotime('-' . $moraDays . ' days'));

        $charges = Database::fetchAll(
            "SELECT c.descripcion, c.valor_total, c.fecha_emision, c.fecha_vencimiento, c.estado,
                    cc.nombre AS concepto, e.codigo AS estudiante_codigo, e.sede_id,
                    p.numero_documento, p.apellidos, p.nombres, s.nombre AS sede,
                    (SELECT COALESCE(SUM(pa.valor),0) FROM pago_aplicaciones pa WHERE pa.cargo_id = c.id AND pa.estado = 'activa') AS pagado
             FROM cargos c
             JOIN estudiantes e ON e.id = c.estudiante_id
             JOIN personas p ON p.id = e.persona_id
             JOIN sedes s ON s.id = e.sede_id
             JOIN conceptos_cargo cc ON cc.id = c.concepto_id
             WHERE " . implode(' AND ', $where) . '
             ORDER BY c.fecha_vencimiento, p.apellidos, p.nombres',
            $params
        );

        $rows = [];
        $total = 0.0;
        $paid = 0.0;
        $balance = 0.0;
        $overdue = 0.0;
        $requested = (string) ($filters['estado'] ?? '');

        foreach ($charges as $charge) {
            $chargeBalance = round((float) $charge['valor_total'] - (float) $charge['pagado'], 2);
            $isOverdue = $chargeBalance > 0.01 && (string) $charge['fecha_vencimiento'] < $limitDate;
            $visual = $chargeBalance <= 0.01 ? 'pagado' : ($isOverdue ? 'vencido' : 'pendiente');

            if ($requested !== '' && $requested !== $visual) {
                continue;
            }

            $row = [
                'estudiante_codigo' => $charge['estudiante_codigo'],
                'numero_documento' => $charge['numero_documento'],
                'apellidos' => $charge['apellidos'],
                'nombres' => $charge['nombres'],
                'sede' => $charge['sede'],
                'concepto' => $charge['concepto'],
                'descripcion' => $charge['descripcion'],
                'fecha_emision' => $charge['fecha_emision'],
                'fecha_vencimiento' => $charge['fecha_vencimiento'],
                'valor_total' => (float) $charge['valor_total'],
                'pagado' => (float) $charge['pagado'],
                'saldo' => $chargeBalance,
                'estado' => $visual,
            ];

            $rows[] = $row;
            $total += (float) $charge['valor_total'];
            $paid += (float) $charge['pagado'];
            $balance += $chargeBalance;
            if ($isOverdue) {
                $overdue += $chargeBalance;
            }
        }

        return [
            'columns' => [
                'estudiante_codigo' => 'Código',
                'numero_documento' => 'Documento',
                'apellidos' => 'Apellidos',
                'nombres' => 'Nombres',
                'sede' => 'Sede',
                'concepto' => 'Concepto',
                'descripcion' => 'Descripción',
                'fecha_emision' => 'Emisión',
                'fecha_vencimiento' => 'Vencimiento',
                'valor_total' => 'Valor',
                'pagado' => 'Pagado',
                'saldo' => 'Saldo',
                'estado' => 'Estado',
            ],
            'rows' => $rows,
            'summary' => [
                'Cargos' => count($rows),
                'Valor total' => round($total, 2),
                'Pagado' => round($paid, 2),
                'Saldo' => round($balance, 2),
                'Vencido' => round($overdue, 2),
            ],
        ];
    }

    /** @param array<string,mixed> $filters */
    private static function pagos(array $filters): array
    {
        $where = ['1=1'];
        $params = [];

        $from = (string) ($filters['desde'] ?? '');
        $to = (string) ($filters['hasta'] ?? '');

        if ($from === '') {
            $from = date('Y-m-01');
        }

        if ($to === '') {
            $to = date('Y-m-t');
        }

        $where[] = 'pg.fecha BETWEEN ? AND ?';
        array_push($params, $from, $to);

        if (!empty($filters['sede_id'])) {
            $where[] = 'e.sede_id = ?';
            $params[] = (int) $filters['sede_id'];
        }

        if (!empty($filters['medio'])) {
            $where[] = 'pg.medio = ?';
            $params[] = $filters['medio'];
        }

        if (!empty($filters['estado'])) {
            $where[] = 'pg.estado = ?';
            $params[] = $filters['estado'];
        }

        $scope = Auth::sedeScope();
        if ($scope !== null) {
            $where[] = 'e.sede_id = ?';
            $params[] = $scope;
        }

        $rows = Database::fetchAll(
            "SELECT cp.numero AS comprobante, pg.fecha, p.numero_documento, p.apellidos, p.nombres,
                    s.nombre AS sede, pg.valor, pg.medio, pg.referencia, pg.estado,
                    e.codigo AS estudiante_codigo
             FROM pagos pg
             JOIN estudiantes e ON e.id = pg.estudiante_id
             JOIN personas p ON p.id = e.persona_id
             JOIN sedes s ON s.id = e.sede_id
             LEFT JOIN comprobantes cp ON cp.pago_id = pg.id
             WHERE " . implode(' AND ', $where) . '
             ORDER BY pg.fecha DESC, pg.id DESC',
            $params
        );

        $total = 0.0;
        $annulled = 0.0;

        foreach ($rows as $row) {
            if ($row['estado'] === 'anulado') {
                $annulled += (float) $row['valor'];
            } else {
                $total += (float) $row['valor'];
            }
        }

        return [
            'columns' => [
                'comprobante' => 'Comprobante',
                'fecha' => 'Fecha',
                'numero_documento' => 'Documento',
                'apellidos' => 'Apellidos',
                'nombres' => 'Nombres',
                'sede' => 'Sede',
                'valor' => 'Valor',
                'medio' => 'Medio',
                'referencia' => 'Referencia',
                'estado' => 'Estado',
            ],
            'rows' => $rows,
            'summary' => [
                'Pagos' => count($rows),
                'Total vigente' => round($total, 2),
                'Total anulado' => round($annulled, 2),
                'Rango' => $from . ' a ' . $to,
            ],
        ];
    }

    /** @param array<string,mixed> $filters */
    private static function docentes(array $filters): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['sede_id'])) {
            $where[] = 'd.sede_id = ?';
            $params[] = (int) $filters['sede_id'];
        }

        if (!empty($filters['estado'])) {
            $where[] = 'd.estado = ?';
            $params[] = $filters['estado'];
        }

        $scope = Auth::sedeScope();
        if ($scope !== null) {
            $where[] = 'd.sede_id = ?';
            $params[] = $scope;
        }

        $rows = Database::fetchAll(
            "SELECT d.codigo, p.numero_documento, p.apellidos, p.nombres, s.nombre AS sede,
                    d.titulo, d.tipo_vinculacion, d.estado,
                    (SELECT COUNT(*) FROM grupos g WHERE g.docente_id = d.id AND g.estado = 'activo') AS grupos,
                    (SELECT COUNT(*) FROM inscripciones i
                     JOIN grupos g ON g.id = i.grupo_id
                     WHERE g.docente_id = d.id AND g.estado = 'activo' AND i.estado = 'activa') AS estudiantes
             FROM docentes d
             JOIN personas p ON p.id = d.persona_id
             JOIN sedes s ON s.id = d.sede_id
             WHERE " . implode(' AND ', $where) . '
             ORDER BY s.nombre, p.apellidos, p.nombres',
            $params
        );

        return [
            'columns' => [
                'codigo' => 'Código',
                'numero_documento' => 'Documento',
                'apellidos' => 'Apellidos',
                'nombres' => 'Nombres',
                'sede' => 'Sede',
                'titulo' => 'Título',
                'tipo_vinculacion' => 'Vinculación',
                'grupos' => 'Grupos activos',
                'estudiantes' => 'Estudiantes',
                'estado' => 'Estado',
            ],
            'rows' => $rows,
            'summary' => ['Docentes' => count($rows)],
        ];
    }

    /** @param array<string,mixed> $filters */
    private static function riesgo(array $filters): array
    {
        $where = ["a.estado = 'nueva'"];
        $params = [];

        if (!empty($filters['tipo'])) {
            $where[] = 'a.tipo = ?';
            $params[] = $filters['tipo'];
        }

        if (!empty($filters['nivel'])) {
            $where[] = 'a.nivel = ?';
            $params[] = $filters['nivel'];
        }

        if (!empty($filters['sede_id'])) {
            $where[] = 'e.sede_id = ?';
            $params[] = (int) $filters['sede_id'];
        }

        $scope = Auth::sedeScope();
        if ($scope !== null) {
            $where[] = 'e.sede_id = ?';
            $params[] = $scope;
        }

        $rows = Database::fetchAll(
            "SELECT a.created_at AS fecha, p.numero_documento, p.apellidos, p.nombres,
                    e.codigo AS estudiante_codigo, s.nombre AS sede,
                    a.tipo, a.nivel, a.mensaje, a.estado
             FROM alertas a
             JOIN estudiantes e ON e.id = a.estudiante_id
             JOIN personas p ON p.id = e.persona_id
             JOIN sedes s ON s.id = e.sede_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY CASE a.nivel WHEN 'critica' THEN 0 WHEN 'advertencia' THEN 1 ELSE 2 END,
                      a.created_at DESC",
            $params
        );

        $critical = count(array_filter($rows, static fn (array $row): bool => $row['nivel'] === 'critica'));

        return [
            'columns' => [
                'fecha' => 'Fecha',
                'numero_documento' => 'Documento',
                'apellidos' => 'Apellidos',
                'nombres' => 'Nombres',
                'estudiante_codigo' => 'Código',
                'sede' => 'Sede',
                'tipo' => 'Tipo',
                'nivel' => 'Nivel',
                'mensaje' => 'Mensaje',
                'estado' => 'Estado',
            ],
            'rows' => $rows,
            'summary' => [
                'Alertas abiertas' => count($rows),
                'Críticas' => $critical,
            ],
        ];
    }
}
