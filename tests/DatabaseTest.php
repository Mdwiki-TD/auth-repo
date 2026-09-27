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
    public function testDisableFullGroupByModeDoesNotThrow(): void
    {
        $this->expectNotToPerformAssertions();
        $this->db->disableFullGroupByMode('SELECT name FROM users GROUP BY name');
    }
}
