<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Services\GradeService;
use Tests\Support\AcademicScenario;
use Tests\Support\AuthContext;
use Tests\Support\DatabaseTestCase;
use Tests\Support\Fixtures;

/**
 * RF-13, RN-12 y RN-13: evaluaciones, pesos, escala de notas, promedio
 * ponderado, alertas de bajo rendimiento y bloqueo por periodo cerrado.
 */
final class GradeRulesTest extends DatabaseTestCase
{
    use AcademicScenario;

    public function testEvaluationRequiresNameAndAValidWeight(): void
    {
        $scenario = $this->academicScenario();
        $this->loginTeacher($scenario);
        $groupId = (int) $scenario['grupo_id'];

        $this->expectDomain('RF-13', fn () => GradeService::addEvaluation($groupId, '   ', 50.0, null), 'nombre');
        $this->expectDomain('RN-13', fn () => GradeService::addEvaluation($groupId, 'Parcial', 0.0, null), 'peso');
        $this->expectDomain('RN-13', fn () => GradeService::addEvaluation($groupId, 'Parcial', 100.5, null), 'peso');
    }

    public function testEvaluationNamesAreUniqueInsideTheGroup(): void
    {
        $scenario = $this->academicScenario();
        $this->loginTeacher($scenario);
        $groupId = (int) $scenario['grupo_id'];

        GradeService::addEvaluation($groupId, 'Parcial 1', 30.0, date('Y-m-d'));

        $this->expectDomain('RF-13', fn () => GradeService::addEvaluation(
            $groupId,
            'Parcial 1',
            30.0,
            date('Y-m-d')
        ), 'Ya existe');
    }

    public function testWeightsCannotExceedOneHundredPercent(): void
    {
        $scenario = $this->academicScenario();
        $this->loginTeacher($scenario);
        $groupId = (int) $scenario['grupo_id'];

        GradeService::addEvaluation($groupId, 'Parcial 1', 60.0, date('Y-m-d'));

        $this->expectDomain('RN-13', fn () => GradeService::addEvaluation(
            $groupId,
            'Parcial 2',
            50.0,
            date('Y-m-d')
        ), '100');
    }

    public function testGradesCannotBeSavedUntilWeightsSumOneHundredPercent(): void
    {
        $scenario = $this->academicScenario();
        $this->loginTeacher($scenario);
        $groupId = (int) $scenario['grupo_id'];
        $inscriptionId = (int) $this->enrollInScenario($scenario)['inscripcion_id'];

        $evaluationId = GradeService::addEvaluation($groupId, 'Parcial 1', 50.0, date('Y-m-d'));

        $this->expectDomain('RN-13', fn () => GradeService::saveGrades(
            $groupId,
            $evaluationId,
            [$inscriptionId => 4.0]
        ), '100');
    }

    public function testFinalGradeNormalizesByTheGradedWeightAndRoundsToOneDecimal(): void
    {
        $scenario = $this->academicScenario();
        $this->loginTeacher($scenario);
        $groupId = (int) $scenario['grupo_id'];
        $inscriptionId = (int) $this->enrollInScenario($scenario)['inscripcion_id'];

        $first = GradeService::addEvaluation($groupId, 'Parcial 1', 40.0, date('Y-m-d'));
        $second = GradeService::addEvaluation($groupId, 'Parcial 2', 60.0, date('Y-m-d'));

        GradeService::saveGrades($groupId, $first, [$inscriptionId => 2.5]);

        $partial = GradeService::finalForInscription($inscriptionId);
        $this->assertSame(2.5, $partial['nota']);
        $this->assertSame(40.0, $partial['peso_evaluado']);
        $this->assertFalse($partial['completo']);

        GradeService::saveGrades($groupId, $second, [$inscriptionId => 4.6]);

        $final = GradeService::finalForInscription($inscriptionId);
        // (2.5 * 40 + 4.6 * 60) / 100 = 3.76 -> 3.8
        $this->assertSame(3.8, $final['nota']);
        $this->assertSame(100.0, $final['peso_evaluado']);
        $this->assertTrue($final['completo']);
        $this->assertSame('3.8', (string) Database::scalar(
            'SELECT nota_final FROM inscripciones WHERE id = ?',
            [$inscriptionId]
        ));

        $matrix = GradeService::matrix($groupId);
        $this->assertSame(100.0, $matrix['weight']);
        $this->assertCount(1, $matrix['rows']);
        $this->assertSame(3.8, $matrix['rows'][0]['final']['nota']);
    }

