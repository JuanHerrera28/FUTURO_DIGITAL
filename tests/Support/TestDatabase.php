<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Database;
use PDO;
use RuntimeException;

/**
 * Ciclo de vida de la base de datos de pruebas.
 *
 * Nunca se ejecuta contra la base configurada en .env: tests/bootstrap.php
 * sustituye el nombre por futuro_digital_test (o TEST_DB_DATABASE).
 */
final class TestDatabase
{
    /** @param array<string,mixed> $config */
    public static function createDatabase(array $config): void
    {
        $database = (string) ($config['database'] ?? 'futuro_digital_test');

        $dsn = sprintf(
            'mysql:host=%s;port=%s;charset=%s',
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? '3306',
            $config['charset'] ?? 'utf8mb4'
        );

        $pdo = new PDO(
            $dsn,
            (string) ($config['username'] ?? 'root'),
            (string) ($config['password'] ?? ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );

        $pdo->exec(sprintf(
            'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
            $database
        ));
    }

    /**
     * Deja la base de pruebas en el estado de schema.sql + seed.sql.
     */
    public static function reset(): void
    {
        $pdo = Database::pdo();

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $pdo->exec('DROP TABLE IF EXISTS `' . (string) $table . '`');
        }

        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        self::runFile(BASE_PATH . '/database/schema.sql');
        self::runFile(BASE_PATH . '/database/seed.sql');

        $count = (int) Database::scalar(
            "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()"
        );

        if ($count !== 37) {
            throw new RuntimeException('La base de pruebas no quedó completa. Tablas encontradas: ' . $count);
        }
    }

    private static function runFile(string $path): void
    {
        $sql = file_get_contents($path);

        if ($sql === false) {
            throw new RuntimeException('No se pudo leer ' . $path);
        }

        $sql = preg_replace('/^\xEF\xBB\xBF/', '', $sql) ?? $sql;

        Database::pdo()->exec($sql);
    }
}
