<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\DomainException;

final class PersonService
{
    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        return Database::fetch(
            'SELECT p.*, td.nombre AS tipo_documento, td.codigo AS tipo_documento_codigo
             FROM personas p
             JOIN documento_tipos td ON td.id = p.tipo_documento_id
             WHERE p.id = ?',
            [$id]
        );
    }

    /** @return list<array<string,mixed>> */
    public static function documentTypes(): array
    {
        return Database::fetchAll('SELECT * FROM documento_tipos WHERE estado = ? ORDER BY id', ['activo']);
    }

    /** @return list<array<string,mixed>> */
    public static function sedes(): array
    {
        return Database::fetchAll('SELECT * FROM sedes WHERE estado = ? ORDER BY nombre', ['activo']);
    }

    public static function assertDocumentUnique(int $tipoDocumentoId, string $numeroDocumento, ?int $excludePersonaId = null): void
    {
        $exists = Database::fetch(
            'SELECT id FROM personas WHERE tipo_documento_id = ? AND numero_documento = ? AND id <> ? LIMIT 1',
            [$tipoDocumentoId, $numeroDocumento, $excludePersonaId ?? 0]
        );

        if ($exists !== null) {
            throw new DomainException('RN-01', 'Ya existe una persona registrada con ese tipo y número de documento. La unicidad es global para ambas sedes.');
        }
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function create(array $data): int
    {
        $tipoId = (int) ($data['tipo_documento_id'] ?? 0);
        $numero = normalizar_documento((string) ($data['numero_documento'] ?? ''));

        if ($numero === '') {
            throw new DomainException('RN-01', 'El número de documento es obligatorio.');
        }

        $tipo = Database::fetch('SELECT id FROM documento_tipos WHERE id = ? AND estado = ?', [$tipoId, 'activo']);
        if ($tipo === null) {
            throw new DomainException('RN-01', 'El tipo de documento seleccionado no es válido.');
        }

        self::assertDocumentUnique($tipoId, $numero);

        $autoriza = !empty($data['autorizacion_datos']) ? 1 : 0;

        return Database::insert('personas', [
            'tipo_documento_id' => $tipoId,
            'numero_documento' => $numero,
            'nombres' => trim((string) ($data['nombres'] ?? '')),
            'apellidos' => trim((string) ($data['apellidos'] ?? '')),
            'fecha_nacimiento' => ($data['fecha_nacimiento'] ?? '') !== '' ? $data['fecha_nacimiento'] : null,
            'email' => ($data['email'] ?? '') !== '' ? mb_strtolower(trim((string) $data['email'])) : null,
            'telefono' => ($data['telefono'] ?? '') !== '' ? trim((string) $data['telefono']) : null,
            'direccion' => ($data['direccion'] ?? '') !== '' ? trim((string) $data['direccion']) : null,
            'autorizacion_datos' => $autoriza,
            'fecha_autorizacion' => $autoriza ? ($data['fecha_autorizacion'] ?? date('Y-m-d')) : null,
            'medio_autorizacion' => $autoriza ? (($data['medio_autorizacion'] ?? '') ?: 'Formulario físico') : null,
        ]);
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function update(int $personaId, array $data): void
    {
        $persona = self::find($personaId);
        if ($persona === null) {
            throw new DomainException('RN-01', 'La persona no existe.');
        }

        $tipoId = (int) ($data['tipo_documento_id'] ?? $persona['tipo_documento_id']);
        $numero = normalizar_documento((string) ($data['numero_documento'] ?? $persona['numero_documento']));

        self::assertDocumentUnique($tipoId, $numero, $personaId);

        $autoriza = !empty($data['autorizacion_datos']) ? 1 : 0;

        Database::update('personas', [
            'tipo_documento_id' => $tipoId,
            'numero_documento' => $numero,
            'nombres' => trim((string) ($data['nombres'] ?? $persona['nombres'])),
            'apellidos' => trim((string) ($data['apellidos'] ?? $persona['apellidos'])),
            'fecha_nacimiento' => ($data['fecha_nacimiento'] ?? '') !== '' ? $data['fecha_nacimiento'] : null,
            'email' => ($data['email'] ?? '') !== '' ? mb_strtolower(trim((string) $data['email'])) : null,
            'telefono' => ($data['telefono'] ?? '') !== '' ? trim((string) $data['telefono']) : null,
            'direccion' => ($data['direccion'] ?? '') !== '' ? trim((string) $data['direccion']) : null,
            'autorizacion_datos' => $autoriza,
            'fecha_autorizacion' => $autoriza ? ($data['fecha_autorizacion'] ?? $persona['fecha_autorizacion'] ?? date('Y-m-d')) : null,
            'medio_autorizacion' => $autoriza ? (($data['medio_autorizacion'] ?? '') ?: ($persona['medio_autorizacion'] ?? 'Formulario físico')) : null,
        ], ['id' => $personaId]);
    }
}
