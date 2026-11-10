<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Services\AttendanceService;
use Tests\Support\AcademicScenario;
use Tests\Support\AuthContext;
use Tests\Support\DatabaseTestCase;
use Tests\Support\Fixtures;

/**
 * RF-14, RF-17 y RN-12: sesiones, registro de asistencia, exclusiones por
 * excusa, umbrales de alerta y pérdida por inasistencia.
 */
final class AttendanceRulesTest extends DatabaseTestCase
{
    use AcademicScenario;

    public function testCreateSessionValidatesPeriodDateTimesAndDuplicates(): void
    {
        $scenario = $this->academicScenario();
        $this->loginTeacher($scenario);
        $groupId = (int) $scenario['grupo_id'];
        $today = date('Y-m-d');

        $this->expectDomain('RF-14', fn () => AttendanceService::createSession(
            $groupId,
            date('Y-m-d', strtotime('+200 days')),
            '08:00',
            '10:00'
        ), 'dentro del periodo');

        $this->expectDomain('RF-14', fn () => AttendanceService::createSession(
            $groupId,
            $today,
            '8:00',
            '10:00'
        ), 'formato');

        $this->expectDomain('RF-14', fn () => AttendanceService::createSession(
            $groupId,
            $today,
            '10:00',
            '08:00'
        ), 'posterior');

        AttendanceService::createSession($groupId, $today, '08:00', '10:00', 'Clase inicial');

        $this->expectDomain('RF-14', fn () => AttendanceService::createSession(
            $groupId,
            $today,
            '08:00',
            '10:00'
        ), 'Ya existe');
    }

    public function testSavingAttendanceClosesTheSessionAndRecordsTheExcuse(): void
    {
        $scenario = $this->academicScenario();
        $this->loginTeacher($scenario);
        $groupId = (int) $scenario['grupo_id'];
        $inscriptionId = (int) $this->enrollInScenario($scenario)['inscripcion_id'];

        $sessionId = AttendanceService::createSession($groupId, date('Y-m-d'), '08:00', '10:00');

        AttendanceService::save(
            $sessionId,
            [$inscriptionId => 'excusa'],
            [$inscriptionId => 'Cita médica']
        );

        $this->assertSame('cerrada', (string) Database::scalar(
            'SELECT estado FROM sesiones WHERE id = ?',
            [$sessionId]
        ));
        $this->assertSame('excusa', (string) Database::scalar(
            'SELECT estado FROM asistencias WHERE sesion_id = ? AND inscripcion_id = ?',
            [$sessionId, $inscriptionId]
        ));
        $this->assertDatabaseCount(
            'excusas',
            1,
            "motivo = 'Cita médica' AND estado = 'aprobada'"
        );
        $this->assertDatabaseCount(
            'auditoria',
            1,
            "accion = 'asistencia_registrada' AND entidad_id = ?",
            [(string) $sessionId]
        );
    }

    public function testSummaryExcludesExcusedAbsencesFromTheDenominator(): void
    {
        $scenario = $this->academicScenario();
        $groupId = (int) $scenario['grupo_id'];
        $inscriptionId = (int) Fixtures::inscripcion(
            (int) $scenario['matricula_id'],
            $groupId
        );

        $states = ['ausente', 'excusa', 'presente', 'presente', 'presente'];

        foreach ($states as $index => $state) {
            $sessionId = Fixtures::sesion(
                $groupId,
                date('Y-m-d', strtotime('-' . ($index + 1) . ' days'))
            );
            Fixtures::asistencia($sessionId, $inscriptionId, $state);
        }

        $summary = AttendanceService::summary($groupId);

        $this->assertSame(5, $summary['sessions']);
        $this->assertSame(20.0, $summary['alerta']);
        $this->assertSame(25.0, $summary['perdida']);
        $this->assertSame(25.0, (float) $summary['rows'][0]['porcentaje_inasistencia']);
        $this->assertSame('alerta', $summary['rows'][0]['estado_asistencia']);
    }

    public function testTardeCountsAsPresentForAbsencePercentage(): void
    {
        $scenario = $this->academicScenario();
        $groupId = (int) $scenario['grupo_id'];
        $inscriptionId = (int) Fixtures::inscripcion((int) $scenario['matricula_id'], $groupId);

        $states = ['tarde', 'presente', 'presente', 'presente'];

        foreach ($states as $index => $state) {
            $sessionId = Fixtures::sesion(
                $groupId,
                date('Y-m-d', strtotime('-' . ($index + 1) . ' days'))
            );
            Fixtures::asistencia($sessionId, $inscriptionId, $state);
        }

        $summary = AttendanceService::summary($groupId);

        $this->assertSame(1, (int) $summary['rows'][0]['tardes']);
        $this->assertSame(0.0, (float) $summary['rows'][0]['porcentaje_inasistencia']);
        $this->assertSame('normal', $summary['rows'][0]['estado_asistencia']);
    }

