<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Database;

/**
 * Constructores de datos de prueba. Cada llamada genera códigos y documentos
 * únicos dentro del proceso para no chocar con la semilla ni entre pruebas.
 */
final class Fixtures
{
    private static int $sequence = 0;

    private static function next(): int
    {
        self::$sequence++;

        return self::$sequence;
    }

    private static function code(string $prefix): string
    {
        return $prefix . '-' . self::next() . '-' . random_int(1000, 9999);
    }

    /** @param array<string,mixed> $overrides */
    public static function persona(array $overrides = []): int
    {
        $sequence = self::next();

        return Database::insert('personas', array_merge([
            'tipo_documento_id' => 3,
            'numero_documento' => '9' . str_pad((string) $sequence, 9, '0', STR_PAD_LEFT),
            'nombres' => 'Prueba',
            'apellidos' => 'Persona ' . $sequence,
            'email' => 'persona' . $sequence . '@example.test',
            'telefono' => '3000000000',
            'autorizacion_datos' => 1,
            'fecha_autorizacion' => date('Y-m-d'),
        ], $overrides));
    }

    /**
     * @param array<string,mixed> $overrides
     * @return array{id:int,persona_id:int}
     */
    public static function estudiante(int $sedeId = 1, array $overrides = []): array
    {
        $personaId = self::persona((array) ($overrides['persona'] ?? []));

        $id = Database::insert('estudiantes', array_merge([
            'persona_id' => $personaId,
            'sede_id' => $sedeId,
            'codigo' => self::code('EST'),
            'estado' => 'activo',
        ], array_diff_key($overrides, ['persona' => true])));

        return ['id' => $id, 'persona_id' => $personaId];
    }

    /**
     * @param array<string,mixed> $overrides
     * @return array{id:int,persona_id:int,user_id:?int,username:?string,password:?string}
     */
    public static function docente(int $sedeId = 1, bool $withUser = false, array $overrides = []): array
    {
        $personaId = self::persona((array) ($overrides['persona'] ?? []));
        $documento = (string) Database::scalar('SELECT numero_documento FROM personas WHERE id = ?', [$personaId]);

        $id = Database::insert('docentes', array_merge([
            'persona_id' => $personaId,
            'sede_id' => $sedeId,
            'codigo' => self::code('DOC'),
            'titulo' => 'Magíster',
            'tipo_vinculacion' => 'catedra',
            'estado' => 'activo',
        ], array_diff_key($overrides, ['persona' => true])));

        $userId = null;
        $username = null;
        $password = null;

        if ($withUser) {
            $username = $documento;
            $password = 'Demo123*';
            $userId = self::usuario($personaId, 6, $sedeId, $username, $password);
        }

        return [
            'id' => $id,
            'persona_id' => $personaId,
            'user_id' => $userId,
            'username' => $username,
            'password' => $password,
        ];
    }

    public static function usuario(
        int $personaId,
        int $rolId,
        ?int $sedeId,
        string $username,
        string $password = 'Demo123*',
        int $debeCambiar = 0
    ): int {
        return Database::insert('usuarios', [
            'persona_id' => $personaId,
            'rol_id' => $rolId,
            'sede_id' => $sedeId,
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'debe_cambiar_password' => $debeCambiar,
            'estado' => 'activo',
        ]);
    }

    /** @param array<string,mixed> $overrides */
    public static function programa(array $overrides = []): int
    {
        return Database::insert('programas', array_merge([
            'codigo' => self::code('PRG'),
            'nombre' => 'Programa de prueba ' . self::$sequence,
            'duracion_periodos' => 2,
            'estado' => 'activo',
        ], $overrides));
    }

    /** @param array<string,mixed> $overrides */
    public static function curso(array $overrides = []): int
    {
        return Database::insert('cursos', array_merge([
            'codigo' => self::code('CUR'),
            'nombre' => 'Curso de prueba ' . self::$sequence,
            'estado' => 'activo',
        ], $overrides));
    }

    public static function plan(int $programaId, int $cursoId, int $nivel = 1): int
    {
        return Database::insert('plan_estudios', [
            'programa_id' => $programaId,
            'curso_id' => $cursoId,
            'nivel' => $nivel,
            'obligatorio' => 1,
            'estado' => 'activo',
        ]);
    }

