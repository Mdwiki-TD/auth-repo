<?php

declare(strict_types=1);

namespace OAuth\Services;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;

/**
 * Service for encrypting and decrypting values using Defuse Crypto.
 * 
 * Consolidates functionality from helps.php (en_code_value, de_code_value)
 * into a modern, testable class with proper dependency injection.
 */
final class EncryptionService
{
    private ?Key $cookieKey;
    private ?Key $decryptKey;

    public function __construct(?Key $cookieKey = null, ?Key $decryptKey = null)
    {
        $this->cookieKey = $cookieKey;
        $this->decryptKey = $decryptKey;
    }

    /**
     * Create instance from Settings singleton for backward compatibility.
     */
    public static function fromSettings(): self
    {
        $settings = \Settings::getInstance();
        return new self($settings->cookieKey, $settings->decryptKey);
    }

    /**
     * Encrypt a value using the specified key type.
     *
     * @param string $value The plain text value to encrypt
     * @param string $keyType Either "cookie" or "decrypt" to select the key
     * @return string The encrypted value, or empty string on failure
     */
    public function encrypt(string $value, string $keyType = 'cookie'): string
    {
        if (empty(trim($value))) {
            return '';
        }

        $key = $this->getKey($keyType);
        if ($key === null) {
            return '';
        }

        try {
            return Crypto::encrypt($value, $key);
        } catch (\Exception $e) {
            error_log('Encryption failed: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Decrypt a value using the specified key type.
     *
     * @param string $value The encrypted value to decrypt
     * @param string $keyType Either "cookie" or "decrypt" to select the key
     * @return string The decrypted value, or empty string on failure
     */
    public function decrypt(string $value, string $keyType = 'cookie'): string
    {
        if (empty(trim($value))) {
            return '';
        }

        $key = $this->getKey($keyType);
        if ($key === null) {
            return '';
        }

        try {
            return Crypto::decrypt($value, $key);
        } catch (\Exception $e) {
            // Silently fail - this is expected for invalid/corrupted data
            return '';
        }
    }

    /**
     * Get the appropriate encryption key based on type.
     */
    private function getKey(string $keyType): ?Key
    {
        return ($keyType === 'decrypt') ? $this->decryptKey : $this->cookieKey;
    }
}
