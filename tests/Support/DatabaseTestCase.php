<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Database;
use App\Core\Param;
use PHPUnit\Framework\TestCase;

/**
 * Base para pruebas de integración: cada prueba corre dentro de una
 * transacción que se revierte al final, salvo que la prueba indique lo
 * contrario (por ejemplo, concurrencia con procesos hijos).
 */
abstract class DatabaseTestCase extends TestCase
{
    use DomainAssertions;

    protected function setUp(): void
    {
        parent::setUp();
        Param::refresh();
        Database::begin();
    }

    protected function tearDown(): void
    {
        AuthContext::clear();

        if (Database::inTransaction()) {
            Database::rollBack();
        }

        Param::refresh();
        parent::tearDown();
    }
}
