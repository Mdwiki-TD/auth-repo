<?php

declare(strict_types=1);

namespace App\Tests\Database;

use App\Database\DbNameResolver;
use PHPUnit\Framework\TestCase;

/**
 * Tests for DbNameResolver.
 */
class DbNameResolverTest extends TestCase
{
    public function testDefaultDatabaseIsDbName(): void
    {
        $this->assertSame('DB_NAME', DbNameResolver::resolve(null));
    }

    public function testUnknownTableUsesDefaultDb(): void
    {
        $this->assertSame('DB_NAME', DbNameResolver::resolve('unknown_table'));
    }

    public function testNewDbTablesResolveCorrectly(): void
    {
        $newDbTables = [
            'missing',
            'missing_by_qids',
            'exists_by_qids',
            'publish_reports',
            'login_attempts',
            'logins',
            'publish_reports_stats',
            'all_qids_titles',
        ];

        foreach ($newDbTables as $table) {
            $this->assertSame(
                'DB_NAME_NEW',
                DbNameResolver::resolve($table),
                "Table '{$table}' should resolve to DB_NAME_NEW"
            );
        }
    }

    public function testAccessKeysUsesDefaultDb(): void
    {
        $this->assertSame('DB_NAME', DbNameResolver::resolve('access_keys'));
    }

    public function testUsersUsesDefaultDb(): void
    {
        $this->assertSame('DB_NAME', DbNameResolver::resolve('users'));
    }
}
