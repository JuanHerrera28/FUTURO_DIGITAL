<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Param;
use PHPUnit\Framework\TestCase;
use Tests\Support\AcademicScenario;
use Tests\Support\AuthContext;
use Tests\Support\DomainAssertions;
use Tests\Support\Fixtures;
use Tests\Support\TestDatabase;

/**
 * RN-03: dos procesos compiten por el último cupo. La transacción con
 * SELECT ... FOR UPDATE debe permitir una sola inscripción y rechazar la otra
 * con el código exacto de la regla.
 *
 * Esta prueba no usa DatabaseTestCase porque los procesos hijos necesitan ver
 * los datos confirmados: cada uno abre su propia conexión.
 */
final class EnrollmentConcurrencyTest extends TestCase
{
    use AcademicScenario;
    use DomainAssertions;

    /** @var list<string> */
    private array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (!function_exists('proc_open')) {
            $this->markTestSkipped('proc_open no está disponible para la prueba de concurrencia.');
        }

        TestDatabase::reset();
        Param::refresh();
    }

    protected function tearDown(): void
    {
        AuthContext::clear();

        foreach ($this->temporaryFiles as $file) {
            @unlink($file);
        }

        TestDatabase::reset();
        Param::refresh();
        parent::tearDown();
    }

    public function testOnlyOneStudentGetsTheLastSeat(): void
    {
        $scenario = $this->academicScenario(['capacidad' => 1]);

        $secondStudent = Fixtures::estudiante((int) $scenario['sede_id']);
        $secondMatricula = Fixtures::matricula(
            (int) $secondStudent['id'],
            (int) $scenario['programa_id'],
            (int) $scenario['periodo_id'],
            (int) $scenario['sede_id']
        );

        $startFile = $this->temporaryFile('start');
        $readyOne = $this->temporaryFile('ready1');
        $readyTwo = $this->temporaryFile('ready2');

        $first = $this->startChild((int) $scenario['matricula_id'], (int) $scenario['grupo_id'], $readyOne, $startFile);
        $second = $this->startChild($secondMatricula, (int) $scenario['grupo_id'], $readyTwo, $startFile);

        $this->waitForFile($readyOne);
        $this->waitForFile($readyTwo);

        touch($startFile);

        $firstResult = $this->finishChild($first);
        $secondResult = $this->finishChild($second);

        $results = [$firstResult, $secondResult];
        $successes = array_values(array_filter($results, static fn (array $result): bool => ($result['ok'] ?? false) === true));
        $failures = array_values(array_filter($results, static fn (array $result): bool => ($result['ok'] ?? false) === false));

        $this->assertCount(1, $successes, 'Exactamente una inscripción debe ganar el último cupo.');
        $this->assertCount(1, $failures, 'Exactamente una inscripción debe ser rechazada.');
        $this->assertSame('RN-03', $failures[0]['rule'] ?? null);
        $this->assertStringContainsString('no tiene cupo disponible', (string) ($failures[0]['message'] ?? ''));

        $this->assertDatabaseCount('inscripciones', 1, "grupo_id = ? AND estado = 'activa'", [(int) $scenario['grupo_id']]);
        $this->assertDatabaseCount('inscripcion_movimientos', 1, "tipo = 'inscripcion'");
    }

    /**
     * @return array{0:resource,1:array<int,resource>}
     */
    private function startChild(int $matriculaId, int $grupoId, string $readyFile, string $startFile): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            [
                PHP_BINARY,
                __DIR__ . '/../Support/enroll_child.php',
                (string) $matriculaId,
                (string) $grupoId,
                $readyFile,
                $startFile,
            ],
            $descriptors,
            $pipes,
            BASE_PATH
        );

        if (!is_resource($process)) {
            $this->fail('No se pudo iniciar el proceso hijo de inscripción.');
        }

        fclose($pipes[0]);

        return [$process, $pipes];
    }

    /**
     * @param array{0:resource,1:array<int,resource>} $child
     * @return array<string,mixed>
     */
    private function finishChild(array $child): array
    {
        [$process, $pipes] = $child;

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        $this->assertSame(0, $exitCode, 'El proceso hijo falló: ' . $stderr);

        $decoded = json_decode($stdout, true);
        $this->assertIsArray($decoded, 'Salida inválida del proceso hijo: ' . $stdout);

        return $decoded;
    }

    private function waitForFile(string $file): void
    {
        $deadline = microtime(true) + 15.0;

        while (!is_file($file)) {
            if (microtime(true) > $deadline) {
                $this->fail('El proceso hijo no confirmó su arranque: ' . $file);
            }

            usleep(20000);
        }
    }

    private function temporaryFile(string $suffix): string
    {
        $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'fd_conc_' . bin2hex(random_bytes(6)) . '_' . $suffix;
        @unlink($file);
        $this->temporaryFiles[] = $file;

        return $file;
    }
}
