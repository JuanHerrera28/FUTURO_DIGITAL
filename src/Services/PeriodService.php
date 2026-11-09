<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\DomainException;
use App\Core\Param;

final class PeriodService
{
    /**
     * @param array<string,mixed> $filters
     * @return array{rows:list<array<string,mixed>>,total:int}
     */
    public static function paginate(array $filters, int $page = 1, int $perPage = 20): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['buscar'])) {
            $term = '%' . $filters['buscar'] . '%';
            $where[] = '(codigo LIKE ? OR nombre LIKE ?)';
            array_push($params, $term, $term);
        }

        if (!empty($filters['estado'])) {
            $where[] = 'estado = ?';
            $params[] = $filters['estado'];
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) Database::scalar("SELECT COUNT(*) FROM periodos WHERE {$whereSql}", $params);
        $offset = max(0, ($page - 1) * $perPage);

        $rows = Database::fetchAll(
            "SELECT p.*,
                    (SELECT COUNT(*) FROM matriculas m WHERE m.periodo_id = p.id AND m.estado = 'activa') AS matriculas,
                    (SELECT COUNT(*) FROM grupos g WHERE g.periodo_id = p.id AND g.estado = 'activo') AS grupos
             FROM periodos p
             WHERE {$whereSql}
             ORDER BY p.fecha_inicio DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::fetch('SELECT * FROM periodos WHERE id = ?', [$id]);
    }

    /** @param array<string,mixed> $data */
    public static function create(array $data): int
    {
        $codigo = mb_strtoupper(trim((string) ($data['codigo'] ?? '')));
        $nombre = trim((string) ($data['nombre'] ?? ''));
        $inicio = (string) ($data['fecha_inicio'] ?? '');
        $fin = (string) ($data['fecha_fin'] ?? '');
        $matriculaInicio = (string) ($data['matricula_inicio'] ?? '');
        $matriculaFin = (string) ($data['matricula_fin'] ?? '');

        if ($codigo === '' || $nombre === '') {
            throw new DomainException('RF-06', 'El código y el nombre del periodo son obligatorios.');
        }

        if ($fin <= $inicio) {
            throw new DomainException('RF-06', 'La fecha de fin del periodo debe ser posterior a la fecha de inicio.');
        }

        if ($matriculaFin < $matriculaInicio) {
            throw new DomainException('RF-06', 'La fecha de cierre de matrícula no puede ser anterior a la apertura.');
        }

        if ($matriculaFin > $fin) {
            throw new DomainException('RF-06', 'La matrícula debe cerrar antes del fin del periodo.');
        }

        if (Database::scalar('SELECT COUNT(*) FROM periodos WHERE codigo = ?', [$codigo]) > 0) {
            throw new DomainException('RF-06', 'Ya existe un periodo con el código ' . $codigo . '.');
        }

        if (Database::scalar('SELECT COUNT(*) FROM periodos WHERE nombre = ?', [$nombre]) > 0) {
            throw new DomainException('RF-06', 'Ya existe un periodo con el nombre ' . $nombre . '.');
        }

        $id = Database::insert('periodos', [
            'codigo' => $codigo,
            'nombre' => $nombre,
            'fecha_inicio' => $inicio,
            'fecha_fin' => $fin,
            'matricula_inicio' => $matriculaInicio,
            'matricula_fin' => $matriculaFin,
            'estado' => 'planificado',
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ]);

        Audit::log('periodo_creado', 'periodos', 'periodos', $id, null, [
            'codigo' => $codigo, 'nombre' => $nombre, 'fecha_inicio' => $inicio, 'fecha_fin' => $fin,
        ]);

        return $id;
    }

    /** @param array<string,mixed> $data */
    public static function update(int $id, array $data): void
    {
        $before = self::find($id);
        if ($before === null) {
            throw new DomainException('RF-06', 'El periodo no existe.');
        }

        if ((string) $before['estado'] === 'cerrado') {
            throw new DomainException('RF-22', 'Un periodo cerrado no puede modificarse.');
        }

        $inicio = (string) ($data['fecha_inicio'] ?? $before['fecha_inicio']);
        $fin = (string) ($data['fecha_fin'] ?? $before['fecha_fin']);
        $matriculaInicio = (string) ($data['matricula_inicio'] ?? $before['matricula_inicio']);
        $matriculaFin = (string) ($data['matricula_fin'] ?? $before['matricula_fin']);

        if ($fin <= $inicio) {
            throw new DomainException('RF-06', 'La fecha de fin debe ser posterior a la fecha de inicio.');
        }

        if ($matriculaFin < $matriculaInicio || $matriculaFin > $fin) {
            throw new DomainException('RF-06', 'Las fechas de matrícula no son coherentes con el periodo.');
        }

        Database::update('periodos', [
            'codigo' => mb_strtoupper(trim((string) ($data['codigo'] ?? $before['codigo']))),
            'nombre' => trim((string) ($data['nombre'] ?? $before['nombre'])),
            'fecha_inicio' => $inicio,
            'fecha_fin' => $fin,
            'matricula_inicio' => $matriculaInicio,
            'matricula_fin' => $matriculaFin,
            'updated_by' => Auth::id(),
        ], ['id' => $id]);

        Audit::log('periodo_actualizado', 'periodos', 'periodos', $id, $before, $data);
    }

    /**
     * Abre la matrícula del periodo. Solo un periodo puede tenerla abierta.
     * Si hay grupos por debajo del cupo mínimo, exige confirmación explícita.
     */
    public static function openEnrollment(int $id, bool $confirmLowGroups = false, ?string $note = null): array
    {
        return Database::transaction(static function () use ($id, $confirmLowGroups, $note): array {
            $period = Database::fetch('SELECT * FROM periodos WHERE id = ? FOR UPDATE', [$id]);

            if ($period === null) {
                throw new DomainException('RF-06', 'El periodo no existe.');
            }

            if ((string) $period['estado'] === 'cerrado') {
                throw new DomainException('RF-22', 'Un periodo cerrado no puede reabrirse.');
            }

            $otherOpen = Database::fetch(
                "SELECT id, nombre FROM periodos WHERE estado = 'matricula_abierta' AND id <> ? LIMIT 1",
                [$id]
            );

            if ($otherOpen !== null) {
                throw new DomainException('RF-06', 'Ya existe un periodo con matrícula abierta: ' . $otherOpen['nombre'] . '. Debe cerrarlo antes de abrir otro.');
            }

            $minimum = max(1, Param::int('cupo_minimo_grupo', 5));

            $lowGroups = Database::fetchAll(
                "SELECT g.codigo, c.nombre AS curso,
                        (SELECT COUNT(*) FROM inscripciones i WHERE i.grupo_id = g.id AND i.estado = 'activa') AS inscritos
                 FROM grupos g
                 JOIN cursos c ON c.id = g.curso_id
                 WHERE g.periodo_id = ? AND g.estado = 'activo'
                 HAVING inscritos < ?
                 ORDER BY c.nombre",
                [$id, $minimum]
            );

            if ($lowGroups !== [] && !$confirmLowGroups) {
                $detail = implode('; ', array_map(
                    static fn (array $group): string => $group['codigo'] . ' (' . $group['curso'] . ': ' . $group['inscritos'] . '/' . $minimum . ')',
                    $lowGroups
                ));

                throw new DomainException(
                    'RN-03',
                    'Hay grupos por debajo del cupo mínimo de ' . $minimum . ': ' . $detail
                    . '. Confirme la apertura para continuar; la decisión quedará auditada.'
                );
            }

            $before = $period['estado'];
            Database::update('periodos', [
                'estado' => 'matricula_abierta',
                'updated_by' => Auth::id(),
            ], ['id' => $id]);

            Audit::log('periodo_matricula_abierta', 'periodos', 'periodos', $id, ['estado' => $before], [
                'estado' => 'matricula_abierta',
                'grupos_bajo_cupo' => array_map(static fn (array $g): string => (string) $g['codigo'], $lowGroups),
                'confirmacion' => $confirmLowGroups,
                'observacion' => $note,
            ]);

            return ['period' => $period, 'lowGroups' => $lowGroups];
        });
    }

    public static function start(int $id): void
    {
        $period = self::find($id);
        if ($period === null) {
            throw new DomainException('RF-06', 'El periodo no existe.');
        }

        if (!in_array((string) $period['estado'], ['matricula_abierta', 'planificado'], true)) {
            throw new DomainException('RF-06', 'El periodo no puede pasar a curso en su estado actual.');
        }

        Database::update('periodos', ['estado' => 'en_curso', 'updated_by' => Auth::id()], ['id' => $id]);
        Audit::log('periodo_en_curso', 'periodos', 'periodos', $id, ['estado' => $period['estado']], ['estado' => 'en_curso']);
    }

    /**
     * Cierra el periodo consolidando notas finales y bloqueando calificaciones.
     *
     * @return array{inscripciones:int,periodo:string}
     */
    public static function close(int $id, bool $force = false): array
    {
        return Database::transaction(static function () use ($id, $force): array {
            $period = Database::fetch('SELECT * FROM periodos WHERE id = ? FOR UPDATE', [$id]);

            if ($period === null) {
                throw new DomainException('RF-22', 'El periodo no existe.');
            }

            if ((string) $period['estado'] === 'cerrado') {
                throw new DomainException('RF-22', 'El periodo ya está cerrado.');
            }

            $groups = Database::fetchAll(
                "SELECT DISTINCT g.id, g.codigo
                 FROM grupos g
                 JOIN inscripciones i ON i.grupo_id = g.id AND i.estado = 'activa'
                 WHERE g.periodo_id = ?",
                [$id]
            );

            foreach ($groups as $group) {
                $weight = (float) Database::scalar(
                    "SELECT COALESCE(SUM(peso), 0) FROM evaluaciones WHERE grupo_id = ? AND estado = 'activa'",
                    [(int) $group['id']]
                );

                if ($weight > 0 && abs($weight - 100.0) > 0.01 && !$force) {
                    throw new DomainException(
                        'RN-13',
                        'El grupo ' . $group['codigo'] . ' tiene evaluaciones que suman ' . number_format($weight, 1, ',', '.') . ' %. Ajuste los pesos a 100 % o confirme el cierre forzado.'
                    );
                }
            }

            $inscriptions = Database::fetchAll(
                "SELECT i.id, i.estado
                 FROM inscripciones i
                 JOIN grupos g ON g.id = i.grupo_id
                 WHERE g.periodo_id = ? AND i.estado IN ('activa', 'perdida_inasistencia')",
                [$id]
            );

            foreach ($inscriptions as $inscription) {
                $final = GradeService::finalForInscription((int) $inscription['id']);

                if ((string) $inscription['estado'] === 'perdida_inasistencia') {
                    Database::update('inscripciones', [
                        'nota_final' => 0.0,
                        'estado' => 'perdida_inasistencia',
                    ], ['id' => (int) $inscription['id']]);
                    continue;
                }

                Database::update('inscripciones', [
                    'nota_final' => $final['nota'],
                    'estado' => 'finalizada',
                    'updated_by' => Auth::id(),
                ], ['id' => (int) $inscription['id']]);
            }

            Database::execute(
                "UPDATE matriculas SET estado = 'finalizada', updated_by = ? WHERE periodo_id = ? AND estado = 'activa'",
                [Auth::id(), $id]
            );

            Database::update('periodos', [
                'estado' => 'cerrado',
                'updated_by' => Auth::id(),
            ], ['id' => $id]);

            Audit::log('periodo_cerrado', 'periodos', 'periodos', $id, ['estado' => $period['estado']], [
                'estado' => 'cerrado',
                'inscripciones_consolidadas' => count($inscriptions),
                'forzado' => $force,
            ]);

            return ['inscripciones' => count($inscriptions), 'periodo' => (string) $period['nombre']];
        });
    }

    /** @return list<array<string,mixed>> */
    public static function options(): array
    {
        return Database::fetchAll('SELECT id, codigo, nombre, estado, fecha_inicio, fecha_fin, matricula_inicio, matricula_fin FROM periodos ORDER BY fecha_inicio DESC');
    }

    /** @return array<string,mixed>|null */
    public static function open(): ?array
    {
        return Database::fetch("SELECT * FROM periodos WHERE estado = 'matricula_abierta' LIMIT 1");
    }

    /** @return list<array<string,mixed>> */
    public static function groupsBelowMinimum(int $periodId): array
    {
        $minimum = max(1, Param::int('cupo_minimo_grupo', 5));

        return Database::fetchAll(
            "SELECT g.codigo, c.nombre AS curso,
                    (SELECT COUNT(*) FROM inscripciones i WHERE i.grupo_id = g.id AND i.estado = 'activa') AS inscritos
             FROM grupos g
             JOIN cursos c ON c.id = g.curso_id
             WHERE g.periodo_id = ? AND g.estado = 'activo'
             HAVING inscritos < ?
             ORDER BY c.nombre",
            [$periodId, $minimum]
        );
    }

    public static function assertEnrollmentOpen(int $periodId): void
    {
        $period = Database::fetch('SELECT estado, nombre, matricula_inicio, matricula_fin FROM periodos WHERE id = ?', [$periodId]);

        if ($period === null) {
            throw new DomainException('RN-09', 'El periodo académico no existe.');
        }

        if ((string) $period['estado'] !== 'matricula_abierta') {
            throw new DomainException('RN-09', 'El periodo ' . $period['nombre'] . ' no tiene la matrícula abierta.');
        }
    }
}
