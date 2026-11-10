<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use Tests\Support\TestDatabase;

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

require BASE_PATH . '/bootstrap/autoload.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'Tests\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

Env::load(BASE_PATH . '/.env');

$config = require BASE_PATH . '/config/config.php';
$database = getenv('TEST_DB_DATABASE') ?: 'futuro_digital_test';

if (preg_match('/^[A-Za-z0-9_]+$/', $database) !== 1) {
    throw new RuntimeException('El nombre de la base de datos de pruebas no es válido: ' . $database);
}

$config['db']['database'] = $database;
$config['app']['env'] = 'testing';
$config['app']['debug'] = true;

Config::setAll($config);
date_default_timezone_set((string) Config::get('app.timezone', 'America/Bogota'));
setlocale(LC_ALL, 'es_CO.UTF-8', 'es_CO', 'Spanish_Colombia.1252', 'Spanish');

TestDatabase::createDatabase((array) Config::get('db', []));
Database::init((array) Config::get('db', []));
TestDatabase::reset();
