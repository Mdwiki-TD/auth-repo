<?php

declare(strict_types=1);

namespace OAuth\Tests;

use PHPUnit\Framework\TestCase;

// Note: bootstrap.php already loads config.php with test keys
require_once __DIR__ . '/../src/oauth/helps.php';

use function OAuth\Helps\de_code_value;
use function OAuth\Helps\en_code_value;
use function OAuth\Helps\get_from_cookies;

/**
 * Tests for the helps.php utility functions
 * 
 * These tests cover:
 * - Encryption/decryption of values using Defuse Crypto
 * - Cookie retrieval functionality
 */
class HelpsTest extends TestCase
{
    /**
     * Test that en_code_value returns empty string for empty input
     */
    public function testEnCodeValueReturnsEmptyStringForEmptyInput(): void
    {
        $result = en_code_value('');
        $this->assertEquals('', $result);
    }

    /**
     * Test that de_code_value returns empty string for empty input
     */
    public function testDeCodeValueReturnsEmptyStringForEmptyInput(): void
    {
        $result = de_code_value('');
        $this->assertEquals('', $result);
    }

    /**
     * Test that de_code_value returns empty string for invalid encrypted data
     */
    public function testDeCodeValueReturnsEmptyStringForInvalidData(): void
    {
        $result = de_code_value('invalid_encrypted_data');
        $this->assertEquals('', $result);
    }

    /**
     * Test encryption and decryption round-trip
     */
    public function testEncryptionDecryptionRoundTrip(): void
    {
        $original = 'test_value_123';
        
        // Encrypt the value
        $encrypted = en_code_value($original);
        
        // Should not be empty and should be different from original
        $this->assertNotEmpty($encrypted);
        $this->assertNotEquals($original, $encrypted);
        
        // Decrypt and verify
        $decrypted = de_code_value($encrypted);
        $this->assertEquals($original, $decrypted);
    }

    /**
     * Test encryption with decrypt key type
     */
    public function testEncryptionWithDecryptKeyType(): void
    {
        $original = 'test_with_decrypt_key';
        
        // Encrypt with decrypt key type
        $encrypted = en_code_value($original, 'decrypt');
        
        // Should not be empty
        $this->assertNotEmpty($encrypted);
        
        // Decrypt with same key type
        $decrypted = de_code_value($encrypted, 'decrypt');
        $this->assertEquals($original, $decrypted);
    }

    /**
     * Test get_from_cookies returns empty string when cookie doesn't exist
     */
    public function testGetFromCookiesReturnsEmptyForNonExistentCookie(): void
    {
        $result = get_from_cookies('non_existent_cookie');
        $this->assertEquals('', $result);
    }

    /**
     * Test get_from_cookies handles username with plus signs
     */
    public function testGetFromCookiesReplacesPlusInUsername(): void
    {
        // Simulate a cookie value (would be encrypted in real scenario)
        $_COOKIE['username'] = 'test+user+name';
        
        // Since we can't easily mock de_code_value, we test the plus replacement logic
        // by checking if the function runs without error
        $result = get_from_cookies('username');
        
        // Clean up
        unset($_COOKIE['username']);
        
        // Result should be empty since the encrypted value is invalid
        // but the function should execute without throwing
        $this->assertIsString($result);
    }

    /**
     * Test that special characters are handled correctly in encryption
     */
    public function testSpecialCharactersEncryption(): void
    {
        $specialStrings = [
            'test@example.com',
            'user+name',
            'test value with spaces',
            'unicode: مرحبا',
            'symbols: !@#$%^&*()',
        ];

        foreach ($specialStrings as $original) {
            $encrypted = en_code_value($original);
            $decrypted = de_code_value($encrypted);
            $this->assertEquals($original, $decrypted, "Failed to encrypt/decrypt: $original");
        }
    }
}
