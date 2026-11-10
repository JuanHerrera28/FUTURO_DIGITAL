<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\DomainException;
use PHPUnit\Framework\TestCase;

final class DomainExceptionTest extends TestCase
{
    public function testConservaLaReglaPeroElMensajeVisibleNoLaExpone(): void
    {
        $exception = new DomainException('RN-03', 'El grupo no tiene cupo disponible.');

        $this->assertSame('RN-03', $exception->rule());
        $this->assertSame('El grupo no tiene cupo disponible.', $exception->getMessage());
        $this->assertSame('El grupo no tiene cupo disponible.', $exception->fullMessage());
    }

    public function testSinReglaSoloMuestraElMotivo(): void
    {
        $exception = new DomainException('', 'Usuario o contraseña incorrectos.');

        $this->assertSame('', $exception->rule());
        $this->assertSame('Usuario o contraseña incorrectos.', $exception->fullMessage());
    }
}
