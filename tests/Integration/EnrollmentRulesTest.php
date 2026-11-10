<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Param;
use App\Services\EnrollmentService;
use App\Services\ProgramService;
use Tests\Support\AcademicScenario;
use Tests\Support\AuthContext;
use Tests\Support\DatabaseTestCase;
use Tests\Support\Fixtures;

/**
 * RF-09 a RF-11 y RN-02 a RN-11, RN-16, RN-19: reglas de matrícula,
 * inscripción, cambio de grupo y cancelación.
 */
final class EnrollmentRulesTest extends DatabaseTestCase
{
    use AcademicScenario;

    public function testCreateEnrollmentGeneratesCodeChargeAndTemporaryCredentials(): void
    {
        $scenario = $this->academicScenario(['matricula' => false]);

        $result = EnrollmentService::createEnrollment(
            (int) $scenario['estudiante']['id'],
            (int) $scenario['programa_id'],
            (int) $scenario['periodo_id']
        );

        $this->assertGreaterThan(0, $result['matricula_id']);
        $this->assertMatchesRegularExpression('/^MAT-\d{4}-\d{5}$/', $result['codigo']);
        $this->assertNotNull($result['credentials']);
        $this->assertArrayHasKey('password', $result['credentials']);

        $matricula = EnrollmentService::findMatricula($result['matricula_id']);
        $this->assertSame('activa', $matricula['estado']);
        $this->assertSame((int) $scenario['estudiante']['id'], (int) $matricula['estudiante_id']);

        $charge = Database::fetch(
            'SELECT * FROM cargos WHERE matricula_id = ?',
            [$result['matricula_id']]
        );
        $this->assertNotNull($charge);
        $this->assertSame('350000.00', (string) $charge['valor_total']);
        $this->assertSame('pendiente', $charge['estado']);
        $this->assertStringContainsString('Matrícula', (string) $charge['descripcion']);

        $user = Database::fetch(
            'SELECT u.* FROM usuarios u WHERE u.persona_id = ? AND u.rol_id = 7',
            [(int) $scenario['estudiante']['persona_id']]
        );
        $this->assertNotNull($user);
        $this->assertSame('1', (string) $user['debe_cambiar_password']);
        $this->assertSame(
            (string) Database::scalar('SELECT numero_documento FROM personas WHERE id = ?', [(int) $scenario['estudiante']['persona_id']]),
            (string) $user['username']
        );

        $this->assertDatabaseCount('auditoria', 1, "accion = 'matricula_creada' AND entidad_id = ?", [(string) $result['matricula_id']]);
    }

    public function testCreateEnrollmentRejectsDuplicateMatricula(): void
    {
        $scenario = $this->academicScenario(['matricula' => false]);

        EnrollmentService::createEnrollment(
            (int) $scenario['estudiante']['id'],
            (int) $scenario['programa_id'],
            (int) $scenario['periodo_id']
        );

        $this->expectDomain('RF-09', fn () => EnrollmentService::createEnrollment(
            (int) $scenario['estudiante']['id'],
            (int) $scenario['programa_id'],
            (int) $scenario['periodo_id']
        ), 'ya tiene una matrícula');
    }

    public function testCreateEnrollmentRequiresOpenEnrollmentPeriod(): void
    {
        $scenario = $this->academicScenario(['matricula' => false]);
        Database::update('periodos', ['estado' => 'en_curso'], ['id' => (int) $scenario['periodo_id']]);

        $this->expectDomain('RN-09', fn () => EnrollmentService::createEnrollment(
            (int) $scenario['estudiante']['id'],
            (int) $scenario['programa_id'],
            (int) $scenario['periodo_id']
        ), 'no está abierta');
    }

    public function testCreateEnrollmentRequiresCurrentDateInsideEnrollmentWindow(): void
    {
        $scenario = $this->academicScenario([
            'matricula' => false,
            'periodo_id' => Fixtures::periodo([
                'matricula_inicio' => date('Y-m-d', strtotime('-40 days')),
                'matricula_fin' => date('Y-m-d', strtotime('-1 day')),
            ]),
        ]);

        $this->expectDomain('RN-09', fn () => EnrollmentService::createEnrollment(
            (int) $scenario['estudiante']['id'],
            (int) $scenario['programa_id'],
            (int) $scenario['periodo_id']
        ), 'fuera del calendario');
    }

