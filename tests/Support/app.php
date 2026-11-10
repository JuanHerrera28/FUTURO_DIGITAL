<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}

require BASE_PATH . '/bootstrap/autoload.php';

Env::load(BASE_PATH . '/.env');

$config = require BASE_PATH . '/config/config.php';
$database = getenv('TEST_DB_DATABASE') ?: 'futuro_digital_test';

if (preg_match('/^[A-Za-z0-9_]+$/', $database) !== 1) {
    fwrite(STDERR, "Nombre de base de pruebas inválido.\n");
    exit(2);
}

$config['db']['database'] = $database;
$config['app']['env'] = 'testing';
$config['app']['debug'] = true;

Config::setAll($config);
date_default_timezone_set((string) Config::get('app.timezone', 'America/Bogota'));

Database::init((array) Config::get('db', []));
