<?php

declare(strict_types=1);

/**
 * Autoloader PSR-4 mínimo para el espacio de nombres App\.
 * No se requiere Composer para ejecutar la aplicación.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = BASE_PATH . '/src/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

require BASE_PATH . '/src/Core/helpers.php';