    public function testCreateEnrollmentIsScopedToTheSecretariaSede(): void
    {
        $userId = Fixtures::usuario(Fixtures::persona(), 2, 1, 'secretaria-sede-1');
        AuthContext::login($userId);

        $scenario = $this->academicScenario(['sede_id' => 2, 'matricula' => false]);

        $this->expectDomain('RN-19', fn () => EnrollmentService::createEnrollment(
            (int) $scenario['estudiante']['id'],
            (int) $scenario['programa_id'],
            (int) $scenario['periodo_id']
        ), 'su sede');
    }

    public function testCreateEnrollmentIsBlockedByOverdueDebt(): void
    {
        $scenario = $this->academicScenario(['matricula' => false]);
        Fixtures::cargo(
            (int) $scenario['estudiante']['id'],
            100000.0,
            null,
            date('Y-m-d', strtotime('-31 days'))
        );

        $this->expectDomain('RN-16', fn () => EnrollmentService::createEnrollment(
            (int) $scenario['estudiante']['id'],
            (int) $scenario['programa_id'],
            (int) $scenario['periodo_id']
        ), 'saldo vencido');
    }

    public function testCreateEnrollmentAllowsDebtInsideGracePeriod(): void
    {
        $scenario = $this->academicScenario(['matricula' => false]);
        Fixtures::cargo(
            (int) $scenario['estudiante']['id'],
            100000.0,
            null,
            date('Y-m-d', strtotime('-10 days'))
        );

        $result = EnrollmentService::createEnrollment(
            (int) $scenario['estudiante']['id'],
            (int) $scenario['programa_id'],
            (int) $scenario['periodo_id']
        );

        $this->assertGreaterThan(0, $result['matricula_id']);
    }

    public function testEnrollRequiresTheCourseToBelongToTheStudyPlan(): void
    {
        $scenario = $this->academicScenario(['plan' => false]);

        $this->expectDomain('RN-07', fn () => EnrollmentService::enroll(
            (int) $scenario['matricula_id'],
            (int) $scenario['grupo_id']
        ), 'plan de estudios');
    }

    public function testEnrollCreatesInscriptionAndAuditTrail(): void
    {
        $scenario = $this->academicScenario();

        $result = EnrollmentService::enroll((int) $scenario['matricula_id'], (int) $scenario['grupo_id']);

        $this->assertGreaterThan(0, $result['inscripcion_id']);
        $this->assertSame('activa', (string) Database::scalar(
            'SELECT estado FROM inscripciones WHERE id = ?',
            [$result['inscripcion_id']]
        ));
        $this->assertDatabaseCount('inscripcion_movimientos', 1, "inscripcion_id = ? AND tipo = 'inscripcion'", [$result['inscripcion_id']]);
        $this->assertDatabaseCount('auditoria', 1, "accion = 'inscripcion_creada' AND entidad_id = ?", [(string) $result['inscripcion_id']]);
    }

    public function testEnrollRejectsDuplicatedInscriptionInTheSameGroup(): void
    {
        $scenario = $this->academicScenario();
        EnrollmentService::enroll((int) $scenario['matricula_id'], (int) $scenario['grupo_id']);

        $this->expectDomain('RN-04', fn () => EnrollmentService::enroll(
            (int) $scenario['matricula_id'],
            (int) $scenario['grupo_id']
        ), 'ya está inscrito');
    }

    public function testEnrollRejectsTheSameCourseInAnotherGroup(): void
    {
        $scenario = $this->academicScenario();
        $otherGroup = $this->addGroup($scenario);

        EnrollmentService::enroll((int) $scenario['matricula_id'], (int) $scenario['grupo_id']);

        $this->expectDomain('RN-08', fn () => EnrollmentService::enroll(
            (int) $scenario['matricula_id'],
            $otherGroup
        ), 'ya está inscrito en el curso');
    }

    public function testEnrollRejectsExceedingTheMaximumCoursesPerPeriod(): void
    {
        Database::update('parametros', ['valor' => '1'], ['clave' => 'max_cursos_periodo']);
        Param::refresh();

        $scenario = $this->academicScenario();
        $secondCourse = Fixtures::curso();
        Fixtures::plan((int) $scenario['programa_id'], $secondCourse);
        $secondGroup = Fixtures::grupo(
            $secondCourse,
            (int) $scenario['docente']['id'],
            (int) $scenario['periodo_id'],
            (int) $scenario['sede_id'],
            (int) $scenario['aula_id']
        );

        EnrollmentService::enroll((int) $scenario['matricula_id'], (int) $scenario['grupo_id']);

        $this->expectDomain('RN-10', fn () => EnrollmentService::enroll(
            (int) $scenario['matricula_id'],
            $secondGroup
        ), 'límite de 1 cursos');
    }

