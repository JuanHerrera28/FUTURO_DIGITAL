<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Auth;
use App\Core\Database;
use Tests\Support\AuthContext;
use Tests\Support\DatabaseTestCase;
use Tests\Support\Fixtures;

/**
 * RNF de seguridad: autenticación, bloqueo por intentos, cambio obligatorio
 * de contraseña, matriz de permisos, alcance por sede y expiración de sesión.
 */
final class AuthSecurityTest extends DatabaseTestCase
{
    public function testSuccessfulLoginResetsFailuresAndAudits(): void
    {
        $userId = Fixtures::usuario(Fixtures::persona(), 2, 1, 'login-ok', 'Secret123*');
        Database::update('usuarios', ['intentos_fallidos' => 2], ['id' => $userId]);

        $user = Auth::attempt('login-ok', 'Secret123*');

        $this->assertSame($userId, (int) $user['id']);
        $this->assertTrue(Auth::check());
        $this->assertSame($userId, Auth::id());

        $row = Database::fetch('SELECT * FROM usuarios WHERE id = ?', [$userId]);
        $this->assertSame('0', (string) $row['intentos_fallidos']);
        $this->assertNull($row['bloqueado_hasta']);
        $this->assertNotNull($row['ultimo_acceso']);

        $this->assertDatabaseCount('intentos_login', 1, "username = 'login-ok' AND exito = 1");
        $this->assertDatabaseCount('auditoria', 1, "accion = 'login' AND entidad_id = ?", [(string) $userId]);
    }

    public function testFiveFailedAttemptsLockTheAccount(): void
    {
        $userId = Fixtures::usuario(Fixtures::persona(), 2, 1, 'lock-me', 'Secret123*');

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->expectDomain('', fn () => Auth::attempt('lock-me', 'incorrecta'), 'Intento ' . $attempt);
        }

        $this->expectDomain('', fn () => Auth::attempt('lock-me', 'incorrecta'), 'bloqueada');

        $row = Database::fetch('SELECT * FROM usuarios WHERE id = ?', [$userId]);
        $this->assertSame('5', (string) $row['intentos_fallidos']);
        $this->assertNotNull($row['bloqueado_hasta']);
        $this->assertGreaterThan(time(), strtotime((string) $row['bloqueado_hasta']));

