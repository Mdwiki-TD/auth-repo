<?php

declare(strict_types=1);

namespace App\Tests;

use App\Config;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the App\Config class.
 */
class ConfigTest extends TestCase
{
    protected function setUp(): void
    {
        Config::resetInstance();
    }

    protected function tearDown(): void
    {
        Config::resetInstance();
    }

    public function testGetInstanceReturnsSameInstance(): void
    {
        $a = Config::getInstance();
        $b = Config::getInstance();
        $this->assertSame($a, $b);
    }

    public function testDomainDefaultsToServerName(): void
    {
        $config = Config::getInstance();
        $this->assertEquals($_SERVER['SERVER_NAME'] ?? 'localhost', $config->domain);
    }

    public function testUserAgentIsSet(): void
    {
        $config = Config::getInstance();
        $this->assertStringContainsString('mdwiki', $config->userAgent);
    }

    public function testOauthUrlIsWikimediaOAuth(): void
    {
        $config = Config::getInstance();
        $this->assertStringContainsString('Special:OAuth', $config->oauthUrl);
    }

    public function testApiUrlIsDerivedFromOauthUrl(): void
    {
        $config = Config::getInstance();
        $this->assertStringContainsString('api.php', $config->apiUrl);
    }

    public function testConsumerKeyIsRead(): void
    {
        $config = Config::getInstance();
        $this->assertEquals('test_consumer_key', $config->consumerKey);
    }

    public function testConsumerSecretIsRead(): void
    {
        $config = Config::getInstance();
        $this->assertEquals('test_consumer_secret', $config->consumerSecret);
    }

    public function testJwtKeyIsRead(): void
    {
        $config = Config::getInstance();
        $this->assertNotEmpty($config->jwtKey);
    }

    public function testCookieKeyIsLoaded(): void
    {
        $config = Config::getInstance();
        $this->assertNotNull($config->cookieKey);
    }

    public function testDecryptKeyIsLoaded(): void
    {
        $config = Config::getInstance();
        $this->assertNotNull($config->decryptKey);
    }

    public function testEnvReadsFromGetenv(): void
    {
        $this->assertEquals('testing', Config::env('APP_ENV'));
    }

    public function testEnvReturnsEmptyForMissingKey(): void
    {
        $this->assertEquals('', Config::env('NONEXISTENT_KEY_' . time()));
    }

    public function testResetInstanceAllowsNewCreation(): void
    {
        $a = Config::getInstance();
        Config::resetInstance();
        $b = Config::getInstance();
        $this->assertNotSame($a, $b);
    }

    public function testWakeupThrowsException(): void
    {
        $config = Config::getInstance();
        $this->expectException(\RuntimeException::class);
        $config->__wakeup();
    }
}
