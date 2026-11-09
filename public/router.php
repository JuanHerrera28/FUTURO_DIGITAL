<?php

declare(strict_types=1);

/**
 * Router para el servidor embebido de PHP:
 * php -S localhost:8000 -t public public/router.php
 */
$path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$file = __DIR__ . $path;

if ($path !== '/' && is_file($file)) {
    return false;
}

require __DIR__ . '/index.php';
