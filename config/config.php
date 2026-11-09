<?php

declare(strict_types=1);

use App\Core\Env;

return [
    'app' => [
        'name' => Env::get('APP_NAME', 'Futuro Digital'),
        'env' => Env::get('APP_ENV', 'local'),
        'debug' => Env::bool('APP_DEBUG', true),
        'url' => rtrim((string) Env::get('APP_URL', 'http://localhost:8000'), '/'),
        'timezone' => Env::get('APP_TIMEZONE', 'America/Bogota'),
        'key' => Env::get('APP_KEY', ''),
    ],
    'db' => [
        'host' => Env::get('DB_HOST', '127.0.0.1'),
        'port' => Env::get('DB_PORT', '3306'),
        'database' => Env::get('DB_DATABASE', 'futuro_digital'),
        'username' => Env::get('DB_USERNAME', 'root'),
        'password' => Env::get('DB_PASSWORD', ''),
        'charset' => 'utf8mb4',
    ],
    'session' => [
        'name' => Env::get('SESSION_NAME', 'futuro_digital_sid'),
        'lifetime' => (int) Env::get('SESSION_LIFETIME', '30'),
        'secure' => Env::bool('SESSION_SECURE', false),
    ],
];
