<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Param;
use App\Services\EnrollmentService;
use App\Services\GradeService;
use App\Services\PeriodService;
use Tests\Support\AcademicScenario;
use Tests\Support\DatabaseTestCase;
use Tests\Support\Fixtures;

/**
 * RF-06, RF-22 y RN-03/RN-13: ciclo de vida del periodo, apertura única de
 * matrícula, confirmación por grupos bajo el cupo mínimo y cierre académico.
 */
final class PeriodRulesTest extends DatabaseTestCase
{
    use AcademicScenario;

    public function testCreateValidatesDatesAndUniqueness(): void
    {
        $base = [
            'codigo' => 'PER-TEST',
            'nombre' => 'Periodo de prueba funcional',
            'fecha_inicio' => date('Y-m-d'),
            'fecha_fin' => date('Y-m-d', strtotime('+90 days')),
            'matricula_inicio' => date('Y-m-d'),
            'matricula_fin' => date('Y-m-d', strtotime('+30 days')),
        ];

        $this->expectDomain('RF-06', fn () => PeriodService::create(array_merge($base, [
            'fecha_fin' => $base['fecha_inicio'],
        ])), 'posterior');

        $this->expectDomain('RF-06', fn () => PeriodService::create(array_merge($base, [
            'matricula_fin' => date('Y-m-d', strtotime('+120 days')),
        ])), 'antes del fin');

        $id = PeriodService::create($base);

        $this->assertSame('planificado', (string) Database::scalar(
            'SELECT estado FROM periodos WHERE id = ?',
            [$id]
        ));
        $this->assertDatabaseCount('auditoria', 1, "accion = 'periodo_creado' AND entidad_id = ?", [(string) $id]);

        $this->expectDomain('RF-06', fn () => PeriodService::create($base), 'código');

        $this->expectDomain('RF-06', fn () => PeriodService::create(array_merge($base, [
            'codigo' => 'PER-OTRO',
        ])), 'nombre');
    }

    public function testOnlyOnePeriodMayHaveOpenEnrollment(): void
    {
        Fixtures::periodo();
        $planned = Fixtures::periodo(['estado' => 'planificado']);

        $this->expectDomain('RF-06', fn () => PeriodService::openEnrollment($planned), 'matrícula abierta');
    }

    public function testOpenEnrollmentRequiresExplicitConfirmationForLowGroups(): void
    {
        $scenario = $this->academicScenario([
            'periodo_id' => Fixtures::periodo(['estado' => 'planificado']),
        ]);

        $this->expectDomain('RN-03', fn () => PeriodService::openEnrollment(
            (int) $scenario['periodo_id']
        ), 'cupo mínimo');

        $result = PeriodService::openEnrollment(
            (int) $scenario['periodo_id'],
            true,
            'Aprobado por coordinación académica'
        );

        $this->assertCount(1, $result['lowGroups']);
        $this->assertSame('matricula_abierta', (string) Database::scalar(
            'SELECT estado FROM periodos WHERE id = ?',
            [$scenario['periodo_id']]
        ));

        $audit = Database::fetch(
            "SELECT * FROM auditoria WHERE accion = 'periodo_matricula_abierta' AND entidad_id = ?",
            [(string) $scenario['periodo_id']]
        );
        $this->assertNotNull($audit);
        $this->assertStringContainsString('"confirmacion":true', (string) $audit['valor_nuevo']);
        $this->assertStringContainsString('Aprobado por coordinación', (string) $audit['valor_nuevo']);
    }

    public function testStartMovesThePeriodToInCourseAndRejectsClosedPeriods(): void
    {
        $periodId = Fixtures::periodo(['estado' => 'planificado']);

        PeriodService::start($periodId);
        $this->assertSame('en_curso', (string) Database::scalar(
            'SELECT estado FROM periodos WHERE id = ?',
            [$periodId]
        ));

        Database::update('periodos', ['estado' => 'cerrado'], ['id' => $periodId]);

        $this->expectDomain('RF-06', fn () => PeriodService::start($periodId), 'estado actual');
    }

