<?php

declare(strict_types=1);

use App\Core\Router;

require dirname(__DIR__) . '/bootstrap/app.php';

$router = new Router();
require BASE_PATH . '/routes/web.php';
$router->dispatch();