    public function testEnrollRejectsWhenTheGroupHasNoSeats(): void
    {
        $scenario = $this->academicScenario(['capacidad' => 1]);
        EnrollmentService::enroll((int) $scenario['matricula_id'], (int) $scenario['grupo_id']);

        $otherStudent = Fixtures::estudiante((int) $scenario['sede_id']);
        $otherMatricula = Fixtures::matricula(
            (int) $otherStudent['id'],
            (int) $scenario['programa_id'],
            (int) $scenario['periodo_id'],
            (int) $scenario['sede_id']
        );

        $this->expectDomain('RN-03', fn () => EnrollmentService::enroll(
            $otherMatricula,
            (int) $scenario['grupo_id']
        ), 'no tiene cupo disponible (1/1)');
    }

    public function testEnrollRequiresPrerequisites(): void
    {
        $scenario = $this->academicScenario();
        $prerequisiteCourse = Fixtures::curso();
        Fixtures::plan((int) $scenario['programa_id'], $prerequisiteCourse);
        ProgramService::addPrerequisite((int) $scenario['curso_id'], $prerequisiteCourse, 'cursado');

        $this->expectDomain('RN-02', fn () => EnrollmentService::enroll(
            (int) $scenario['matricula_id'],
            (int) $scenario['grupo_id']
        ), 'prerrequisito');

        $prerequisiteGroup = Fixtures::grupo(
            $prerequisiteCourse,
            (int) $scenario['docente']['id'],
            (int) $scenario['periodo_id'],
            (int) $scenario['sede_id'],
            (int) $scenario['aula_id']
        );
        Fixtures::inscripcion((int) $scenario['matricula_id'], $prerequisiteGroup, 'finalizada');

        $result = EnrollmentService::enroll((int) $scenario['matricula_id'], (int) $scenario['grupo_id']);
        $this->assertGreaterThan(0, $result['inscripcion_id']);
    }

    public function testEnrollRejectsStudentScheduleConflict(): void
    {
        $scenario = $this->academicScenario([
            'horarios' => [['dia_semana' => 1, 'hora_inicio' => '08:00', 'hora_fin' => '10:00']],
        ]);
        EnrollmentService::enroll((int) $scenario['matricula_id'], (int) $scenario['grupo_id']);

        $secondCourse = Fixtures::curso();
        Fixtures::plan((int) $scenario['programa_id'], $secondCourse);
        $secondGroup = Fixtures::grupo(
            $secondCourse,
            (int) $scenario['docente']['id'],
            (int) $scenario['periodo_id'],
            (int) $scenario['sede_id'],
            (int) $scenario['aula_id'],
            30,
            [['dia_semana' => 1, 'hora_inicio' => '09:00', 'hora_fin' => '11:00']]
        );

        $this->expectDomain('RN-05', fn () => EnrollmentService::enroll(
            (int) $scenario['matricula_id'],
            $secondGroup
        ), 'se cruza');
    }

    public function testChangeGroupCancelsThePreviousInscription(): void
    {
        $scenario = $this->academicScenario();
        $first = EnrollmentService::enroll((int) $scenario['matricula_id'], (int) $scenario['grupo_id']);
        $secondGroup = $this->addGroup($scenario);

        $changed = EnrollmentService::changeGroup($first['inscripcion_id'], $secondGroup, 'Ajuste de horario');

        $this->assertNotSame($first['inscripcion_id'], $changed['inscripcion_id']);
        $this->assertSame('cancelada', (string) Database::scalar(
            'SELECT estado FROM inscripciones WHERE id = ?',
            [$first['inscripcion_id']]
        ));
        $this->assertSame('activa', (string) Database::scalar(
            'SELECT estado FROM inscripciones WHERE id = ?',
            [$changed['inscripcion_id']]
        ));
        $this->assertDatabaseCount(
            'inscripcion_movimientos',
            1,
            "inscripcion_id = ? AND tipo = 'cambio_grupo' AND motivo = 'Ajuste de horario'",
            [$changed['inscripcion_id']]
        );
        $this->assertDatabaseCount('auditoria', 1, "accion = 'cambio_grupo' AND entidad_id = ?", [(string) $changed['inscripcion_id']]);
    }

