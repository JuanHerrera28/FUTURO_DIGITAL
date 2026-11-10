<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    public function testEscapeHtml(): void
    {
        $this->assertSame(
            '&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;',
            e('<script>alert("x")</script>')
        );
        $this->assertSame('', e(null));
    }

    public function testFormatoDeMoneda(): void
    {
        $this->assertSame('$ 1.235', money(1234.5));
        $this->assertSame('$ 1.234,50', money(1234.5, 2));
        $this->assertSame('$ 0', money(0));
    }

    public function testFormatoDeFechaYHora(): void
    {
        $this->assertSame('09/11/2026', fecha('2026-11-09'));
        $this->assertSame('—', fecha(null));
        $this->assertSame('08:30', hora('08:30:00'));
        $this->assertSame('—', hora(''));
    }

    public function testPorcentajeEvitaDivisionPorCero(): void
    {
        $this->assertSame(25.0, porcentaje(1, 4));
        $this->assertSame(0.0, porcentaje(1, 0));
        $this->assertSame(33.3, porcentaje(1, 3));
    }

    public function testNormalizaDocumento(): void
    {
        $this->assertSame('12345678A', normalizar_documento(' 12.345-678 a '));
        $this->assertSame('', normalizar_documento(null));
    }

    public function testPasswordTemporalCumplePolitica(): void
    {
        $password = generar_password(12);

        $this->assertSame(12, mb_strlen($password));
        $this->assertMatchesRegularExpression('/[A-Z]/', $password);
        $this->assertMatchesRegularExpression('/[a-z]/', $password);
        $this->assertMatchesRegularExpression('/\d/', $password);
        $this->assertMatchesRegularExpression('/[!@#$%*?]/', $password);
    }

    public function testInicialesYLimiteDeTexto(): void
    {
        $this->assertSame('JC', iniciales('Juan Carlos Herrera'));
        $this->assertSame('ABC…', str_limit('ABCDEF', 4));
        $this->assertSame('ABC', str_limit('ABC', 4));
    }

    public function testDiasSemana(): void
    {
        $days = dias_semana();

        $this->assertSame('Lunes', $days[1]);
        $this->assertSame('Domingo', $days[7]);
        $this->assertCount(7, $days);
    }
}
