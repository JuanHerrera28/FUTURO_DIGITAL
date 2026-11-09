<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;

/**
 * Validador declarativo de datos de formulario.
 * Reglas separadas por | y argumentos con :
 */
final class Validator
{
    /** @var array<string,string> */
    private array $errors = [];

    /** @var array<string,mixed> */
    private array $validated = [];

    /** @param array<string,mixed> $data */
    public function __construct(
        private readonly array $data,
        private readonly array $labels = []
    ) {
    }

    /** @param array<string,string> $rules */
    public function rules(array $rules): self
    {
        foreach ($rules as $field => $definition) {
            $this->apply($field, $definition);
        }

        return $this;
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    /** @return array<string,string> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return list<string> */
    public function allErrors(): array
    {
        return array_values($this->errors);
    }

    /** @return array<string,mixed> */
    public function validated(): array
    {
        return $this->validated;
    }

    public function error(string $field): ?string
    {
        return $this->errors[$field] ?? null;
    }

    public function first(): ?string
    {
        return $this->allErrors()[0] ?? null;
    }

    public function addError(string $field, string $message): void
    {
        $this->errors[$field] ??= $message;
    }

    private function apply(string $field, string $definition): void
    {
        $value = $this->data[$field] ?? null;
        $parts = array_values(array_filter(array_map('trim', explode('|', $definition)), static fn (string $part): bool => $part !== ''));
        $nullable = in_array('nullable', $parts, true);
        $required = in_array('required', $parts, true);
        $empty = $value === null || (is_string($value) && trim($value) === '') || (is_array($value) && $value === []);

        if ($empty) {
            if ($required) {
                $this->addError($field, 'El campo ' . $this->label($field) . ' es obligatorio.');
            } elseif ($nullable) {
                $this->validated[$field] = null;
            } else {
                $this->validated[$field] = $value;
            }

            return;
        }

        $valid = true;

        foreach ($parts as $part) {
            if ($part === 'required' || $part === 'nullable') {
                continue;
            }

            [$rule, $argument] = array_pad(explode(':', $part, 2), 2, null);

            if (!$this->check($rule, $value, $argument, $field)) {
                $this->addError($field, $this->message($rule, $field, $argument));
                $valid = false;
                break;
            }
        }

        if ($valid) {
            $this->validated[$field] = is_string($value) ? trim($value) : $value;
        }
    }

    private function check(string $rule, mixed $value, ?string $argument, string $field): bool
    {
        $string = is_scalar($value) ? (string) $value : '';
        $numericString = str_replace(',', '.', $string);

        return match ($rule) {
            'int' => preg_match('/^-?\d+$/', $string) === 1,
            'digits' => preg_match('/^\d+$/', $string) === 1,
            'numeric' => is_numeric($numericString),
            'decimal' => is_numeric($numericString),
            'email' => filter_var($string, FILTER_VALIDATE_EMAIL) !== false,
            'date' => $this->isDate($string),
            'min' => mb_strlen($string) >= (int) $argument,
            'max' => mb_strlen($string) <= (int) $argument,
            'minval' => is_numeric($numericString) && (float) $numericString >= (float) $argument,
            'maxval' => is_numeric($numericString) && (float) $numericString <= (float) $argument,
            'between' => $this->betweenLength($string, $argument),
            'betweenval' => is_numeric($numericString) && $this->betweenValue((float) $numericString, $argument),
            'in' => in_array($string, array_map('trim', explode(',', (string) $argument)), true),
            'same' => $string === (string) ($this->data[$argument ?? ''] ?? null),
            'confirmed' => $string === (string) ($this->data[$field . '_confirmed'] ?? null),
            'regex' => @preg_match((string) $argument, $string) === 1,
            'alpha' => preg_match('/^[\pL\s]+$/u', $string) === 1,
            'alpha_num' => preg_match('/^[\pL\pN\s._-]+$/u', $string) === 1,
            'username' => preg_match('/^[A-Za-z0-9._-]+$/', $string) === 1,
            'strong_password' => $this->isStrongPassword($string),
            'phone' => preg_match('/^[0-9+\s()-]{7,30}$/', $string) === 1,
            default => true,
        };
    }

    private function message(string $rule, string $field, ?string $argument): string
    {
        $label = $this->label($field);

        return match ($rule) {
            'int' => 'El campo ' . $label . ' debe ser un número entero.',
            'digits' => 'El campo ' . $label . ' debe contener solo números.',
            'numeric', 'decimal' => 'El campo ' . $label . ' debe ser numérico.',
            'email' => 'El campo ' . $label . ' debe ser un correo válido.',
            'date' => 'El campo ' . $label . ' debe ser una fecha válida (AAAA-MM-DD).',
            'min' => 'El campo ' . $label . ' debe tener al menos ' . $argument . ' caracteres.',
            'max' => 'El campo ' . $label . ' no puede superar ' . $argument . ' caracteres.',
            'minval' => 'El campo ' . $label . ' debe ser mayor o igual a ' . $argument . '.',
            'maxval' => 'El campo ' . $label . ' debe ser menor o igual a ' . $argument . '.',
            'between' => 'El campo ' . $label . ' debe tener entre ' . $argument . ' caracteres.',
            'betweenval' => 'El campo ' . $label . ' debe estar entre ' . $argument . '.',
            'in' => 'El campo ' . $label . ' tiene un valor no permitido.',
            'same' => 'El campo ' . $label . ' no coincide.',
            'confirmed' => 'La confirmación del campo ' . $label . ' no coincide.',
            'regex' => 'El campo ' . $label . ' tiene un formato inválido.',
            'alpha' => 'El campo ' . $label . ' solo admite letras y espacios.',
            'alpha_num' => 'El campo ' . $label . ' contiene caracteres no permitidos.',
            'username' => 'El campo ' . $label . ' solo admite letras, números, punto, guion y guion bajo.',
            'strong_password' => 'La contraseña debe tener mínimo 8 caracteres, mayúscula, minúscula, número y símbolo.',
            'phone' => 'El campo ' . $label . ' debe ser un teléfono válido.',
            default => 'El campo ' . $label . ' no es válido.',
        };
    }

    private function label(string $field): string
    {
        if (isset($this->labels[$field])) {
            return $this->labels[$field];
        }

        return mb_strtolower(str_replace(['_', 'id'], [' ', ''], $field));
    }

    private function isDate(string $value): bool
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }

    private function betweenLength(string $value, ?string $argument): bool
    {
        [$min, $max] = array_pad(explode(',', (string) $argument), 2, null);
        $length = mb_strlen($value);

        return $length >= (int) $min && $length <= (int) $max;
    }

    private function betweenValue(float $value, ?string $argument): bool
    {
        [$min, $max] = array_pad(explode(',', (string) $argument), 2, null);

        return $value >= (float) $min && $value <= (float) $max;
    }

    private function isStrongPassword(string $value): bool
    {
        return mb_strlen($value) >= 8
            && preg_match('/[A-ZÁÉÍÓÚÑ]/u', $value) === 1
            && preg_match('/[a-záéíóúñ]/u', $value) === 1
            && preg_match('/\d/', $value) === 1
            && preg_match('/[^A-Za-z0-9\s]/', $value) === 1;
    }
}
