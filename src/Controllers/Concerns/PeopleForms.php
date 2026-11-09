<?php

declare(strict_types=1);

namespace App\Controllers\Concerns;

use App\Core\Request;

/**
 * Construye el arreglo de datos de persona a partir del formulario.
 * Los servicios de dominio realizan la validación definitiva.
 */
trait PeopleForms
{
    /** @return array<string,mixed> */
    protected function personData(): array
    {
        return [
            'tipo_documento_id' => Request::int('tipo_documento_id'),
            'numero_documento' => Request::string('numero_documento'),
            'nombres' => Request::string('nombres'),
            'apellidos' => Request::string('apellidos'),
            'fecha_nacimiento' => Request::string('fecha_nacimiento'),
            'email' => Request::string('email'),
            'telefono' => Request::string('telefono'),
            'direccion' => Request::string('direccion'),
            'autorizacion_datos' => Request::bool('autorizacion_datos') ? 1 : 0,
            'fecha_autorizacion' => Request::string('fecha_autorizacion'),
            'medio_autorizacion' => Request::string('medio_autorizacion'),
        ];
    }

    /** @return array<string,string> */
    protected function personRules(): array
    {
        return [
            'tipo_documento_id' => 'required|int',
            'numero_documento' => 'required|max:30',
            'nombres' => 'required|max:100',
            'apellidos' => 'required|max:100',
            'fecha_nacimiento' => 'nullable|date',
            'email' => 'nullable|email|max:120',
            'telefono' => 'nullable|phone|max:30',
            'direccion' => 'nullable|max:180',
            'medio_autorizacion' => 'nullable|max:80',
        ];
    }
}
