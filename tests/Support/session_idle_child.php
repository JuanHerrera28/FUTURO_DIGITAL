<?php

declare(strict_types=1);

/**
 * Proceso hijo para comprobar la expiración de la sesión por inactividad.
 * Crea una sesión con actividad antigua, la cierra y vuelve a abrirla usando
 * App\Core\Session::start con una vida útil de un minuto.
 */

require __DIR__ . '/app.php';

use App\Core\Session;

$name = 'fd_idle_' . getmypid();
$id = 'idle' . bin2hex(random_bytes(8));

session_name($name);
session_id($id);
session_start();
$_SESSION['_last_activity'] = time() - 120;
$_SESSION['marker'] = 'debe-desaparecer';
session_write_close();

session_id($id);
Session::start(['name' => $name, 'lifetime' => 1]);

echo json_encode([
    'marker' => $_SESSION['marker'] ?? null,
    'warning' => $_SESSION['_flash']['warning'][0] ?? null,
    'activity' => $_SESSION['_last_activity'] ?? null,
], JSON_UNESCAPED_UNICODE);

session_write_close();