    public function testAbsenceOverTheLossThresholdMarksTheInscriptionAsLost(): void
    {
        $scenario = $this->academicScenario();
        $groupId = (int) $scenario['grupo_id'];
        $inscriptionId = (int) Fixtures::inscripcion((int) $scenario['matricula_id'], $groupId);

        $states = ['ausente', 'ausente', 'presente', 'presente'];

        foreach ($states as $index => $state) {
            $sessionId = Fixtures::sesion(
                $groupId,
                date('Y-m-d', strtotime('-' . ($index + 1) . ' days'))
            );
            Fixtures::asistencia($sessionId, $inscriptionId, $state);
        }

        AttendanceService::evaluateGroup($groupId);

        $inscription = Database::fetch('SELECT * FROM inscripciones WHERE id = ?', [$inscriptionId]);
        $this->assertSame('perdida_inasistencia', $inscription['estado']);
        $this->assertSame('0.0', (string) $inscription['nota_final']);

        $this->assertDatabaseCount(
            'inscripcion_movimientos',
            1,
            "inscripcion_id = ? AND tipo = 'finalizacion'",
            [$inscriptionId]
        );
        $this->assertDatabaseCount(
            'auditoria',
            1,
            "accion = 'perdida_inasistencia' AND entidad_id = ?",
            [(string) $inscriptionId]
        );

        $alert = Database::fetch(
            "SELECT * FROM alertas WHERE inscripcion_id = ? AND tipo = 'inasistencia'",
            [$inscriptionId]
        );
        $this->assertNotNull($alert);
        $this->assertSame('critica', $alert['nivel']);
        $this->assertStringContainsString('Pérdida por inasistencia', (string) $alert['mensaje']);
    }

    public function testOnlyTheAssignedTeacherCanRegisterAttendance(): void
    {
        $scenario = $this->academicScenario();
        $groupId = (int) $scenario['grupo_id'];
        $sessionId = Fixtures::sesion($groupId, date('Y-m-d'), '08:00:00', '10:00:00', 'abierta');

        $otherTeacher = Fixtures::docente((int) $scenario['sede_id'], true);
        AuthContext::login((int) $otherTeacher['user_id']);

        $this->expectDomain('RN-12', fn () => AttendanceService::createSession(
            $groupId,
            date('Y-m-d', strtotime('+1 day')),
            '08:00',
            '10:00'
        ), 'docente asignado');

        $this->expectDomain('RN-12', fn () => AttendanceService::save($sessionId, []), 'docente asignado');
    }

    public function testClosedPeriodBlocksAttendanceChanges(): void
    {
        $scenario = $this->academicScenario();
        $this->loginTeacher($scenario);
        $groupId = (int) $scenario['grupo_id'];
        $sessionId = AttendanceService::createSession($groupId, date('Y-m-d'), '08:00', '10:00');

        Database::update('periodos', ['estado' => 'cerrado'], ['id' => (int) $scenario['periodo_id']]);

        $this->expectDomain('RF-22', fn () => AttendanceService::save($sessionId, []), 'cerrado');
    }

    public function testStudentSummaryReflectsOnlyTheStudentInscriptions(): void
    {
        $scenario = $this->academicScenario();
        $groupId = (int) $scenario['grupo_id'];
        $inscriptionId = (int) Fixtures::inscripcion((int) $scenario['matricula_id'], $groupId);

        $sessionId = Fixtures::sesion($groupId, date('Y-m-d', strtotime('-1 day')));
        Fixtures::asistencia($sessionId, $inscriptionId, 'ausente');

        $summary = AttendanceService::studentSummary((int) $scenario['estudiante']['id']);

        $this->assertCount(1, $summary);
        $this->assertSame($groupId, (int) $summary[0]['grupo_id']);
        $this->assertSame(1, (int) $summary[0]['ausentes']);
        $this->assertSame(100.0, (float) $summary[0]['porcentaje_inasistencia']);
        $this->assertSame('perdida', $summary[0]['estado_asistencia']);
    }
}
