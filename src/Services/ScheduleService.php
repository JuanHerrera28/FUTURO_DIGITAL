<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\DomainException;

/**
 * Validación de horarios. Un bloque se compone de día (1-7), hora inicio
 * y hora fin. Se comparan cruces de docente, aula y estudiante.
 */
final class ScheduleService
{
    /**
     * @param array<int,mixed> $days
     * @param array<int,mixed> $starts
     * @param array<int,mixed> $ends
     * @return list<array{dia_semana:int,hora_inicio:string,hora_fin:string}>
     */
    public static function fromRequest(array $days, array $starts, array $ends): array
    {
        $blocks = [];
        $count = max(count($days), count($starts), count($ends));

        for ($i = 0; $i < $count; $i++) {
            $day = (int) ($days[$i] ?? 0);
            $start = trim((string) ($starts[$i] ?? ''));
            $end = trim((string) ($ends[$i] ?? ''));

            if ($day === 0 && $start === '' && $end === '') {
                continue;
            }

            if ($day < 1 || $day > 7) {
                throw new DomainException('RF-07', 'Debe seleccionar un día válido para cada bloque de horario.');
            }

            if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $start) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $end)) {
                throw new DomainException('RF-07', 'Las horas de inicio y fin deben tener el formato HH:MM.');
            }

            $start = strlen($start) === 5 ? $start . ':00' : $start;
            $end = strlen($end) === 5 ? $end . ':00' : $end;

            if ($end <= $start) {
                throw new DomainException('RF-07', 'La hora de fin debe ser posterior a la hora de inicio en cada bloque.');
            }

            $blocks[] = [
                'dia_semana' => $day,
                'hora_inicio' => $start,
                'hora_fin' => $end,
            ];
        }

        if ($blocks === []) {
            throw new DomainException('RF-07', 'Debe registrar al menos un bloque de horario para el grupo.');
        }

        self::assertNoSelfOverlap($blocks);

        return $blocks;
    }

    /** @param list<array{dia_semana:int,hora_inicio:string,hora_fin:string}> $blocks */
    public static function assertNoSelfOverlap(array $blocks): void
    {
        $count = count($blocks);

        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                if (
                    $blocks[$i]['dia_semana'] === $blocks[$j]['dia_semana']
                    && $blocks[$i]['hora_inicio'] < $blocks[$j]['hora_fin']
                    && $blocks[$i]['hora_fin'] > $blocks[$j]['hora_inicio']
                ) {
                    throw new DomainException('RF-07', 'Los bloques de horario del mismo grupo se cruzan el ' . self::dayName($blocks[$i]['dia_semana']) . '.');
                }
            }
        }
    }

    /**
     * @param list<array{dia_semana:int,hora_inicio:string,hora_fin:string}> $blocks
     */
    public static function assertTeacherFree(int $docenteId, int $periodoId, array $blocks, ?int $excludeGroupId = null): void
    {
        [$start, $end] = self::periodRange($periodoId);

        foreach ($blocks as $block) {
            $conflict = Database::fetch(
                "SELECT g.codigo, c.nombre AS curso, gh.dia_semana, gh.hora_inicio, gh.hora_fin
                 FROM grupo_horarios gh
                 JOIN grupos g ON g.id = gh.grupo_id
                 JOIN cursos c ON c.id = g.curso_id
                 JOIN periodos p2 ON p2.id = g.periodo_id
                 WHERE gh.estado = 'activo'
                   AND g.estado = 'activo'
                   AND g.docente_id = ?
                   AND g.id <> ?
                   AND p2.fecha_inicio <= ?
                   AND p2.fecha_fin >= ?
                   AND gh.dia_semana = ?
                   AND gh.hora_inicio < ?
                   AND gh.hora_fin > ?
                 LIMIT 1",
                [$docenteId, $excludeGroupId ?? 0, $end, $start, $block['dia_semana'], $block['hora_fin'], $block['hora_inicio']]
            );

            if ($conflict !== null) {
                throw new DomainException(
                    'RN-06',
                    'El docente ya tiene el grupo ' . $conflict['codigo'] . ' (' . $conflict['curso'] . ') el '
                    . self::dayName((int) $conflict['dia_semana']) . ' de ' . hora($conflict['hora_inicio'])
                    . ' a ' . hora($conflict['hora_fin']) . '.'
                );
            }
        }
    }

    /**
     * @param list<array{dia_semana:int,hora_inicio:string,hora_fin:string}> $blocks
     */
    public static function assertClassroomFree(int $aulaId, int $periodoId, array $blocks, ?int $excludeGroupId = null): void
    {
        [$start, $end] = self::periodRange($periodoId);

        foreach ($blocks as $block) {
            $conflict = Database::fetch(
                "SELECT g.codigo, c.nombre AS curso, gh.dia_semana, gh.hora_inicio, gh.hora_fin
                 FROM grupo_horarios gh
                 JOIN grupos g ON g.id = gh.grupo_id
                 JOIN cursos c ON c.id = g.curso_id
                 JOIN periodos p2 ON p2.id = g.periodo_id
                 WHERE gh.estado = 'activo'
                   AND g.estado = 'activo'
                   AND g.aula_id = ?
                   AND g.id <> ?
                   AND p2.fecha_inicio <= ?
                   AND p2.fecha_fin >= ?
                   AND gh.dia_semana = ?
                   AND gh.hora_inicio < ?
                   AND gh.hora_fin > ?
                 LIMIT 1",
                [$aulaId, $excludeGroupId ?? 0, $end, $start, $block['dia_semana'], $block['hora_fin'], $block['hora_inicio']]
            );

            if ($conflict !== null) {
                throw new DomainException(
                    'RN-06',
                    'El aula ya está ocupada por el grupo ' . $conflict['codigo'] . ' (' . $conflict['curso'] . ') el '
                    . self::dayName((int) $conflict['dia_semana']) . ' de ' . hora($conflict['hora_inicio'])
                    . ' a ' . hora($conflict['hora_fin']) . '.'
                );
            }
        }
    }

    /**
     * @param list<array{dia_semana:int,hora_inicio:string,hora_fin:string}> $blocks
     */
    public static function assertStudentFree(int $matriculaId, int $periodoId, array $blocks, ?int $excludeGroupId = null): void
    {
        [$start, $end] = self::periodRange($periodoId);

        foreach ($blocks as $block) {
            $conflict = Database::fetch(
                "SELECT g.id, g.codigo, c.nombre AS curso, gh.dia_semana, gh.hora_inicio, gh.hora_fin
                 FROM grupo_horarios gh
                 JOIN grupos g ON g.id = gh.grupo_id
                 JOIN cursos c ON c.id = g.curso_id
                 JOIN inscripciones i ON i.grupo_id = g.id AND i.estado = 'activa'
                 JOIN periodos p2 ON p2.id = g.periodo_id
                 WHERE i.matricula_id = ?
                   AND g.estado = 'activo'
                   AND g.id <> ?
                   AND p2.fecha_inicio <= ?
                   AND p2.fecha_fin >= ?
                   AND gh.dia_semana = ?
                   AND gh.hora_inicio < ?
                   AND gh.hora_fin > ?
                 LIMIT 1",
                [$matriculaId, $excludeGroupId ?? 0, $end, $start, $block['dia_semana'], $block['hora_fin'], $block['hora_inicio']]
            );

            if ($conflict !== null) {
                throw new DomainException(
                    'RN-05',
                    'El estudiante se cruza con el grupo ' . $conflict['codigo'] . ' (' . $conflict['curso'] . ') el '
                    . self::dayName((int) $conflict['dia_semana']) . ' de ' . hora($conflict['hora_inicio'])
                    . ' a ' . hora($conflict['hora_fin']) . '.'
                );
            }
        }
    }

    /** @return array{0:string,1:string} */
    private static function periodRange(int $periodoId): array
    {
        $period = Database::fetch('SELECT fecha_inicio, fecha_fin FROM periodos WHERE id = ?', [$periodoId]);

        if ($period === null) {
            throw new DomainException('RF-06', 'El periodo académico no existe.');
        }

        return [(string) $period['fecha_inicio'], (string) $period['fecha_fin']];
    }

    public static function dayName(int $day): string
    {
        return dias_semana()[$day] ?? ('Día ' . $day);
    }
}
