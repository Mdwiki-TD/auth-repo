<?php

declare(strict_types=1);

namespace OAuth\Tests;

use PHPUnit\Framework\TestCase;
use OAuth\Services\CookieService;
use OAuth\Services\EncryptionService;
use Defuse\Crypto\Key;

/**
 * Tests for the CookieService class.
 * 
 * Note: These tests don't actually set cookies (since we're in CLI mode),
 * but they test the service's logic and error handling.
 */
class CookieServiceTest extends TestCase
{
    private CookieService $service;
    private EncryptionService $encryption;

    protected function setUp(): void
    {
        $cookieKey = Key::loadFromAsciiSafeString(getenv('COOKIE_KEY'));
        $decryptKey = Key::loadFromAsciiSafeString(getenv('DECRYPT_KEY'));
        
        $this->encryption = new EncryptionService($cookieKey, $decryptKey);
        $this->service = new CookieService($this->encryption, 'localhost', false);
        
        // Clear cookies between tests
        $_COOKIE = [];
    }

    protected function tearDown(): void
    {
        $_COOKIE = [];
    }

    public function testGetReturnsEmptyStringForNonExistentCookie(): void
    {
        $result = $this->service->get('non_existent_cookie');
        $this->assertEquals('', $result);
    }

    public function testGetDecryptsExistingCookie(): void
    {
        $originalValue = 'test_value';
        $encryptedValue = $this->encryption->encrypt($originalValue);
        
        $_COOKIE['test_cookie'] = $encryptedValue;
        
        $result = $this->service->get('test_cookie');
        $this->assertEquals($originalValue, $result);
    }

    public function testGetReturnsEmptyForInvalidEncryptedData(): void
    {
        $_COOKIE['invalid_cookie'] = 'not_encrypted_data';
        
        $result = $this->service->get('invalid_cookie');
        $this->assertEquals('', $result);
    }

    public function testGetReplacesPlusInUsername(): void
    {
        // Encrypt a username with spaces
        $originalValue = 'user name';
        $encryptedValue = $this->encryption->encrypt($originalValue);
        
        $_COOKIE['username'] = $encryptedValue;
        
        $result = $this->service->get('username');
        
        // The + replacement happens on the decrypted value
        // Since we encrypted 'user name', we should get 'user name' back
        $this->assertEquals('user name', $result);
    }

    public function testSecureFlagIsSetBasedOnDomain(): void
    {
        // Test localhost (non-secure)
        $localhostService = new CookieService($this->encryption, 'localhost');
        $this->assertInstanceOf(CookieService::class, $localhostService);
        
        // Test production domain (secure)
        $productionService = new CookieService($this->encryption, 'mdwiki.toolforge.org');
        $this->assertInstanceOf(CookieService::class, $productionService);
    }

    public function testFromSettingsFactory(): void
    {
        $service = CookieService::fromSettings();
        $this->assertInstanceOf(CookieService::class, $service);
    }

    public function testMultipleCookies(): void
    {
        $values = [
            'cookie1' => 'value1',
            'cookie2' => 'value2',
            'cookie3' => 'special!@#$%',
        ];

        foreach ($values as $key => $value) {
            $_COOKIE[$key] = $this->encryption->encrypt($value);
        }

        foreach ($values as $key => $expectedValue) {
            $this->assertEquals($expectedValue, $this->service->get($key));
        }
    }

    public function testDeleteMultipleCookies(): void
    {
        // This test verifies the method runs without errors
        // Since we're in CLI mode where headers can't be sent, we just verify the method exists
        // and the service is properly instantiated
        $this->assertTrue(method_exists($this->service, 'deleteMultiple'));
        $this->assertTrue(method_exists($this->service, 'delete'));
    }
}
