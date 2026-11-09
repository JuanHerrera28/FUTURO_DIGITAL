<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;
use Throwable;

/**
 * Conexión PDO única y utilidades de consulta.
 * Todas las consultas usan sentencias preparadas.
 */
final class Database
{
    private static ?PDO $pdo = null;
    private static int $transactionDepth = 0;

    /** @param array<string,mixed> $config */
    public static function init(array $config): void
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? '3306',
            $config['database'] ?? 'futuro_digital',
            $config['charset'] ?? 'utf8mb4'
        );

        self::$pdo = new PDO(
            $dsn,
            (string) ($config['username'] ?? 'root'),
            (string) ($config['password'] ?? ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4, time_zone = '-05:00'",
            ]
        );
    }

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            throw new \RuntimeException('La base de datos no ha sido inicializada.');
        }

        return self::$pdo;
    }

    /** @param array<string|int,mixed> $params */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $statement = self::pdo()->prepare($sql);
        $statement->execute($params);

        return $statement;
    }

    /**
     * @param array<string|int,mixed> $params
     * @return array<string,mixed>|null
     */
    public static function fetch(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array<string|int,mixed> $params
     * @return list<array<string,mixed>>
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /** @param array<string|int,mixed> $params */
    public static function scalar(string $sql, array $params = []): mixed
    {
        $value = self::query($sql, $params)->fetchColumn();

        return $value === false ? null : $value;
    }

    /** @param array<string|int,mixed> $params */
    public static function execute(string $sql, array $params = []): int
    {
        return self::query($sql, $params)->rowCount();
    }

    /** @param array<string,mixed> $data */
    public static function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $quoted = array_map(static fn (string $column): string => '`' . $column . '`', $columns);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', $quoted),
            implode(', ', $placeholders)
        );

        self::query($sql, $data);

        return (int) self::pdo()->lastInsertId();
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $where
     */
    public static function update(string $table, array $data, array $where): int
    {
        $sets = [];
        $params = [];

        foreach ($data as $column => $value) {
            $sets[] = '`' . $column . '` = :set_' . $column;
            $params['set_' . $column] = $value;
        }

        $conditions = [];
        foreach ($where as $column => $value) {
            $conditions[] = '`' . $column . '` = :where_' . $column;
            $params['where_' . $column] = $value;
        }

        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE %s',
            $table,
            implode(', ', $sets),
            implode(' AND ', $conditions)
        );

        return self::execute($sql, $params);
    }

    /**
     * Ejecuta un bloque dentro de una transacción. Si ya hay una transacción
     * activa, reutiliza la existente para permitir composición de servicios.
     */
    public static function transaction(callable $callback): mixed
    {
        if (self::$transactionDepth > 0) {
            return $callback();
        }

        self::pdo()->beginTransaction();
        self::$transactionDepth++;

        try {
            $result = $callback();
            self::pdo()->commit();
            self::$transactionDepth--;

            return $result;
        } catch (Throwable $e) {
            if (self::pdo()->inTransaction()) {
                self::pdo()->rollBack();
            }
            self::$transactionDepth--;

            throw $e;
        }
    }

    public static function begin(): void
    {
        self::pdo()->beginTransaction();
        self::$transactionDepth++;
    }

    public static function commit(): void
    {
        if (self::$transactionDepth > 0) {
            self::$transactionDepth--;
        }
        if (self::$transactionDepth === 0 && self::pdo()->inTransaction()) {
            self::pdo()->commit();
        }
    }

    public static function rollBack(): void
    {
        if (self::$transactionDepth > 0) {
            self::$transactionDepth--;
        }
        if (self::pdo()->inTransaction()) {
            self::pdo()->rollBack();
        }
        self::$transactionDepth = 0;
    }

    public static function inTransaction(): bool
    {
        return self::$pdo !== null && self::$pdo->inTransaction();
    }
}
