<?php

declare(strict_types=1);

namespace OAuth\Tests;

use PHPUnit\Framework\TestCase;
use OAuth\Actions\BaseAction;

/**
 * Concrete implementation of BaseAction for testing.
 */
class TestableBaseAction extends BaseAction
{
    public function execute(): void
    {
        // No-op for testing
    }

    // Expose protected methods for testing
    public function testCreateState(array $keys): array
    {
        return $this->createState($keys);
    }

    public function testCreateReturnTo(string $httpReferer): string
    {
        return $this->createReturnTo($httpReferer);
    }

    public function testIsDevelopment(): bool
    {
        return $this->isDevelopment();
    }
}

/**
 * Tests for the BaseAction class.
 */
class BaseActionTest extends TestCase
{
    private TestableBaseAction $action;

    protected function setUp(): void
    {
        $this->action = new TestableBaseAction();
        
        // Clear GET parameters between tests
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_GET = [];
    }

    public function testCreateStateReturnsEmptyArrayForNoMatchingKeys(): void
    {
        $_GET = ['other' => 'value'];
        
        $state = $this->action->testCreateState(['cat', 'code']);
        
        $this->assertEquals([], $state);
    }

    public function testCreateStateReturnsMatchingKeys(): void
    {
        $_GET = [
            'cat' => 'test_category',
            'code' => 'test_code',
            'other' => 'should_not_appear',
        ];
        
        $state = $this->action->testCreateState(['cat', 'code']);
        
        $this->assertArrayHasKey('cat', $state);
        $this->assertArrayHasKey('code', $state);
        $this->assertArrayNotHasKey('other', $state);
    }

    public function testCreateStateIgnoresEmptyValues(): void
    {
        $_GET = [
            'cat' => 'value',
            'code' => '',
        ];
        
        $state = $this->action->testCreateState(['cat', 'code']);
        
        $this->assertArrayHasKey('cat', $state);
        $this->assertArrayNotHasKey('code', $state);
    }

    public function testCreateReturnToReturnsEmptyForEmptyReferer(): void
    {
        $result = $this->action->testCreateReturnTo('');
        $this->assertEquals('', $result);
    }

    public function testCreateReturnToReturnsEmptyForDisallowedDomain(): void
    {
        $result = $this->action->testCreateReturnTo('https://evil.com/page');
        $this->assertEquals('', $result);
    }

    public function testCreateReturnToAllowsLocalhostDomain(): void
    {
        $url = 'http://localhost/some/page';
        $result = $this->action->testCreateReturnTo($url);
        $this->assertEquals($url, $result);
    }

    public function testCreateReturnToAllowsMdwikiDomain(): void
    {
        $url = 'https://mdwiki.toolforge.org/some/page';
        $result = $this->action->testCreateReturnTo($url);
        $this->assertEquals($url, $result);
    }

    public function testCreateReturnToRejectsAuthPaths(): void
    {
        $url = 'https://mdwiki.toolforge.org/auth/login';
        $result = $this->action->testCreateReturnTo($url);
        $this->assertEquals('', $result);
    }

    public function testCreateReturnToRejectsAuthPathsOnLocalhost(): void
    {
        $url = 'http://localhost/auth/callback';
        $result = $this->action->testCreateReturnTo($url);
        $this->assertEquals('', $result);
    }

    public function testIsDevelopmentReturnsTrueForLocalhost(): void
    {
        // The Settings singleton should have localhost as domain in test environment
        $result = $this->action->testIsDevelopment();
        
        // In test environment, SERVER_NAME is set to 'localhost' in bootstrap.php
        $this->assertTrue($result);
    }
}