    public function testUpdateRejectsAClosedPeriod(): void
    {
        $periodId = Fixtures::periodo();
        Database::update('periodos', ['estado' => 'cerrado'], ['id' => $periodId]);

        $this->expectDomain('RF-22', fn () => PeriodService::update($periodId, [
            'nombre' => 'Nombre nuevo',
        ]), 'no puede modificarse');
    }

    public function testCloseConsolidatesFinalGradesAndFinalizesEnrollments(): void
    {
        $scenario = $this->academicScenario();
        $this->loginTeacher($scenario);
        $groupId = (int) $scenario['grupo_id'];
        $inscriptionId = (int) $this->enrollInScenario($scenario)['inscripcion_id'];

        $evaluationId = GradeService::addEvaluation($groupId, 'Parcial final', 100.0, date('Y-m-d'));
        GradeService::saveGrades($groupId, $evaluationId, [$inscriptionId => 4.0]);

        $result = PeriodService::close((int) $scenario['periodo_id']);

        $this->assertSame(1, $result['inscripciones']);
        $this->assertSame('cerrado', (string) Database::scalar(
            'SELECT estado FROM periodos WHERE id = ?',
            [$scenario['periodo_id']]
        ));

        $inscription = Database::fetch('SELECT * FROM inscripciones WHERE id = ?', [$inscriptionId]);
        $this->assertSame('finalizada', $inscription['estado']);
        $this->assertSame('4.0', (string) $inscription['nota_final']);

        $this->assertSame('finalizada', (string) Database::scalar(
            'SELECT estado FROM matriculas WHERE id = ?',
            [$scenario['matricula_id']]
        ));

        $this->assertDatabaseCount(
            'auditoria',
            1,
            "accion = 'periodo_cerrado' AND entidad_id = ?",
            [(string) $scenario['periodo_id']]
        );

        $this->expectDomain('RF-22', fn () => PeriodService::close(
            (int) $scenario['periodo_id']
        ), 'ya está cerrado');
    }

    public function testCloseRejectsIncompleteWeightsUnlessForced(): void
    {
        $scenario = $this->academicScenario();
        $this->loginTeacher($scenario);
        $groupId = (int) $scenario['grupo_id'];
        $inscriptionId = (int) $this->enrollInScenario($scenario)['inscripcion_id'];

        GradeService::addEvaluation($groupId, 'Parcial 1', 50.0, date('Y-m-d'));

        $this->expectDomain('RN-13', fn () => PeriodService::close(
            (int) $scenario['periodo_id']
        ), 'Ajuste los pesos');

        $result = PeriodService::close((int) $scenario['periodo_id'], true);

        $this->assertSame(1, $result['inscripciones']);
        $this->assertSame('finalizada', (string) Database::scalar(
            'SELECT estado FROM inscripciones WHERE id = ?',
            [$inscriptionId]
        ));
        $this->assertSame('cerrado', (string) Database::scalar(
            'SELECT estado FROM periodos WHERE id = ?',
            [$scenario['periodo_id']]
        ));
    }

    public function testGroupsBelowMinimumUsesTheConfiguredParameter(): void
    {
        Database::update('parametros', ['valor' => '2'], ['clave' => 'cupo_minimo_grupo']);
        Param::refresh();

        $scenario = $this->academicScenario();

        $this->assertCount(1, PeriodService::groupsBelowMinimum((int) $scenario['periodo_id']));

        $this->enrollInScenario($scenario);
        $this->assertCount(1, PeriodService::groupsBelowMinimum((int) $scenario['periodo_id']));

        $secondStudent = Fixtures::estudiante((int) $scenario['sede_id']);
        $secondMatricula = Fixtures::matricula(
            (int) $secondStudent['id'],
            (int) $scenario['programa_id'],
            (int) $scenario['periodo_id'],
            (int) $scenario['sede_id']
        );
        EnrollmentService::enroll($secondMatricula, (int) $scenario['grupo_id']);

        $this->assertCount(0, PeriodService::groupsBelowMinimum((int) $scenario['periodo_id']));
    }
}
