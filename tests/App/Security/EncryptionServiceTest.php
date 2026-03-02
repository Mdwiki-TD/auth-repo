<?php

declare(strict_types=1);

namespace OAuth\Tests;

use PHPUnit\Framework\TestCase;
use OAuth\Services\EncryptionService;
use Defuse\Crypto\Key;

/**
 * Tests for the EncryptionService class.
 */
class EncryptionServiceTest extends TestCase
{
    private EncryptionService $service;
    private Key $cookieKey;
    private Key $decryptKey;

    protected function setUp(): void
    {
        // Generate test keys
        $this->cookieKey = Key::loadFromAsciiSafeString(getenv('COOKIE_KEY'));
        $this->decryptKey = Key::loadFromAsciiSafeString(getenv('DECRYPT_KEY'));
        
        $this->service = new EncryptionService($this->cookieKey, $this->decryptKey);
    }

    public function testEncryptReturnsEmptyStringForEmptyInput(): void
    {
        $result = $this->service->encrypt('');
        $this->assertEquals('', $result);
    }

    public function testEncryptReturnsEmptyStringForWhitespaceOnly(): void
    {
        $result = $this->service->encrypt('   ');
        $this->assertEquals('', $result);
    }

    public function testDecryptReturnsEmptyStringForEmptyInput(): void
    {
        $result = $this->service->decrypt('');
        $this->assertEquals('', $result);
    }

    public function testDecryptReturnsEmptyStringForInvalidData(): void
    {
        $result = $this->service->decrypt('invalid_encrypted_data');
        $this->assertEquals('', $result);
    }

    public function testEncryptionDecryptionRoundTrip(): void
    {
        $original = 'test_value_123';
        
        $encrypted = $this->service->encrypt($original);
        
        $this->assertNotEmpty($encrypted);
        $this->assertNotEquals($original, $encrypted);
        
        $decrypted = $this->service->decrypt($encrypted);
        $this->assertEquals($original, $decrypted);
    }

    public function testEncryptionWithDecryptKeyType(): void
    {
        $original = 'test_with_decrypt_key';
        
        $encrypted = $this->service->encrypt($original, 'decrypt');
        
        $this->assertNotEmpty($encrypted);
        
        $decrypted = $this->service->decrypt($encrypted, 'decrypt');
        $this->assertEquals($original, $decrypted);
    }

    public function testCookieKeyIsDefault(): void
    {
        $original = 'cookie_key_test';
        
        // Encrypt with default (cookie) key
        $encrypted = $this->service->encrypt($original);
        
        // Should NOT decrypt with 'decrypt' key type
        $decryptedWithWrongKey = $this->service->decrypt($encrypted, 'decrypt');
        $this->assertNotEquals($original, $decryptedWithWrongKey);
        
        // Should decrypt with 'cookie' key type
        $decryptedWithCorrectKey = $this->service->decrypt($encrypted, 'cookie');
        $this->assertEquals($original, $decryptedWithCorrectKey);
    }

    public function testSpecialCharactersEncryption(): void
    {
        $specialStrings = [
            'test@example.com',
            'user+name',
            'test value with spaces',
            'unicode: مرحبا',
            'symbols: !@#$%^&*()',
            'newlines: line1\nline2',
        ];

        foreach ($specialStrings as $original) {
            $encrypted = $this->service->encrypt($original);
            $decrypted = $this->service->decrypt($encrypted);
            $this->assertEquals($original, $decrypted, "Failed for: $original");
        }
    }

    public function testEncryptionProducesDifferentCiphertextEachTime(): void
    {
        $original = 'same_value';
        
        $encrypted1 = $this->service->encrypt($original);
        $encrypted2 = $this->service->encrypt($original);
        
        // Due to random IV, encryptions should be different
        $this->assertNotEquals($encrypted1, $encrypted2);
        
        // But both should decrypt to the same value
        $this->assertEquals($original, $this->service->decrypt($encrypted1));
        $this->assertEquals($original, $this->service->decrypt($encrypted2));
    }

    public function testServiceWithNullKeysReturnsEmptyString(): void
    {
        $serviceWithNoKeys = new EncryptionService(null, null);
        
        $this->assertEquals('', $serviceWithNoKeys->encrypt('test'));
        $this->assertEquals('', $serviceWithNoKeys->decrypt('test'));
    }

    public function testFromSettingsFactory(): void
    {
        // This tests the factory method with actual Settings
        $service = EncryptionService::fromSettings();
        
        $original = 'factory_test';
        $encrypted = $service->encrypt($original);
        $decrypted = $service->decrypt($encrypted);
        
        $this->assertEquals($original, $decrypted);
    }
}
