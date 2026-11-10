<?php

declare(strict_types=1);

/**
 * Proceso hijo para la prueba de concurrencia del último cupo.
 *
 * Argumentos:
 *   1. matricula_id
 *   2. grupo_id
 *   3. archivo listo (lo escribe al terminar el arranque)
 *   4. archivo de inicio (espera a que exista para ejecutar)
 */

require __DIR__ . '/app.php';

use App\Core\DomainException;
use App\Services\EnrollmentService;

$matriculaId = (int) ($argv[1] ?? 0);
$grupoId = (int) ($argv[2] ?? 0);
$readyFile = (string) ($argv[3] ?? '');
$startFile = (string) ($argv[4] ?? '');

if ($matriculaId <= 0 || $grupoId <= 0 || $readyFile === '' || $startFile === '') {
    fwrite(STDERR, "Argumentos incompletos.\n");
    exit(2);
}

file_put_contents($readyFile, (string) getmypid());

$deadline = microtime(true) + 15.0;

while (!is_file($startFile)) {
    if (microtime(true) > $deadline) {
        echo json_encode(['ok' => false, 'error' => 'timeout_esperando_inicio']);
        exit(2);
    }

    usleep(2000);
}

try {
    $result = EnrollmentService::enroll($matriculaId, $grupoId);
    echo json_encode([
        'ok' => true,
        'inscripcion_id' => $result['inscripcion_id'],
        'grupo' => $result['grupo'],
    ], JSON_UNESCAPED_UNICODE);
    exit(0);
} catch (DomainException $exception) {
    echo json_encode([
        'ok' => false,
        'rule' => $exception->rule(),
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit(0);
} catch (Throwable $exception) {
    echo json_encode([
        'ok' => false,
        'error' => get_class($exception) . ': ' . $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
    exit(1);
}
