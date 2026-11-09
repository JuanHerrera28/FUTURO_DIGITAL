<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\DomainException;
use App\Core\Param;

final class GradeService
{
    /** @return list<array<string,mixed>> */
    public static function evaluations(int $groupId): array
    {
        return Database::fetchAll(
            "SELECT e.*,
                    (SELECT COUNT(*) FROM notas n WHERE n.evaluacion_id = e.id) AS calificadas
             FROM evaluaciones e
             WHERE e.grupo_id = ?
             ORDER BY e.fecha, e.id",
            [$groupId]
        );
    }

    public static function addEvaluation(int $groupId, string $nombre, float $peso, ?string $fecha): int
    {
        $group = IdentityService::ownedGroup($groupId);
        self::assertPeriodOpen((int) $group['periodo_id']);

        $nombre = trim($nombre);
        if ($nombre === '') {
            throw new DomainException('RF-13', 'El nombre de la evaluación es obligatorio.');
        }

        if ($peso <= 0 || $peso > 100) {
            throw new DomainException('RN-13', 'El peso de la evaluación debe estar entre 0,1 % y 100 %.');
        }

        if (Database::scalar('SELECT COUNT(*) FROM evaluaciones WHERE grupo_id = ? AND nombre = ?', [$groupId, $nombre]) > 0) {
            throw new DomainException('RF-13', 'Ya existe una evaluación con ese nombre en el grupo.');
        }

        $current = (float) Database::scalar(
            "SELECT COALESCE(SUM(peso),0) FROM evaluaciones WHERE grupo_id = ? AND estado = 'activa'",
            [$groupId]
        );

        if ($current + $peso > 100.01) {
            throw new DomainException('RN-13', 'La suma de pesos no puede superar 100 %. Actualmente suma ' . number_format($current, 1, ',', '.') . ' %.');
        }

        $id = Database::insert('evaluaciones', [
            'grupo_id' => $groupId,
            'nombre' => substr($nombre, 0, 120),
            'peso' => round($peso, 2),
            'fecha' => $fecha !== null && $fecha !== '' ? $fecha : null,
            'estado' => 'activa',
            'created_by' => Auth::id(),
        ]);

        Audit::log('evaluacion_creada', 'calificaciones', 'evaluaciones', $id, null, [
            'grupo_id' => $groupId, 'nombre' => $nombre, 'peso' => round($peso, 2),
        ]);

        return $id;
    }

    public static function updateEvaluation(int $id, string $nombre, float $peso, ?string $fecha, string $estado = 'activa'): void
    {
        $before = Database::fetch('SELECT * FROM evaluaciones WHERE id = ?', [$id]);
        if ($before === null) {
            throw new DomainException('RF-13', 'La evaluación no existe.');
        }

        $group = IdentityService::ownedGroup((int) $before['grupo_id']);
        self::assertPeriodOpen((int) $group['periodo_id']);

        if ($peso <= 0 || $peso > 100) {
            throw new DomainException('RN-13', 'El peso de la evaluación debe estar entre 0,1 % y 100 %.');
        }

        $current = (float) Database::scalar(
            "SELECT COALESCE(SUM(peso),0) FROM evaluaciones WHERE grupo_id = ? AND estado = 'activa' AND id <> ?",
            [(int) $before['grupo_id'], $id]
        );

        if ($current + $peso > 100.01) {
            throw new DomainException('RN-13', 'La suma de pesos no puede superar 100 %.');
        }

        Database::update('evaluaciones', [
            'nombre' => substr(trim($nombre), 0, 120),
            'peso' => round($peso, 2),
            'fecha' => $fecha !== null && $fecha !== '' ? $fecha : null,
            'estado' => in_array($estado, ['activa', 'inactiva'], true) ? $estado : 'activa',
        ], ['id' => $id]);

        Audit::log('evaluacion_actualizada', 'calificaciones', 'evaluaciones', $id, $before, [
            'nombre' => $nombre, 'peso' => round($peso, 2), 'fecha' => $fecha, 'estado' => $estado,
        ]);
    }

