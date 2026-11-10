<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function testRequiredRechazaVacio(): void
    {
        $validator = new Validator(['nombre' => '   '], ['nombre' => 'nombre']);
        $validator->rules(['nombre' => 'required']);

        $this->assertTrue($validator->fails());
        $this->assertSame('El campo nombre es obligatorio.', $validator->first());
    }

    public function testNullablePermiteVacioYDevuelveNull(): void
    {
        $validator = new Validator(['telefono' => ''], ['telefono' => 'teléfono']);
        $validator->rules(['telefono' => 'nullable|phone']);

        $this->assertTrue($validator->passes());
        $this->assertNull($validator->validated()['telefono']);
    }

    public function testEmailValidoEInvalido(): void
    {
        $valid = new Validator(['email' => 'persona@example.test']);
        $valid->rules(['email' => 'required|email']);

        $invalid = new Validator(['email' => 'persona@']);
        $invalid->rules(['email' => 'required|email']);

        $this->assertTrue($valid->passes());
        $this->assertTrue($invalid->fails());
        $this->assertStringContainsString('correo válido', (string) $invalid->first());
    }

    public function testFechaRealYFechaImposible(): void
    {
        $valid = new Validator(['fecha' => '2026-02-28']);
        $valid->rules(['fecha' => 'required|date']);

        $invalid = new Validator(['fecha' => '2026-02-30']);
        $invalid->rules(['fecha' => 'required|date']);

        $this->assertTrue($valid->passes());
        $this->assertTrue($invalid->fails());
    }

    public function testPasswordFuerte(): void
    {
        $validator = new Validator(['password' => 'Abc123!x']);
        $validator->rules(['password' => 'required|strong_password']);

        $this->assertTrue($validator->passes());
    }

    public function testPasswordDebilPorCadaCriterio(): void
    {
        $cases = [
            'Abc123!x' => true,
            'abc123!x' => false,
            'ABCD123!X' => false,
            'Abcdef!x' => false,
            'Abc1234x' => false,
            'Ab1!xyz' => false,
        ];

        foreach ($cases as $password => $expected) {
            $validator = new Validator(['password' => $password]);
            $validator->rules(['password' => 'required|strong_password']);
            $this->assertSame($expected, $validator->passes(), 'Contraseña evaluada: ' . $password);
        }
    }

    public function testConfirmedComparaConElCampoDeConfirmacion(): void
    {
        $valid = new Validator([
            'password' => 'Abc123!x',
            'password_confirmed' => 'Abc123!x',
        ]);
        $valid->rules(['password' => 'required|confirmed']);

        $invalid = new Validator([
            'password' => 'Abc123!x',
            'password_confirmed' => 'Otra123!x',
        ]);
        $invalid->rules(['password' => 'required|confirmed']);

        $this->assertTrue($valid->passes());
        $this->assertTrue($invalid->fails());
        $this->assertStringContainsString('confirmación', (string) $invalid->first());
    }

    public function testBetweenvalAceptaDecimalesConComa(): void
    {
        $valid = new Validator(['nota' => '4,5']);
        $valid->rules(['nota' => 'required|betweenval:0,5']);

        $invalid = new Validator(['nota' => '5,1']);
        $invalid->rules(['nota' => 'required|betweenval:0,5']);

        $this->assertTrue($valid->passes());
        $this->assertTrue($invalid->fails());
    }

    public function testInYEnteros(): void
    {
        $valid = new Validator(['estado' => 'activo', 'cantidad' => '12']);
        $valid->rules(['estado' => 'required|in:activo,inactivo', 'cantidad' => 'required|int']);

        $invalid = new Validator(['estado' => 'borrado', 'cantidad' => '1.5']);
        $invalid->rules(['estado' => 'required|in:activo,inactivo', 'cantidad' => 'required|int']);

        $this->assertTrue($valid->passes());
        $this->assertCount(2, $invalid->errors());
    }

    public function testMaxYMinSeMidenEnCaracteresUnicode(): void
    {
        $validator = new Validator(['nombre' => 'José']);
        $validator->rules(['nombre' => 'required|min:4|max:4']);

        $this->assertTrue($validator->passes());
    }

    public function testTrimeaElValorValidado(): void
    {
        $validator = new Validator(['nombre' => '  Ana  ']);
        $validator->rules(['nombre' => 'required|alpha']);

        $this->assertSame('Ana', $validator->validated()['nombre']);
    }
}
