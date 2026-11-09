<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    public static function token(): string
    {
        return Session::token();
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(self::token()) . '">';
    }

    public static function validate(mixed $token): bool
    {
        $stored = Session::get('_csrf');

        return is_string($stored) && is_string($token) && $token !== '' && hash_equals($stored, $token);
    }
}