        $this->assertDatabaseCount('intentos_login', 5, "username = 'lock-me' AND exito = 0");
        $this->assertDatabaseCount('auditoria', 5, "accion = 'login_fallido' AND entidad_id = ?", [(string) $userId]);
    }

    public function testBlockedAccountRejectsTheCorrectPassword(): void
    {
        $userId = Fixtures::usuario(Fixtures::persona(), 2, 1, 'blocked', 'Secret123*');
        Database::update('usuarios', [
            'intentos_fallidos' => 5,
            'bloqueado_hasta' => date('Y-m-d H:i:s', time() + 900),
        ], ['id' => $userId]);

        $this->expectDomain('', fn () => Auth::attempt('blocked', 'Secret123*'), 'bloqueada temporalmente');
        $this->assertFalse(Auth::check());
    }

    public function testInactiveAccountIsRejected(): void
    {
        $userId = Fixtures::usuario(Fixtures::persona(), 2, 1, 'inactive', 'Secret123*');
        Database::update('usuarios', ['estado' => 'inactivo'], ['id' => $userId]);

        $this->expectDomain('', fn () => Auth::attempt('inactive', 'Secret123*'), 'inactiva');
        $this->assertFalse(Auth::check());
    }

    public function testUnknownUserIsRejectedAndTheAttemptIsRecorded(): void
    {
        $this->expectDomain('', fn () => Auth::attempt('no-existe', 'cualquiera'), 'incorrectos');

        $this->assertDatabaseCount('intentos_login', 1, "username = 'no-existe' AND exito = 0");
        $this->assertDatabaseCount('auditoria', 0, "accion = 'login_fallido'");
    }

    public function testChangePasswordValidatesAndUpdatesTheHash(): void
    {
        $userId = Fixtures::usuario(Fixtures::persona(), 2, 1, 'change-pw', 'OldPass123*', 1);
        AuthContext::login($userId);

        $this->assertTrue(Auth::mustChangePassword());

        $this->expectDomain('', fn () => Auth::changePassword('incorrecta', 'NewPass123*'), 'actual no es correcta');
        $this->expectDomain('', fn () => Auth::changePassword('OldPass123*', 'OldPass123*'), 'diferente');
        $this->expectDomain('', fn () => Auth::changePassword('OldPass123*', 'debil'), 'mínimo 8');

        Auth::changePassword('OldPass123*', 'NewPass123*');

        $this->assertFalse(Auth::mustChangePassword());

        $row = Database::fetch('SELECT * FROM usuarios WHERE id = ?', [$userId]);
        $this->assertTrue(password_verify('NewPass123*', (string) $row['password_hash']));
        $this->assertSame('0', (string) $row['debe_cambiar_password']);
        $this->assertDatabaseCount(
            'auditoria',
            1,
            "accion = 'cambio_password' AND entidad_id = ?",
            [(string) $userId]
        );
    }

    public function testPermissionsFollowTheEditableRoleMatrix(): void
    {
        $admin = Fixtures::usuario(Fixtures::persona(), 1, null, 'admin-permisos');
        AuthContext::login($admin);

        $this->assertTrue(Auth::can('users.manage'));
        $this->assertTrue(Auth::can('roles.manage'));
        $this->assertTrue(Auth::can('params.manage'));
        $this->assertTrue(Auth::can('audit.view'));
        $this->assertFalse(Auth::can('grades.manage'));
        $this->assertFalse(Auth::can('enrollment.manage'));

        $teacher = Fixtures::docente(1, true);
        AuthContext::login((int) $teacher['user_id']);

        $this->assertTrue(Auth::can('grades.manage'));
        $this->assertTrue(Auth::can('attendance.manage'));
        $this->assertTrue(Auth::can('groups.view'));
        $this->assertFalse(Auth::can('users.manage'));

        $student = Fixtures::usuario(Fixtures::persona(), 7, 1, 'estudiante-permisos');
        AuthContext::login($student);

        $this->assertTrue(Auth::can('portal.view'));
        $this->assertFalse(Auth::can('payments.manage'));
        $this->assertFalse(Auth::can('grades.view'));
    }

    public function testSedeScopeOnlyRestrictsSecretaria(): void
    {
        $secretaria = Fixtures::usuario(Fixtures::persona(), 2, 1, 'secretaria-scope');
        AuthContext::login($secretaria);

        $this->assertSame(1, Auth::sedeScope());
        $this->assertTrue(Auth::canAccessSede(1));
        $this->assertTrue(Auth::canAccessSede(null));
        $this->assertFalse(Auth::canAccessSede(2));

        $coordinador = Fixtures::usuario(Fixtures::persona(), 3, 2, 'coordinador-scope');
        AuthContext::login($coordinador);

        $this->assertNull(Auth::sedeScope());
        $this->assertTrue(Auth::canAccessSede(1));
        $this->assertTrue(Auth::canAccessSede(2));
    }

    public function testIdleSessionExpiresAfterTheConfiguredMinutes(): void
    {
        $result = $this->runPhpChild(__DIR__ . '/../Support/session_idle_child.php');

        $this->assertNull($result['marker'] ?? null, 'La sesión debía limpiarse por inactividad.');
        $this->assertStringContainsString('expiró por inactividad', (string) ($result['warning'] ?? ''));
        $this->assertNotNull($result['activity'] ?? null);
    }

    /**
     * @return array<string,mixed>
     */
    private function runPhpChild(string $script): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open([PHP_BINARY, $script], $descriptors, $pipes, BASE_PATH);

        if (!is_resource($process)) {
            $this->fail('No se pudo iniciar el proceso hijo de PHP.');
        }

        fclose($pipes[0]);
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
}