    public static function deactivateEvaluation(int $id): void
    {
        $before = Database::fetch('SELECT * FROM evaluaciones WHERE id = ?', [$id]);
        if ($before === null) {
            throw new DomainException('RF-13', 'La evaluación no existe.');
        }

        $group = IdentityService::ownedGroup((int) $before['grupo_id']);
        self::assertPeriodOpen((int) $group['periodo_id']);

        Database::update('evaluaciones', ['estado' => 'inactiva'], ['id' => $id]);
        Audit::log('evaluacion_inactivada', 'calificaciones', 'evaluaciones', $id, ['estado' => $before['estado']], ['estado' => 'inactiva']);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public static function gradeSheet(int $groupId, int $evaluationId): array
    {
        IdentityService::assertOwnsGroup($groupId);

        return Database::fetchAll(
            "SELECT i.id AS inscripcion_id, i.nota_final, i.estado AS inscripcion_estado,
                    e.id AS estudiante_id, e.codigo AS estudiante_codigo,
                    p.nombres, p.apellidos, p.numero_documento,
                    n.id AS nota_id, n.nota, n.observacion
             FROM inscripciones i
             JOIN matriculas m ON m.id = i.matricula_id
             JOIN estudiantes e ON e.id = m.estudiante_id
             JOIN personas p ON p.id = e.persona_id
             LEFT JOIN notas n ON n.inscripcion_id = i.id AND n.evaluacion_id = ?
             WHERE i.grupo_id = ? AND i.estado = 'activa'
             ORDER BY p.apellidos, p.nombres",
            [$evaluationId, $groupId]
        );
    }

    /**
     * Guarda las notas de una evaluación. Exige que los pesos del grupo sumen
     * 100 % antes de calificar (RN-13).
     *
     * @param array<int,float|string> $notas inscripcion_id => nota
     * @param array<int,string> $observaciones
     */
    public static function saveGrades(int $groupId, int $evaluationId, array $notas, array $observaciones = []): void
    {
        $group = IdentityService::ownedGroup($groupId);
        self::assertPeriodOpen((int) $group['periodo_id']);

        $evaluation = Database::fetch(
            "SELECT * FROM evaluaciones WHERE id = ? AND grupo_id = ? AND estado = 'activa'",
            [$evaluationId, $groupId]
        );

        if ($evaluation === null) {
            throw new DomainException('RF-13', 'La evaluación no existe o está inactiva.');
        }

        $weight = (float) Database::scalar(
            "SELECT COALESCE(SUM(peso),0) FROM evaluaciones WHERE grupo_id = ? AND estado = 'activa'",
            [$groupId]
        );

        if (abs($weight - 100.0) > 0.01) {
            throw new DomainException('RN-13', 'Antes de calificar, los pesos de las evaluaciones del grupo deben sumar 100 %. Actualmente suman ' . number_format($weight, 1, ',', '.') . ' %.');
        }

        $minimum = Param::float('nota_minima_escala', 0.0);
        $maximum = Param::float('nota_maxima', 5.0);

        Database::transaction(static function () use ($groupId, $evaluationId, $notas, $observaciones, $minimum, $maximum): void {
            $validInscriptions = Database::fetchAll(
                "SELECT id FROM inscripciones WHERE grupo_id = ? AND estado = 'activa'",
                [$groupId]
            );
            $validIds = array_map(static fn (array $row): int => (int) $row['id'], $validInscriptions);

            foreach ($notas as $inscripcionId => $nota) {
                $inscripcionId = (int) $inscripcionId;

                if (!in_array($inscripcionId, $validIds, true)) {
                    continue;
                }

                if ($nota === '' || $nota === null) {
                    Database::execute(
                        'DELETE FROM notas WHERE evaluacion_id = ? AND inscripcion_id = ?',
                        [$evaluationId, $inscripcionId]
                    );
                    continue;
                }

                $value = round((float) str_replace(',', '.', (string) $nota), 1);

                if ($value < $minimum || $value > $maximum) {
                    throw new DomainException('RN-13', 'Las notas deben estar entre ' . number_format($minimum, 1, ',', '.') . ' y ' . number_format($maximum, 1, ',', '.') . '.');
                }

                $observation = isset($observaciones[$inscripcionId]) ? substr(trim((string) $observaciones[$inscripcionId]), 0, 255) : null;

                $existing = Database::fetch(
                    'SELECT id FROM notas WHERE evaluacion_id = ? AND inscripcion_id = ?',
                    [$evaluationId, $inscripcionId]
                );

                if ($existing !== null) {
                    Database::update('notas', [
                        'nota' => $value,
                        'observacion' => $observation !== '' ? $observation : null,
                        'registrado_por' => Auth::id(),
                    ], ['id' => (int) $existing['id']]);
                } else {
                    Database::insert('notas', [
                        'evaluacion_id' => $evaluationId,
                        'inscripcion_id' => $inscripcionId,
                        'nota' => $value,
                        'observacion' => $observation !== '' ? $observation : null,
                        'registrado_por' => Auth::id(),
                    ]);
                }
            }

            foreach ($validIds as $inscripcionId) {
                self::recalculateFinal($inscripcionId);
            }

            Audit::log('notas_registradas', 'calificaciones', 'evaluaciones', $evaluationId, null, [
                'grupo_id' => $groupId,
                'registros' => count($notas),
            ]);
        });
    }

    /**
     * @return array{nota:?float,peso_evaluado:float,peso_total:float,completo:bool}
     */
    public static function finalForInscription(int $inscripcionId): array
    {
        $row = Database::fetch(
            "SELECT i.id, i.grupo_id
             FROM inscripciones i
             WHERE i.id = ?",
            [$inscripcionId]
        );

        if ($row === null) {
            return ['nota' => null, 'peso_evaluado' => 0.0, 'peso_total' => 0.0, 'completo' => false];
        }

        $evaluations = Database::fetchAll(
            "SELECT e.id, e.peso, n.nota
             FROM evaluaciones e
             LEFT JOIN notas n ON n.evaluacion_id = e.id AND n.inscripcion_id = ?
             WHERE e.grupo_id = ? AND e.estado = 'activa'
             ORDER BY e.id",
            [$inscripcionId, (int) $row['grupo_id']]
        );

        $weighted = 0.0;
        $graded = 0.0;
        $total = 0.0;

        foreach ($evaluations as $evaluation) {
            $weight = (float) $evaluation['peso'];
            $total += $weight;

            if ($evaluation['nota'] !== null) {
                $weighted += (float) $evaluation['nota'] * $weight;
                $graded += $weight;
            }
        }

        $note = $graded > 0.001 ? round($weighted / $graded, 1) : null;

        return [
            'nota' => $note,
            'peso_evaluado' => round($graded, 2),
            'peso_total' => round($total, 2),
            'completo' => abs($graded - 100.0) <= 0.01,
        ];
    }

    public static function recalculateFinal(int $inscripcionId): void
    {
        $final = self::finalForInscription($inscripcionId);
        $inscription = Database::fetch(
            "SELECT i.*, g.periodo_id FROM inscripciones i JOIN grupos g ON g.id = i.grupo_id WHERE i.id = ?",
            [$inscripcionId]
        );

        if ($inscription === null) {
            return;
        }

        if ((string) $inscription['estado'] === 'perdida_inasistencia') {
            Database::update('inscripciones', ['nota_final' => 0.0], ['id' => $inscripcionId]);

            return;
        }

        Database::update('inscripciones', ['nota_final' => $final['nota']], ['id' => $inscripcionId]);

        if ($final['nota'] === null) {
            return;
        }

        $threshold = Param::float('bajo_rendimiento_peso', 50.0);
        $minimum = Param::float('nota_minima', 3.0);

        if ($final['peso_evaluado'] + 0.01 >= $threshold && $final['nota'] < $minimum) {
            $studentId = (int) Database::scalar(
                'SELECT m.estudiante_id FROM inscripciones i JOIN matriculas m ON m.id = i.matricula_id WHERE i.id = ?',
                [$inscripcionId]
            );

            AlertService::create(
                $studentId,
                $inscripcionId,
                'bajo_rendimiento',
                'advertencia',
                'Bajo rendimiento: promedio parcial ' . number_format($final['nota'], 1, ',', '.') . ' por debajo de ' . number_format($minimum, 1, ',', '.') . '.'
            );
        }
    }

    /**
     * Planilla completa del grupo: estudiantes y notas por evaluación.
     *
     * @return array{evaluations:list<array<string,mixed>>,rows:list<array<string,mixed>>,weight:float}
     */
    public static function matrix(int $groupId): array
    {
        $evaluations = self::evaluations($groupId);
        $weight = 0.0;
        foreach ($evaluations as $evaluation) {
            if ((string) $evaluation['estado'] === 'activa') {
                $weight += (float) $evaluation['peso'];
            }
        }

        $rows = Database::fetchAll(
            "SELECT i.id AS inscripcion_id, i.nota_final, i.estado AS inscripcion_estado,
                    e.codigo AS estudiante_codigo, p.nombres, p.apellidos, p.numero_documento
             FROM inscripciones i
             JOIN matriculas m ON m.id = i.matricula_id
             JOIN estudiantes e ON e.id = m.estudiante_id
             JOIN personas p ON p.id = e.persona_id
             WHERE i.grupo_id = ? AND i.estado = 'activa'
             ORDER BY p.apellidos, p.nombres",
            [$groupId]
        );

        $notes = Database::fetchAll(
            "SELECT n.inscripcion_id, n.evaluacion_id, n.nota
             FROM notas n
             JOIN evaluaciones ev ON ev.id = n.evaluacion_id
             WHERE ev.grupo_id = ?",
            [$groupId]
        );

        $indexed = [];
        foreach ($notes as $note) {
            $indexed[(int) $note['inscripcion_id']][(int) $note['evaluacion_id']] = (float) $note['nota'];
        }

        foreach ($rows as &$row) {
            $row['notas'] = $indexed[(int) $row['inscripcion_id']] ?? [];
            $row['final'] = self::finalForInscription((int) $row['inscripcion_id']);
        }
        unset($row);

        return ['evaluations' => $evaluations, 'rows' => $rows, 'weight' => round($weight, 2)];
    }

    /** @return list<array<string,mixed>> */
    public static function studentGrades(int $estudianteId): array
    {
        return Database::fetchAll(
            "SELECT i.id AS inscripcion_id, i.nota_final, i.estado,
                    c.nombre AS curso, c.codigo AS curso_codigo,
                    g.codigo AS grupo, pe.nombre AS periodo, pe.estado AS periodo_estado,
                    (SELECT GROUP_CONCAT(CONCAT(ev.nombre, ': ', n.nota, ' (', ev.peso, '%)') ORDER BY ev.id SEPARATOR ' · ')
                     FROM notas n JOIN evaluaciones ev ON ev.id = n.evaluacion_id
                     WHERE n.inscripcion_id = i.id) AS detalle
             FROM inscripciones i
             JOIN grupos g ON g.id = i.grupo_id
             JOIN cursos c ON c.id = g.curso_id
             JOIN periodos pe ON pe.id = g.periodo_id
             JOIN matriculas m ON m.id = i.matricula_id
             WHERE m.estudiante_id = ?
             ORDER BY pe.fecha_inicio DESC, c.nombre",
            [$estudianteId]
        );
    }

    private static function assertPeriodOpen(int $periodoId): void
    {
        $state = (string) Database::scalar('SELECT estado FROM periodos WHERE id = ?', [$periodoId]);

        if ($state === 'cerrado') {
            throw new DomainException('RN-12', 'El periodo está cerrado y no admite cambios de calificaciones.');
        }
    }
}