    public function testChangeGroupRejectsTheSameGroup(): void
    {
        $scenario = $this->academicScenario();
        $first = EnrollmentService::enroll((int) $scenario['matricula_id'], (int) $scenario['grupo_id']);

        $this->expectDomain('RF-11', fn () => EnrollmentService::changeGroup(
            $first['inscripcion_id'],
            (int) $scenario['grupo_id'],
            'Sin cambio'
        ), 'diferente');
    }

    public function testCancelInscriptionInsideTheDeadline(): void
    {
        $scenario = $this->academicScenario();
        $inscription = EnrollmentService::enroll((int) $scenario['matricula_id'], (int) $scenario['grupo_id']);

        EnrollmentService::cancel($inscription['inscripcion_id'], 'Retiro voluntario');

        $this->assertSame('cancelada', (string) Database::scalar(
            'SELECT estado FROM inscripciones WHERE id = ?',
            [$inscription['inscripcion_id']]
        ));
        $this->assertDatabaseCount(
            'inscripcion_movimientos',
            1,
            "inscripcion_id = ? AND tipo = 'cancelacion' AND motivo = 'Retiro voluntario'",
            [$inscription['inscripcion_id']]
        );
        $this->assertDatabaseCount('auditoria', 1, "accion = 'inscripcion_cancelada' AND entidad_id = ?", [(string) $inscription['inscripcion_id']]);
    }

    public function testCancelInscriptionRejectsAfterTheDeadline(): void
    {
        $scenario = $this->academicScenario([
            'periodo_id' => Fixtures::periodo([
                'fecha_inicio' => date('Y-m-d', strtotime('-30 days')),
                'matricula_inicio' => date('Y-m-d', strtotime('-30 days')),
                'matricula_fin' => date('Y-m-d', strtotime('+30 days')),
            ]),
        ]);
        $inscription = EnrollmentService::enroll((int) $scenario['matricula_id'], (int) $scenario['grupo_id']);

        $this->expectDomain('RN-11', fn () => EnrollmentService::cancel(
            $inscription['inscripcion_id'],
            'Retiro tardío'
        ), 'plazo para cancelar venció');
    }

    public function testEnrollReactivatesAPreviouslyCancelledInscription(): void
    {
        $scenario = $this->academicScenario();
        $inscription = EnrollmentService::enroll((int) $scenario['matricula_id'], (int) $scenario['grupo_id']);
        EnrollmentService::cancel($inscription['inscripcion_id'], 'Retiro temporal');

        $reactivated = EnrollmentService::enroll((int) $scenario['matricula_id'], (int) $scenario['grupo_id']);

        $this->assertSame($inscription['inscripcion_id'], $reactivated['inscripcion_id']);
        $this->assertSame('activa', (string) Database::scalar(
            'SELECT estado FROM inscripciones WHERE id = ?',
            [$inscription['inscripcion_id']]
        ));
        $this->assertDatabaseCount(
            'inscripcion_movimientos',
            1,
            "inscripcion_id = ? AND tipo = 'reactivacion'",
            [$inscription['inscripcion_id']]
        );
    }

    public function testEnrollRejectsAClosedPeriod(): void
    {
        $scenario = $this->academicScenario();
        Database::update('periodos', ['estado' => 'cerrado'], ['id' => (int) $scenario['periodo_id']]);

        $this->expectDomain('RF-22', fn () => EnrollmentService::enroll(
            (int) $scenario['matricula_id'],
            (int) $scenario['grupo_id']
        ), 'periodo del grupo está cerrado');
    }

    public function testEnrollRejectsAnInactiveMatriculaOrGroup(): void
    {
        $scenario = $this->academicScenario();
        Database::update('matriculas', ['estado' => 'cancelada'], ['id' => (int) $scenario['matricula_id']]);

        $this->expectDomain('RF-10', fn () => EnrollmentService::enroll(
            (int) $scenario['matricula_id'],
            (int) $scenario['grupo_id']
        ), 'matrícula no está activa');

        Database::update('matriculas', ['estado' => 'activa'], ['id' => (int) $scenario['matricula_id']]);
        Database::update('grupos', ['estado' => 'inactivo'], ['id' => (int) $scenario['grupo_id']]);

        $this->expectDomain('RF-10', fn () => EnrollmentService::enroll(
            (int) $scenario['matricula_id'],
            (int) $scenario['grupo_id']
        ), 'grupo no está activo');
    }
}
