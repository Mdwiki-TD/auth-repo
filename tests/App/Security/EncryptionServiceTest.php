<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Config;
use App\Security\EncryptionService;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the EncryptionService.
 */
class EncryptionServiceTest extends TestCase
{
    private EncryptionService $service;

    protected function setUp(): void
    {
        Config::resetInstance();
        $this->service = new EncryptionService(Config::getInstance());
    }

    protected function tearDown(): void
    {
        Config::resetInstance();
    }

    public function testEncryptReturnsEmptyStringForEmptyInput(): void
    {
        $this->assertSame('', $this->service->encrypt(''));
    }

    public function testEncryptReturnsEmptyStringForWhitespace(): void
    {
        $this->assertSame('', $this->service->encrypt('   '));
    }

    public function testDecryptReturnsEmptyStringForEmptyInput(): void
    {
        $this->assertSame('', $this->service->decrypt(''));
    }

    public function testDecryptReturnsEmptyStringForInvalidData(): void
    {
        $this->assertSame('', $this->service->decrypt('not_valid_ciphertext'));
    }

    public function testEncryptDecryptRoundTrip(): void
    {
        $original = 'test_value_123';
        $encrypted = $this->service->encrypt($original);

        $this->assertNotEmpty($encrypted);
        $this->assertNotEquals($original, $encrypted);

        $decrypted = $this->service->decrypt($encrypted);
        $this->assertSame($original, $decrypted);
    }

    public function testEncryptDecryptWithDecryptKeyType(): void
    {
        $original = 'secret_data';
        $encrypted = $this->service->encrypt($original, 'decrypt');

        $this->assertNotEmpty($encrypted);

        $decrypted = $this->service->decrypt($encrypted, 'decrypt');
        $this->assertSame($original, $decrypted);
    }

    public function testCookieKeyAndDecryptKeyProduceDifferentCiphertexts(): void
    {
        $original = 'same_value';
        $encryptedCookie  = $this->service->encrypt($original, 'cookie');
        $encryptedDecrypt = $this->service->encrypt($original, 'decrypt');

        $this->assertNotEquals($encryptedCookie, $encryptedDecrypt);
    }

    public function testCrossKeyDecryptionFails(): void
    {
        $original  = 'cross_key_test';
        $encrypted = $this->service->encrypt($original, 'cookie');

        // Decrypting with wrong key type returns empty
        $decrypted = $this->service->decrypt($encrypted, 'decrypt');
        $this->assertSame('', $decrypted);
    }

    /**
     * @dataProvider specialCharactersProvider
     */
    public function testSpecialCharactersRoundTrip(string $input): void
    {
        $encrypted = $this->service->encrypt($input);
        $decrypted = $this->service->decrypt($encrypted);
        $this->assertSame($input, $decrypted);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function specialCharactersProvider(): array
    {
        return [
            'email'   => ['test@example.com'],
            'plus'    => ['user+name'],
            'spaces'  => ['value with spaces'],
            'unicode' => ['unicode: مرحبا'],
            'symbols' => ['!@#$%^&*()'],
        ];
    }
}
