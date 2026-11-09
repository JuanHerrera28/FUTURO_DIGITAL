<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Renderizado de vistas PHP con layout.
 */
final class View
{
    /** @param array<string,mixed> $data */
    public static function render(string $view, array $data = [], string $layout = 'layouts/app'): void
    {
        $old = Session::pull('_old', []);
        $errors = Session::pull('_errors', []);

        $GLOBALS['__old'] = is_array($old) ? $old : [];
        $GLOBALS['__errors'] = is_array($errors) ? $errors : [];

        $data['old'] ??= $GLOBALS['__old'];
        $data['errors'] ??= $GLOBALS['__errors'];
        $data['flashes'] ??= Session::flashes();
        $data['title'] ??= (string) Config::get('app.name', 'Futuro Digital');

        $file = BASE_PATH . '/views/' . str_replace('.', '/', $view) . '.php';
        if (!is_file($file)) {
            throw new RuntimeException('Vista no encontrada: ' . $view);
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $file;
        $content = (string) ob_get_clean();

        if ($layout === '') {
            echo $content;
            return;
        }

        $layoutFile = BASE_PATH . '/views/' . str_replace('.', '/', $layout) . '.php';
        if (!is_file($layoutFile)) {
            throw new RuntimeException('Layout no encontrado: ' . $layout);
        }

        require $layoutFile;
    }

    /** @param array<string,mixed> $data */
    public static function partial(string $view, array $data = []): string
    {
        $file = BASE_PATH . '/views/' . str_replace('.', '/', $view) . '.php';
        if (!is_file($file)) {
            throw new RuntimeException('Parcial no encontrado: ' . $view);
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $file;

        return (string) ob_get_clean();
    }
}
