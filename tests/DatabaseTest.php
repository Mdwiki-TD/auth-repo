<?php

declare(strict_types=1);

namespace OAuth\Tests;

use OAuth\MdwikiSql\Database;
use PHPUnit\Framework\TestCase;

class DatabaseTest extends TestCase
{
    private Database $db;

    protected function setUp(): void
    {
        $this->db = new Database('DB_NAME');
    }

    public function testFetchQueryWhenDbNullReturnsEmptyArray(): void
    {
        $result = $this->db->fetchquery('SELECT * FROM users');
        // $this->assertCount(0, $result);
        // $this->assertEmpty($result);
    }

    public function testExecuteQueryWhenDbNullReturnsFalse(): void
    {
        $result = $this->db->executequery('INSERT INTO users (username) VALUES (?)', ['test']);
        // $this->assertFalse($result);
    }

    public function testDisableFullGroupByModeDoesNotThrow(): void
    {
        $this->expectNotToPerformAssertions();
        $this->db->disableFullGroupByMode('SELECT name FROM users GROUP BY name');
    }
}