    public function testGradesRespectTheScaleAndAreRoundedToOneDecimal(): void
    {
        $scenario = $this->academicScenario();
        $this->loginTeacher($scenario);
        $groupId = (int) $scenario['grupo_id'];
        $inscriptionId = (int) $this->enrollInScenario($scenario)['inscripcion_id'];

        $evaluationId = GradeService::addEvaluation($groupId, 'Parcial 1', 100.0, date('Y-m-d'));

        $this->expectDomain('RN-13', fn () => GradeService::saveGrades(
            $groupId,
            $evaluationId,
            [$inscriptionId => 5.1]
        ), 'entre');

        GradeService::saveGrades($groupId, $evaluationId, [$inscriptionId => '4,44']);

        $this->assertSame('4.4', (string) Database::scalar(
            'SELECT nota FROM notas WHERE evaluacion_id = ? AND inscripcion_id = ?',
            [$evaluationId, $inscriptionId]
        ));

        $sheet = GradeService::gradeSheet($groupId, $evaluationId);
        $this->assertCount(1, $sheet);
        $this->assertSame('4.4', (string) $sheet[0]['nota']);
    }

    public function testUnderperformanceAlertActivatesWhenHalfTheWeightIsGraded(): void
    {
        $scenario = $this->academicScenario();
        $this->loginTeacher($scenario);
        $groupId = (int) $scenario['grupo_id'];
        $inscriptionId = (int) $this->enrollInScenario($scenario)['inscripcion_id'];

        $evaluationId = GradeService::addEvaluation($groupId, 'Parcial 1', 100.0, date('Y-m-d'));
        GradeService::saveGrades($groupId, $evaluationId, [$inscriptionId => 2.0]);

        $alert = Database::fetch(
            "SELECT * FROM alertas WHERE inscripcion_id = ? AND tipo = 'bajo_rendimiento'",
            [$inscriptionId]
        );

        $this->assertNotNull($alert);
        $this->assertSame('advertencia', $alert['nivel']);
        $this->assertSame('nueva', $alert['estado']);
        $this->assertStringContainsString('Bajo rendimiento', (string) $alert['mensaje']);
    }

    public function testUnderperformanceAlertDoesNotActivateBeforeHalfTheWeightIsGraded(): void
    {
        $scenario = $this->academicScenario();
        $this->loginTeacher($scenario);
        $groupId = (int) $scenario['grupo_id'];
        $inscriptionId = (int) $this->enrollInScenario($scenario)['inscripcion_id'];

        $first = GradeService::addEvaluation($groupId, 'Parcial 1', 40.0, date('Y-m-d'));
        GradeService::addEvaluation($groupId, 'Parcial 2', 60.0, date('Y-m-d'));

        GradeService::saveGrades($groupId, $first, [$inscriptionId => 1.0]);

        $this->assertDatabaseCount(
            'alertas',
            0,
            "inscripcion_id = ? AND tipo = 'bajo_rendimiento'",
            [$inscriptionId]
        );
    }

    public function testOnlyTheAssignedTeacherCanGrade(): void
    {
        $scenario = $this->academicScenario();
        $otherTeacher = Fixtures::docente((int) $scenario['sede_id'], true);
        AuthContext::login((int) $otherTeacher['user_id']);

        $this->expectDomain('RN-12', fn () => GradeService::addEvaluation(
            (int) $scenario['grupo_id'],
            'Parcial',
            100.0,
            null
        ), 'docente asignado');

        $admin = Fixtures::usuario(Fixtures::persona(), 1, null, 'admin-califica');
        AuthContext::login($admin);

        $this->expectDomain('RN-12', fn () => GradeService::addEvaluation(
            (int) $scenario['grupo_id'],
            'Parcial',
            100.0,
            null
        ), 'Solo el docente asignado');
    }

    public function testClosedPeriodBlocksEvaluationChanges(): void
    {
        $scenario = $this->academicScenario();
        $this->loginTeacher($scenario);

        Database::update('periodos', ['estado' => 'cerrado'], ['id' => (int) $scenario['periodo_id']]);

        $this->expectDomain('RN-12', fn () => GradeService::addEvaluation(
            (int) $scenario['grupo_id'],
            'Parcial',
            100.0,
            null
        ), 'cerrado');
    }
}
