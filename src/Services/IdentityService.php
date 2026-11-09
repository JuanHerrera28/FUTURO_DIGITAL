<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\DomainException;
use App\Core\HttpException;

/**
 * Resuelve la ficha de estudiante o docente asociada al usuario autenticado.
 */
final class IdentityService
{
    /** @return array<string,mixed>|null */
    public static function docente(): ?array
    {
        if (!Auth::check()) {
            return null;
        }

        $personaId = (int) (Auth::user()['persona_id'] ?? 0);

        return Database::fetch('SELECT * FROM docentes WHERE persona_id = ? LIMIT 1', [$personaId]);
    }

    public static function docenteId(): ?int
    {
        $docente = self::docente();

        return $docente === null ? null : (int) $docente['id'];
    }

    public static function requireDocente(): int
    {
        $id = self::docenteId();

        if ($id === null) {
            throw new DomainException('RN-12', 'El usuario autenticado no está registrado como docente.');
        }

        return $id;
    }

    /** @return array<string,mixed>|null */
    public static function estudiante(): ?array
    {
        if (!Auth::check()) {
            return null;
        }

        $personaId = (int) (Auth::user()['persona_id'] ?? 0);

        return Database::fetch('SELECT * FROM estudiantes WHERE persona_id = ? LIMIT 1', [$personaId]);
    }

    public static function estudianteId(): ?int
    {
        $estudiante = self::estudiante();

        return $estudiante === null ? null : (int) $estudiante['id'];
    }

    public static function requireEstudiante(): int
    {
        $id = self::estudianteId();

        if ($id === null) {
            throw new HttpException(403, 'El usuario autenticado no está registrado como estudiante.');
        }

        return $id;
    }

    /**
     * RN-12: solo el docente asignado puede calificar o tomar asistencia.
     */
    public static function assertOwnsGroup(int $grupoId): void
    {
        $docenteId = self::docenteId();

        if ($docenteId === null) {
            throw new DomainException('RN-12', 'Solo el docente asignado puede realizar esta acción.');
        }

        $owner = (int) Database::scalar('SELECT docente_id FROM grupos WHERE id = ?', [$grupoId]);

        if ($owner !== $docenteId) {
            throw new DomainException('RN-12', 'Solo el docente asignado al grupo puede calificar o registrar asistencia.');
        }
    }

    /**
     * Verifica que el grupo exista y pertenezca al docente autenticado.
     *
     * @return array<string,mixed>
     */
    public static function ownedGroup(int $grupoId): array
    {
        self::assertOwnsGroup($grupoId);

        $group = Database::fetch('SELECT * FROM grupos WHERE id = ?', [$grupoId]);

        if ($group === null) {
            throw new DomainException('RF-07', 'El grupo no existe.');
        }

        return $group;
    }
}
