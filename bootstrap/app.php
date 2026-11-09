<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Core\Session;

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/bootstrap/autoload.php';

Env::load(BASE_PATH . '/.env');
Config::setAll(require BASE_PATH . '/config/config.php');

$timezone = (string) Config::get('app.timezone', 'America/Bogota');
date_default_timezone_set($timezone);
setlocale(LC_ALL, 'es_CO.UTF-8', 'es_CO', 'Spanish_Colombia.1252', 'Spanish');

$debug = (bool) Config::get('app.debug', false);
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/logs/php-error.log');

try {
    Database::init((array) Config::get('db', []));
} catch (Throwable $e) {
    http_response_code(500);
    if ($debug) {
        echo '<h1>No se pudo conectar a la base de datos</h1><pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
        echo '<p>Verifique que MySQL esté iniciado y los datos de <code>.env</code>.</p>';
    } else {
        echo 'Servicio no disponible. Consulte al administrador.';
    }
    exit;
}

Session::start((array) Config::get('session', []));
Auth::boot();