    /** @param array<string,mixed> $overrides */
    public static function periodo(array $overrides = []): int
    {
        $sequence = self::next();

        return Database::insert('periodos', array_merge([
            'codigo' => self::code('PER'),
            'nombre' => 'Periodo de prueba ' . $sequence,
            'fecha_inicio' => date('Y-m-d', strtotime('-10 days')),
            'fecha_fin' => date('Y-m-d', strtotime('+100 days')),
            'matricula_inicio' => date('Y-m-d', strtotime('-30 days')),
            'matricula_fin' => date('Y-m-d', strtotime('+30 days')),
            'estado' => 'matricula_abierta',
        ], $overrides));
    }

    public static function aula(int $sedeId = 1, int $capacidad = 40): int
    {
        return Database::insert('aulas', [
            'sede_id' => $sedeId,
            'nombre' => self::code('Aula'),
            'capacidad' => $capacidad,
            'estado' => 'activo',
        ]);
    }

    /**
     * @param list<array{dia_semana:int,hora_inicio:string,hora_fin:string}> $horarios
     */
    public static function grupo(
        int $cursoId,
        int $docenteId,
        int $periodoId,
        int $sedeId,
        int $aulaId,
        int $capacidad = 30,
        array $horarios = []
    ): int {
        $id = Database::insert('grupos', [
            'codigo' => self::code('GRP'),
            'curso_id' => $cursoId,
            'docente_id' => $docenteId,
            'periodo_id' => $periodoId,
            'sede_id' => $sedeId,
            'aula_id' => $aulaId,
            'capacidad_max' => $capacidad,
            'cupo_minimo' => 1,
            'estado' => 'activo',
        ]);

        foreach ($horarios as $horario) {
            Database::insert('grupo_horarios', [
                'grupo_id' => $id,
                'dia_semana' => $horario['dia_semana'],
                'hora_inicio' => $horario['hora_inicio'],
                'hora_fin' => $horario['hora_fin'],
                'estado' => 'activo',
            ]);
        }

        return $id;
    }

    public static function matricula(
        int $estudianteId,
        int $programaId,
        int $periodoId,
        int $sedeId = 1,
        string $estado = 'activa'
    ): int {
        return Database::insert('matriculas', [
            'codigo' => self::code('MAT'),
            'estudiante_id' => $estudianteId,
            'programa_id' => $programaId,
            'periodo_id' => $periodoId,
            'sede_id' => $sedeId,
            'fecha' => date('Y-m-d H:i:s'),
            'estado' => $estado,
        ]);
    }

    public static function inscripcion(int $matriculaId, int $grupoId, string $estado = 'activa'): int
    {
        return Database::insert('inscripciones', [
            'matricula_id' => $matriculaId,
            'grupo_id' => $grupoId,
            'estado' => $estado,
            'fecha_inscripcion' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function cargo(
        int $estudianteId,
        float $valor,
        ?int $matriculaId = null,
        ?string $vencimiento = null,
        int $conceptoId = 1,
        string $estado = 'pendiente'
    ): int {
        return Database::insert('cargos', [
            'estudiante_id' => $estudianteId,
            'matricula_id' => $matriculaId,
            'concepto_id' => $conceptoId,
            'descripcion' => 'Cargo de prueba',
            'valor_total' => round($valor, 2),
            'fecha_emision' => date('Y-m-d'),
            'fecha_vencimiento' => $vencimiento ?? date('Y-m-d', strtotime('+15 days')),
            'estado' => $estado,
        ]);
    }

    public static function evaluacion(int $grupoId, string $nombre, float $peso, string $estado = 'activa'): int
    {
        return Database::insert('evaluaciones', [
            'grupo_id' => $grupoId,
            'nombre' => $nombre,
            'peso' => round($peso, 2),
            'fecha' => date('Y-m-d'),
            'estado' => $estado,
        ]);
    }

    public static function sesion(
        int $grupoId,
        string $fecha,
        string $horaInicio = '08:00:00',
        string $horaFin = '10:00:00',
        string $estado = 'cerrada'
    ): int {
        return Database::insert('sesiones', [
            'grupo_id' => $grupoId,
            'fecha' => $fecha,
            'hora_inicio' => $horaInicio,
            'hora_fin' => $horaFin,
            'tema' => 'Sesión de prueba',
            'estado' => $estado,
        ]);
    }

    public static function asistencia(int $sesionId, int $inscripcionId, string $estado): int
    {
        return Database::insert('asistencias', [
            'sesion_id' => $sesionId,
            'inscripcion_id' => $inscripcionId,
            'estado' => $estado,
        ]);
    }
}
