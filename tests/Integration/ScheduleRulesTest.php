<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Services\GroupService;
use App\Services\ScheduleService;
use Tests\Support\AcademicScenario;
use Tests\Support\DatabaseTestCase;
use Tests\Support\Fixtures;

/**
 * RF-07, RN-05 y RN-06: validación de bloques de horario y cruces de docente,
 * aula y estudiante.
 */
final class ScheduleRulesTest extends DatabaseTestCase
{
    use AcademicScenario;

    public function testFromRequestValidatesBlocksAndNormalizesTimes(): void
    {
        $this->expectDomain('RF-07', fn () => ScheduleService::fromRequest([], [], []), 'al menos un bloque');

        $this->expectDomain('RF-07', fn () => ScheduleService::fromRequest([8], ['08:00'], ['10:00']), 'día válido');
        $this->expectDomain('RF-07', fn () => ScheduleService::fromRequest([1], ['8:00'], ['10:00']), 'formato');
        $this->expectDomain('RF-07', fn () => ScheduleService::fromRequest([1], ['10:00'], ['08:00']), 'posterior');

        $blocks = ScheduleService::fromRequest([1, 3], ['08:00', '14:00'], ['10:00', '16:00']);

        $this->assertCount(2, $blocks);
        $this->assertSame('08:00:00', $blocks[0]['hora_inicio']);
        $this->assertSame('16:00:00', $blocks[1]['hora_fin']);
    }

    public function testFromRequestRejectsOverlappingBlocksOfTheSameGroup(): void
    {
        $this->expectDomain('RF-07', fn () => ScheduleService::fromRequest(
            [1, 1],
            ['08:00', '09:00'],
            ['10:00', '11:00']
        ), 'se cruzan');
    }

    public function testTeacherConflictIsDetectedAcrossOverlappingPeriods(): void
    {
        $scenario = $this->academicScenario([
            'horarios' => [['dia_semana' => 1, 'hora_inicio' => '08:00', 'hora_fin' => '10:00']],
        ]);

        $this->expectDomain('RN-06', fn () => ScheduleService::assertTeacherFree(
            (int) $scenario['docente']['id'],
            (int) $scenario['periodo_id'],
            [['dia_semana' => 1, 'hora_inicio' => '09:00:00', 'hora_fin' => '11:00:00']]
        ), 'El docente ya tiene');
    }

    public function testClassroomConflictIsDetected(): void
    {
        $scenario = $this->academicScenario([
            'horarios' => [['dia_semana' => 2, 'hora_inicio' => '08:00', 'hora_fin' => '10:00']],
        ]);

        $this->expectDomain('RN-06', fn () => ScheduleService::assertClassroomFree(
            (int) $scenario['aula_id'],
            (int) $scenario['periodo_id'],
            [['dia_semana' => 2, 'hora_inicio' => '09:00:00', 'hora_fin' => '11:00:00']]
        ), 'aula ya está ocupada');
    }

    public function testStudentConflictIsDetected(): void
    {
        $scenario = $this->academicScenario([
            'horarios' => [['dia_semana' => 3, 'hora_inicio' => '08:00', 'hora_fin' => '10:00']],
        ]);

        Fixtures::inscripcion((int) $scenario['matricula_id'], (int) $scenario['grupo_id']);

        $this->expectDomain('RN-05', fn () => ScheduleService::assertStudentFree(
            (int) $scenario['matricula_id'],
            (int) $scenario['periodo_id'],
            [['dia_semana' => 3, 'hora_inicio' => '09:00:00', 'hora_fin' => '11:00:00']]
        ), 'se cruza');
    }

    public function testGroupServiceCreatesTheGroupWithItsSchedule(): void
    {
        $scenario = $this->academicScenario();

        $groupId = GroupService::create([
            'curso_id' => (int) $scenario['curso_id'],
            'docente_id' => (int) $scenario['docente']['id'],
            'periodo_id' => (int) $scenario['periodo_id'],
            'sede_id' => (int) $scenario['sede_id'],
            'aula_id' => (int) $scenario['aula_id'],
            'capacidad_max' => 20,
            'cupo_minimo' => 2,
        ], [1], ['08:00'], ['10:00']);

        $this->assertGreaterThan(0, $groupId);
        $this->assertMatchesRegularExpression('/^GRP-\d{4}-\d{5}$/', (string) Database::scalar(
            'SELECT codigo FROM grupos WHERE id = ?',
            [$groupId]
        ));
        $this->assertSame('20', (string) Database::scalar(
            'SELECT capacidad_max FROM grupos WHERE id = ?',
            [$groupId]
        ));
        $this->assertDatabaseCount('grupo_horarios', 1, "grupo_id = ? AND estado = 'activo'", [$groupId]);
        $this->assertDatabaseCount('auditoria', 1, "accion = 'grupo_creado' AND entidad_id = ?", [(string) $groupId]);
    }

    public function testGroupServiceValidatesContextAndCapacity(): void
    {
        $scenario = $this->academicScenario();

        $this->expectDomain('RN-03', fn () => GroupService::create([
            'curso_id' => (int) $scenario['curso_id'],
            'docente_id' => (int) $scenario['docente']['id'],
            'periodo_id' => (int) $scenario['periodo_id'],
            'sede_id' => (int) $scenario['sede_id'],
            'aula_id' => (int) $scenario['aula_id'],
            'capacidad_max' => 999,
            'cupo_minimo' => 1,
        ], [1], ['08:00'], ['10:00']), 'no puede superar');

        Database::update('periodos', ['estado' => 'cerrado'], ['id' => (int) $scenario['periodo_id']]);

        $this->expectDomain('RF-22', fn () => GroupService::create([
            'curso_id' => (int) $scenario['curso_id'],
            'docente_id' => (int) $scenario['docente']['id'],
            'periodo_id' => (int) $scenario['periodo_id'],
            'sede_id' => (int) $scenario['sede_id'],
            'aula_id' => (int) $scenario['aula_id'],
            'capacidad_max' => 10,
            'cupo_minimo' => 1,
        ], [1], ['08:00'], ['10:00']), 'periodo cerrado');
    }
}
