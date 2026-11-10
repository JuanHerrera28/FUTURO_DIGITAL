<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Services\EnrollmentService;

/**
 * Construye un escenario académico coherente (programa, plan, periodo, grupo,
 * docente, estudiante y matrícula) para las pruebas de integración.
 */
trait AcademicScenario
{
    /**
     * @param array<string,mixed> $options
     * @return array{
     *     sede_id:int,periodo_id:int,programa_id:int,curso_id:int,
     *     docente:array<string,mixed>,aula_id:int,grupo_id:int,
     *     estudiante:array<string,mixed>,matricula_id:?int
     * }
     */
    protected function academicScenario(array $options = []): array
    {
        $sedeId = (int) ($options['sede_id'] ?? 1);
        $periodoId = (int) ($options['periodo_id'] ?? Fixtures::periodo());
        $programaId = (int) ($options['programa_id'] ?? Fixtures::programa());
        $cursoId = (int) ($options['curso_id'] ?? Fixtures::curso());

        if (($options['plan'] ?? true) === true) {
            Fixtures::plan($programaId, $cursoId);
        }

        $docente = $options['docente'] ?? Fixtures::docente($sedeId, true);
        $aulaId = (int) ($options['aula_id'] ?? Fixtures::aula($sedeId));
        $grupoId = (int) ($options['grupo_id'] ?? Fixtures::grupo(
            $cursoId,
            (int) $docente['id'],
            $periodoId,
            $sedeId,
            $aulaId,
            (int) ($options['capacidad'] ?? 30),
            (array) ($options['horarios'] ?? [])
        ));

        $estudiante = $options['estudiante'] ?? Fixtures::estudiante($sedeId);
        $matriculaId = ($options['matricula'] ?? true) === false
            ? null
            : (int) ($options['matricula_id'] ?? Fixtures::matricula(
                (int) $estudiante['id'],
                $programaId,
                $periodoId,
                $sedeId
            ));

        return [
            'sede_id' => $sedeId,
            'periodo_id' => $periodoId,
            'programa_id' => $programaId,
            'curso_id' => $cursoId,
            'docente' => $docente,
            'aula_id' => $aulaId,
            'grupo_id' => $grupoId,
            'estudiante' => $estudiante,
            'matricula_id' => $matriculaId,
        ];
    }

    /**
     * Crea un grupo adicional dentro del mismo periodo, sede y programa.
     *
     * @param array<string,mixed> $scenario
     * @param list<array{dia_semana:int,hora_inicio:string,hora_fin:string}> $horarios
     */
    protected function addGroup(array $scenario, ?int $cursoId = null, array $horarios = [], int $capacidad = 30): int
    {
        $cursoId ??= (int) $scenario['curso_id'];

        return Fixtures::grupo(
            $cursoId,
            (int) $scenario['docente']['id'],
            (int) $scenario['periodo_id'],
            (int) $scenario['sede_id'],
            (int) $scenario['aula_id'],
            $capacidad,
            $horarios
        );
    }

    /**
     * @param array<string,mixed> $scenario
     * @return array{inscripcion_id:int,grupo:string}
     */
    protected function enrollInScenario(array $scenario): array
    {
        return EnrollmentService::enroll((int) $scenario['matricula_id'], (int) $scenario['grupo_id']);
    }

    /** @param array<string,mixed> $scenario */
    protected function loginTeacher(array $scenario): void
    {
        $userId = $scenario['docente']['user_id'] ?? null;

        if ($userId === null) {
            throw new \RuntimeException('El docente del escenario no tiene usuario.');
        }

        AuthContext::login((int) $userId);
    }
}
