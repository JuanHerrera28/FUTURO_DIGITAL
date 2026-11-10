<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Error de regla de negocio. Conserva el código de la regla para uso interno
 * (pruebas y trazabilidad), pero el mensaje visible muestra solo el motivo.
 */
final class DomainException extends RuntimeException
{
    public function __construct(
        private readonly string $rule,
        string $reason
    ) {
        parent::__construct($reason);
    }

    public function rule(): string
    {
        return $this->rule;
    }

    public function fullMessage(): string
    {
        return $this->getMessage();
    }
}
