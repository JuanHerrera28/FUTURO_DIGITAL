<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\DomainException;
use App\Core\Param;

final class AttendanceService
{
    /** @return list<array<string,mixed>> */
    public static function sessions(int $groupId): array
    {
        return Database::fetchAll(
            "SELECT s.*,
                    (SELECT COUNT(*) FROM asistencias a WHERE a.sesion_id = s.id) AS registros,
                    (SELECT COUNT(*) FROM asistencias a WHERE a.sesion_id = s.id AND a.estado IN ('ausente')) AS ausentes
             FROM sesiones s
             WHERE s.grupo_id = ?
             ORDER BY s.fecha DESC, s.hora_inicio DESC",
            [$groupId]
        );
    }

    /** @return array<string,mixed>|null */
    public static function findSession(int $sessionId): ?array
    {
        return Database::fetch(
            "SELECT s.*, g.codigo AS grupo, g.periodo_id, g.docente_id, g.sede_id,
                    c.nombre AS curso, c.codigo AS curso_codigo,
                    p.nombre AS periodo, p.estado AS periodo_estado
             FROM sesiones s
             JOIN grupos g ON g.id = s.grupo_id
             JOIN cursos c ON c.id = g.curso_id
             JOIN periodos p ON p.id = g.periodo_id
             WHERE s.id = ?",
            [$sessionId]
        );
    }

