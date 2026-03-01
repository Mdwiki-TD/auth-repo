<?php

declare(strict_types=1);

namespace App\Database;

use App\Config;
use PDO;
use PDOException;

/**
 * Lightweight PDO wrapper.
 *
 * Creates a PDO connection to the configured MySQL database and provides
 * two convenience methods for executing queries.
 */
final class Connection
{
    private PDO $pdo;
    private bool $groupByModeDisabled = false;

    /**
     * @param string $dbNameVar  Environment-variable name that holds the database name.
     * @throws PDOException      When the connection cannot be established (re-thrown in testing mode).
     */
    public function __construct(string $dbNameVar = 'DB_NAME')
    {
        $host     = Config::env('DB_HOST') ?: 'tools.db.svc.wikimedia.cloud';
        $dbName   = Config::env($dbNameVar);
        $user     = Config::env('TOOL_TOOLSDB_USER');
        $password = Config::env('TOOL_TOOLSDB_PASSWORD');

        try {
            $this->pdo = new PDO(
                "mysql:host={$host};dbname={$dbName}",
                $user,
                $password
            );
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            error_log($e->getMessage());
            if (Config::env('APP_ENV') === 'testing') {
                throw $e;
            }
            echo 'Unable to connect to the database. Please try again later.';
            exit();
        }
    }

    /**
     * Execute a statement (INSERT / UPDATE / DELETE) or a SELECT query.
     *
     * For SELECT queries the result rows are returned; for other statements
     * an empty array is returned.
     *
     * @param  string            $sql
     * @param  array<mixed>|null $params
     * @return array<int, array<string, mixed>>
     */
    public function execute(string $sql, ?array $params = null): array
    {
        try {
            $this->disableFullGroupByIfNeeded($sql);

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params ?? []);

            $queryType = strtoupper(substr(trim($sql), 0, 6));
            if ($queryType === 'SELECT') {
                /** @var array<int, array<string, mixed>> */
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            return [];
        } catch (PDOException $e) {
            if (Config::env('APP_ENV') === 'testing') {
                throw $e;
            }
            echo 'sql error:' . $e->getMessage() . '<br>' . $sql;
            return [];
        }
    }

    /**
     * Execute a SELECT query and return all matching rows.
     *
     * @param  string            $sql
     * @param  array<mixed>|null $params
     * @return array<int, array<string, mixed>>
     */
    public function fetch(string $sql, ?array $params = null): array
    {
        try {
            $this->disableFullGroupByIfNeeded($sql);

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params ?? []);

            /** @var array<int, array<string, mixed>> */
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            if (Config::env('APP_ENV') === 'testing') {
                throw $e;
            }
            echo 'SQL Error:' . $e->getMessage() . '<br>' . $sql;
            return [];
        }
    }

    private function disableFullGroupByIfNeeded(string $sql): void
    {
        if (str_contains(strtoupper($sql), 'GROUP BY') && !$this->groupByModeDisabled) {
            try {
                $this->pdo->exec(
                    "SET SESSION sql_mode=(SELECT REPLACE(@@SESSION.sql_mode,'ONLY_FULL_GROUP_BY',''))"
                );
                $this->groupByModeDisabled = true;
            } catch (PDOException $e) {
                error_log('Failed to disable ONLY_FULL_GROUP_BY: ' . $e->getMessage());
            }
        }
    }
}
