<?php

declare(strict_types=1);

namespace App\Tests\Http;

use App\Http\CookieManager;
use App\Config;
use App\Security\EncryptionService;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CookieManager.
 *
 * Since setcookie() cannot be tested without a real HTTP response,
 * these tests focus on the get() method behaviour (reading & decrypting).
 */
class CookieManagerTest extends TestCase
{
    private CookieManager $cookies;

    protected function setUp(): void
    {
        Config::resetInstance();
        $this->cookies = new CookieManager(Config::getInstance());
    }

    protected function tearDown(): void
    {
        Config::resetInstance();
        unset($_COOKIE['test_cookie'], $_COOKIE['username']);
    }

    public function testGetReturnsEmptyForMissingCookie(): void
    {
        $this->assertSame('', $this->cookies->get('nonexistent_cookie'));
    }

    public function testGetReturnsEmptyForInvalidEncryptedCookie(): void
    {
        $_COOKIE['test_cookie'] = 'not_valid_encrypted_data';
        $this->assertSame('', $this->cookies->get('test_cookie'));
    }

    public function testGetReplacePlusInUsername(): void
    {
        // Simulate encrypted cookie value
        $encryption = new EncryptionService(Config::getInstance());
        $encrypted = $encryption->encrypt('John+Doe');
        $_COOKIE['username'] = $encrypted;

        $result = $this->cookies->get('username');
        // The + in the decrypted value should be replaced with a space
        $this->assertSame('John Doe', $result);
    }

    public function testGetDecryptsValidCookieValue(): void
    {
        $encryption = new EncryptionService(Config::getInstance());
        $original = 'test_value_123';
        $_COOKIE['test_cookie'] = $encryption->encrypt($original);

        $this->assertSame($original, $this->cookies->get('test_cookie'));
    }
}