    public static function createSession(
        int $groupId,
        string $fecha,
        string $horaInicio,
        string $horaFin,
        ?string $tema = null
    ): int {
        $group = IdentityService::ownedGroup($groupId);
        self::assertPeriodOpen((int) $group['periodo_id']);

        $period = Database::fetch('SELECT fecha_inicio, fecha_fin FROM periodos WHERE id = ?', [(int) $group['periodo_id']]);
        if ($period === null || $fecha < (string) $period['fecha_inicio'] || $fecha > (string) $period['fecha_fin']) {
            throw new DomainException('RF-14', 'La fecha de la sesión debe estar dentro del periodo académico.');
        }

        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $horaInicio) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $horaFin)) {
            throw new DomainException('RF-14', 'Las horas deben tener el formato HH:MM.');
        }

        $horaInicio = strlen($horaInicio) === 5 ? $horaInicio . ':00' : $horaInicio;
        $horaFin = strlen($horaFin) === 5 ? $horaFin . ':00' : $horaFin;

        if ($horaFin <= $horaInicio) {
            throw new DomainException('RF-14', 'La hora de fin debe ser posterior a la hora de inicio.');
        }

        if ((int) Database::scalar(
            'SELECT COUNT(*) FROM sesiones WHERE grupo_id = ? AND fecha = ? AND hora_inicio = ?',
            [$groupId, $fecha, $horaInicio]
        ) > 0) {
            throw new DomainException('RF-14', 'Ya existe una sesión para ese grupo, fecha y hora.');
        }

        $id = Database::insert('sesiones', [
            'grupo_id' => $groupId,
            'fecha' => $fecha,
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
            'tema' => $tema !== null && trim($tema) !== '' ? substr(trim($tema), 0, 180) : null,
            'estado' => 'abierta',
            'created_by' => Auth::id(),
        ]);

        Audit::log('sesion_creada', 'asistencia', 'sesiones', $id, null, [
            'grupo_id' => $groupId,
            'fecha' => $fecha,
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
        ]);

        return $id;
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function sheet(int $sessionId): array
    {
        $session = self::findSession($sessionId);

        if ($session === null) {
            throw new DomainException('RF-14', 'La sesión no existe.');
        }

        IdentityService::assertOwnsGroup((int) $session['grupo_id']);

        return Database::fetchAll(
            "SELECT i.id AS inscripcion_id, e.id AS estudiante_id, e.codigo AS estudiante_codigo,
                    p.nombres, p.apellidos, p.numero_documento,
                    a.id AS asistencia_id, a.estado, a.observacion
             FROM inscripciones i
             JOIN matriculas m ON m.id = i.matricula_id
             JOIN estudiantes e ON e.id = m.estudiante_id
             JOIN personas p ON p.id = e.persona_id
             LEFT JOIN asistencias a ON a.sesion_id = ? AND a.inscripcion_id = i.id
             WHERE i.grupo_id = ? AND i.estado = 'activa'
             ORDER BY p.apellidos, p.nombres",
            [$sessionId, (int) $session['grupo_id']]
        );
    }

    /**
     * Guarda la asistencia de una sesión.
     *
     * @param array<int,string> $estados inscripcion_id => estado
     * @param array<int,string> $observaciones inscripcion_id => observación
     */
    public static function save(int $sessionId, array $estados, array $observaciones = []): void
    {
        $session = self::findSession($sessionId);

        if ($session === null) {
            throw new DomainException('RF-14', 'La sesión no existe.');
        }

        IdentityService::assertOwnsGroup((int) $session['grupo_id']);
        self::assertPeriodOpen((int) $session['periodo_id']);

        $valid = ['presente', 'ausente', 'excusa', 'tarde'];

        Database::transaction(static function () use ($sessionId, $session, $estados, $observaciones, $valid): void {
            $inscriptions = Database::fetchAll(
                "SELECT id FROM inscripciones WHERE grupo_id = ? AND estado = 'activa'",
                [(int) $session['grupo_id']]
            );

            $validIds = array_map(static fn (array $row): int => (int) $row['id'], $inscriptions);

            foreach ($validIds as $inscripcionId) {
                $estado = (string) ($estados[$inscripcionId] ?? 'presente');

                if (!in_array($estado, $valid, true)) {
                    $estado = 'presente';
                }

                $observacion = isset($observaciones[$inscripcionId]) && trim((string) $observaciones[$inscripcionId]) !== ''
                    ? substr(trim((string) $observaciones[$inscripcionId]), 0, 255)
                    : null;

                $existing = Database::fetch(
                    'SELECT id FROM asistencias WHERE sesion_id = ? AND inscripcion_id = ?',
                    [$sessionId, $inscripcionId]
                );

                if ($existing !== null) {
                    Database::update('asistencias', [
                        'estado' => $estado,
                        'observacion' => $observacion,
                        'registrado_por' => Auth::id(),
                    ], ['id' => (int) $existing['id']]);

                    $asistenciaId = (int) $existing['id'];
                } else {
                    $asistenciaId = Database::insert('asistencias', [
                        'sesion_id' => $sessionId,
                        'inscripcion_id' => $inscripcionId,
                        'estado' => $estado,
                        'observacion' => $observacion,
                        'registrado_por' => Auth::id(),
                    ]);
                }

                if ($estado === 'excusa') {
                    $excusa = Database::fetch('SELECT id FROM excusas WHERE asistencia_id = ?', [$asistenciaId]);

                    if ($excusa === null) {
                        Database::insert('excusas', [
                            'asistencia_id' => $asistenciaId,
                            'motivo' => $observacion ?? 'Excusa registrada por el docente',
                            'estado' => 'aprobada',
                            'aprobado_por' => Auth::id(),
                        ]);
                    } else {
                        Database::update('excusas', [
                            'motivo' => $observacion ?? 'Excusa registrada por el docente',
                            'estado' => 'aprobada',
                            'aprobado_por' => Auth::id(),
                        ], ['id' => (int) $excusa['id']]);
                    }
                } else {
                    Database::execute('DELETE FROM excusas WHERE asistencia_id = ?', [$asistenciaId]);
                }
            }

            Database::update('sesiones', ['estado' => 'cerrada'], ['id' => $sessionId]);

            Audit::log('asistencia_registrada', 'asistencia', 'sesiones', $sessionId, null, [
                'grupo_id' => (int) $session['grupo_id'],
                'registros' => count($validIds),
            ]);
        });

        self::evaluateGroup((int) $session['grupo_id']);
    }

    /**
     * Resumen de asistencia por estudiante del grupo.
     *
     * @return array{rows:list<array<string,mixed>>,sessions:int,alerta:float,perdida:float}
     */
    public static function summary(int $groupId): array
    {
        $sessions = (int) Database::scalar(
            "SELECT COUNT(*) FROM sesiones WHERE grupo_id = ? AND estado = 'cerrada'",
            [$groupId]
        );

        $alertThreshold = Param::float('alerta_inasistencia', 20.0);
        $lossThreshold = Param::float('perdida_inasistencia', 25.0);

        $rows = Database::fetchAll(
            "SELECT i.id AS inscripcion_id, i.estado AS inscripcion_estado, i.nota_final,
                    e.codigo AS estudiante_codigo, p.nombres, p.apellidos, p.numero_documento,
                    COALESCE(SUM(CASE WHEN a.estado = 'ausente' THEN 1 ELSE 0 END), 0) AS ausentes,
                    COALESCE(SUM(CASE WHEN a.estado = 'excusa' THEN 1 ELSE 0 END), 0) AS excusas,
                    COALESCE(SUM(CASE WHEN a.estado = 'tarde' THEN 1 ELSE 0 END), 0) AS tardes,
                    COALESCE(SUM(CASE WHEN a.estado = 'presente' THEN 1 ELSE 0 END), 0) AS presentes,
                    COUNT(a.id) AS registros
             FROM inscripciones i
             JOIN matriculas m ON m.id = i.matricula_id
             JOIN estudiantes e ON e.id = m.estudiante_id
             JOIN personas p ON p.id = e.persona_id
             LEFT JOIN sesiones s ON s.grupo_id = i.grupo_id AND s.estado = 'cerrada'
             LEFT JOIN asistencias a ON a.sesion_id = s.id AND a.inscripcion_id = i.id
             WHERE i.grupo_id = ?
             GROUP BY i.id, i.estado, i.nota_final, e.codigo, p.nombres, p.apellidos, p.numero_documento
             ORDER BY p.apellidos, p.nombres",
            [$groupId]
        );

        foreach ($rows as &$row) {
            $ausentes = (int) $row['ausentes'];
            $excusas = (int) $row['excusas'];
            $base = max(0, $sessions - $excusas);
            $percentage = $base > 0 ? round(($ausentes / $base) * 100, 1) : 0.0;

            $row['porcentaje_inasistencia'] = $percentage;
            $row['estado_asistencia'] = $percentage > $lossThreshold
                ? 'perdida'
                : ($percentage >= $alertThreshold ? 'alerta' : 'normal');
        }
        unset($row);

        return [
            'rows' => $rows,
            'sessions' => $sessions,
            'alerta' => $alertThreshold,
            'perdida' => $lossThreshold,
        ];
    }

    /**
     * Aplica las consecuencias de inasistencia: alerta y pérdida de la
     * inscripción cuando se supera el umbral configurado.
     */
    public static function evaluateGroup(int $groupId): void
    {
        $summary = self::summary($groupId);
        $lossThreshold = $summary['perdida'];

        foreach ($summary['rows'] as $row) {
            $inscripcionId = (int) $row['inscripcion_id'];
            $percentage = (float) $row['porcentaje_inasistencia'];

            if ($percentage <= 0) {
                continue;
            }

            $studentId = (int) Database::scalar(
                'SELECT m.estudiante_id FROM inscripciones i JOIN matriculas m ON m.id = i.matricula_id WHERE i.id = ?',
                [$inscripcionId]
            );

            if ($percentage > $lossThreshold) {
                if ((string) $row['inscripcion_estado'] === 'activa') {
                    Database::update('inscripciones', [
                        'estado' => 'perdida_inasistencia',
                        'nota_final' => 0.0,
                        'updated_by' => Auth::id(),
                    ], ['id' => $inscripcionId]);

                    Database::insert('inscripcion_movimientos', [
                        'inscripcion_id' => $inscripcionId,
                        'tipo' => 'finalizacion',
                        'grupo_origen_id' => $groupId,
                        'motivo' => 'Pérdida por inasistencia (' . number_format($percentage, 1, ',', '.') . ' %)',
                        'usuario_id' => Auth::id(),
                    ]);

                    Audit::log('perdida_inasistencia', 'asistencia', 'inscripciones', $inscripcionId, [
                        'estado' => 'activa',
                    ], [
                        'estado' => 'perdida_inasistencia',
                        'porcentaje' => $percentage,
                    ]);
                }

                AlertService::create(
                    $studentId,
                    $inscripcionId,
                    'inasistencia',
                    'critica',
                    'Pérdida por inasistencia: ' . number_format($percentage, 1, ',', '.')
                    . ' % de ausencias en ' . $row['apellidos'] . ', ' . $row['nombres'] . '.'
                );

                continue;
            }

            if ($percentage >= $summary['alerta']) {
                AlertService::create(
                    $studentId,
                    $inscripcionId,
                    'inasistencia',
                    $percentage >= ($lossThreshold - 2) ? 'critica' : 'advertencia',
                    'Inasistencia del ' . number_format($percentage, 1, ',', '.') . ' % en el grupo, cerca del límite de pérdida.'
                );
            }
        }
    }

    /**
     * Asistencia del estudiante por grupo/periodo.
     *
     * @return list<array<string,mixed>>
     */
    public static function studentSummary(int $estudianteId): array
    {
        $alertThreshold = Param::float('alerta_inasistencia', 20.0);
        $lossThreshold = Param::float('perdida_inasistencia', 25.0);

        $rows = Database::fetchAll(
            "SELECT i.id AS inscripcion_id, i.estado AS inscripcion_estado, i.nota_final,
                    g.id AS grupo_id, g.codigo AS grupo, c.nombre AS curso,
                    pe.nombre AS periodo, pe.estado AS periodo_estado,
                    (SELECT COUNT(*) FROM sesiones s WHERE s.grupo_id = g.id AND s.estado = 'cerrada') AS sesiones,
                    COALESCE(SUM(CASE WHEN a.estado = 'ausente' THEN 1 ELSE 0 END), 0) AS ausentes,
                    COALESCE(SUM(CASE WHEN a.estado = 'excusa' THEN 1 ELSE 0 END), 0) AS excusas,
                    COALESCE(SUM(CASE WHEN a.estado = 'tarde' THEN 1 ELSE 0 END), 0) AS tardes
             FROM inscripciones i
             JOIN grupos g ON g.id = i.grupo_id
             JOIN cursos c ON c.id = g.curso_id
             JOIN periodos pe ON pe.id = g.periodo_id
             JOIN matriculas m ON m.id = i.matricula_id
             LEFT JOIN asistencias a ON a.inscripcion_id = i.id
             WHERE m.estudiante_id = ?
             GROUP BY i.id, i.estado, i.nota_final, g.id, g.codigo, c.nombre, pe.nombre, pe.estado
             ORDER BY pe.fecha_inicio DESC, c.nombre",
            [$estudianteId]
        );

        foreach ($rows as &$row) {
            $base = max(0, (int) $row['sesiones'] - (int) $row['excusas']);
            $percentage = $base > 0 ? round(((int) $row['ausentes'] / $base) * 100, 1) : 0.0;
            $row['porcentaje_inasistencia'] = $percentage;
            $row['estado_asistencia'] = $percentage > $lossThreshold
                ? 'perdida'
                : ($percentage >= $alertThreshold ? 'alerta' : 'normal');
        }
        unset($row);

        return $rows;
    }

    private static function assertPeriodOpen(int $periodoId): void
    {
        $state = (string) Database::scalar('SELECT estado FROM periodos WHERE id = ?', [$periodoId]);

        if ($state === 'cerrado') {
            throw new DomainException('RF-22', 'El periodo está cerrado y no admite cambios de asistencia.');
        }
    }
}
