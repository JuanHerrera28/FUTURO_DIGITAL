<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Database;
use App\Core\DomainException;

/**
 * Aserciones comunes para reglas de negocio expresadas con DomainException.
 */
trait DomainAssertions
{
    protected function expectDomain(string $rule, callable $callback, string $messageContains = ''): DomainException
    {
        try {
            $callback();
        } catch (DomainException $exception) {
            $this->assertSame($rule, $exception->rule(), 'La regla de negocio no coincide.');
            $this->assertNotSame('', trim($exception->getMessage()), 'La regla debe explicar el motivo.');

            if ($messageContains !== '') {
                $this->assertStringContainsString($messageContains, $exception->getMessage());
            }

            return $exception;
        }

        $this->fail('Se esperaba una DomainException con la regla ' . $rule . '.');
    }

    protected function assertDatabaseCount(string $table, int $expected, string $where = '1=1', array $params = []): void
    {
        $count = (int) Database::scalar('SELECT COUNT(*) FROM `' . $table . '` WHERE ' . $where, $params);
        $this->assertSame($expected, $count, 'Conteo inesperado en la tabla ' . $table . '.');
    }
}
