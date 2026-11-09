<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class PortalService
{
    /**
     * Consolida la información que ve el estudiante en su portal.
     *
     * @return array<string,mixed>
     */
    public static function dashboard(int $estudianteId): array
    {
        $student = Database::fetch(
            'SELECT e.*, p.nombres, p.apellidos, p.numero_documento, p.email, p.telefono,
                    p.direccion, p.fecha_nacimiento, td.nombre AS tipo_documento,
                    s.nombre AS sede
             FROM estudiantes e
             JOIN personas p ON p.id = e.persona_id
             JOIN documento_tipos td ON td.id = p.tipo_documento_id
             JOIN sedes s ON s.id = e.sede_id
             WHERE e.id = ?',
            [$estudianteId]
        );

        if ($student === null) {
            return [];
        }

        $matricula = Database::fetch(
            "SELECT m.*, pr.nombre AS programa, pe.nombre AS periodo, pe.estado AS periodo_estado,
                    pe.fecha_inicio, pe.fecha_fin
             FROM matriculas m
             JOIN programas pr ON pr.id = m.programa_id
             JOIN periodos pe ON pe.id = m.periodo_id
             WHERE m.estudiante_id = ? AND m.estado = 'activa'
             ORDER BY CASE pe.estado WHEN 'en_curso' THEN 0 WHEN 'matricula_abierta' THEN 1 ELSE 2 END,
                      pe.fecha_inicio DESC
             LIMIT 1",
            [$estudianteId]
        );

        $grades = GradeService::studentGrades($estudianteId);
        $attendance = AttendanceService::studentSummary($estudianteId);
        $statement = PaymentService::statement($estudianteId);

        $schedule = Database::fetchAll(
            "SELECT gh.dia_semana, gh.hora_inicio, gh.hora_fin,
                    c.nombre AS curso, c.codigo AS curso_codigo,
                    g.codigo AS grupo, a.nombre AS aula,
                    CONCAT_WS(' ', pd.nombres, pd.apellidos) AS docente
             FROM grupo_horarios gh
             JOIN grupos g ON g.id = gh.grupo_id
             JOIN cursos c ON c.id = g.curso_id
             JOIN aulas a ON a.id = g.aula_id
             JOIN docentes d ON d.id = g.docente_id
             JOIN personas pd ON pd.id = d.persona_id
             JOIN inscripciones i ON i.grupo_id = g.id AND i.estado = 'activa'
             JOIN matriculas m ON m.id = i.matricula_id
             WHERE m.estudiante_id = ? AND gh.estado = 'activo'
             ORDER BY gh.dia_semana, gh.hora_inicio",
            [$estudianteId]
        );

        $alerts = Database::fetchAll(
            "SELECT * FROM alertas WHERE estudiante_id = ? ORDER BY created_at DESC LIMIT 10",
            [$estudianteId]
        );

        $guardians = Database::fetchAll(
            "SELECT * FROM acudientes WHERE estudiante_id = ? AND estado = 'activo' ORDER BY nombre",
            [$estudianteId]
        );

        return [
            'student' => $student,
            'matricula' => $matricula,
            'grades' => $grades,
            'attendance' => $attendance,
            'statement' => $statement,
            'schedule' => $schedule,
            'alerts' => $alerts,
            'guardians' => $guardians,
        ];
    }

    /** @return list<array<string,mixed>> */
    public static function studentOptions(): array
    {
        return Database::fetchAll(
            "SELECT e.id, e.codigo, p.nombres, p.apellidos, p.numero_documento
             FROM estudiantes e JOIN personas p ON p.id = e.persona_id
             WHERE e.estado = 'activo'
             ORDER BY p.apellidos, p.nombres
             LIMIT 500"
        );
    }
}
