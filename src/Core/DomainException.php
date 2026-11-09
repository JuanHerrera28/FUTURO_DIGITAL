<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Error de regla de negocio. Siempre incluye el código de la regla
 * (por ejemplo RN-03) y el motivo exacto del rechazo.
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
        return $this->rule !== ''
            ? $this->rule . ': ' . $this->getMessage()
            : $this->getMessage();
    }
}
