<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    /** @param array<string,mixed> $data */
    protected function view(string $view, array $data = [], string $layout = 'layouts/app'): void
    {
        View::render($view, $data, $layout);
    }

    protected function redirect(string $path, int $status = 302): never
    {
        Response::redirect($path, $status);
    }

    protected function back(string $fallback = '/dashboard'): never
    {
        Response::back($fallback);
    }

    protected function fail(string $message, string $rule = '', ?array $old = null, ?string $to = null): never
    {
        $exception = new DomainException($rule, $message);

        if ($old !== null) {
            Session::set('_old', $old);
        }

        Session::flash('error', $exception->fullMessage());

        if ($to !== null) {
            Response::redirect($to);
        }

        Response::back();
    }

    /**
     * Valida y devuelve solo los campos declarados. Si falla, guarda los
     * errores y el formulario para mostrarlos de nuevo.
     *
     * @param array<string,string> $rules
     * @param array<string,string> $labels
     * @return array<string,mixed>
     */
    protected function validate(array $rules, array $labels = []): array
    {
        $validator = new Validator(Request::all(), $labels);
        $validator->rules($rules);

        if ($validator->fails()) {
            Session::set('_old', Request::all());
            Session::set('_errors', $validator->errors());
            Session::flash('error', 'Revise los campos marcados en el formulario.');
            Response::back();
        }

        return $validator->validated();
    }

    protected function id(string|int|null $value): int
    {
        return max(0, (int) $value);
    }

    protected function requirePost(): void
    {
        if (!Request::isPost()) {
            throw new HttpException(405, 'Método no permitido.');
        }
    }
}
