<?php

declare(strict_types=1);

namespace App\Security;

use App\Config;
use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;

/**
 * Symmetric encryption / decryption using Defuse Crypto.
 *
 * Two key types are supported:
 *  - "cookie"  → uses Config::$cookieKey  (for cookie values)
 *  - "decrypt" → uses Config::$decryptKey (for database-stored tokens)
 */
final class EncryptionService
{
    private readonly Config $config;

    public function __construct(?Config $config = null)
    {
        $this->config = $config ?? Config::getInstance();
    }

    /**
     * Encrypt a plaintext value.
     *
     * Returns empty string when the input is blank or no key is available.
     */
    public function encrypt(string $value, string $keyType = 'cookie'): string
    {
        if (trim($value) === '') {
            return '';
        }

        $key = $this->resolveKey($keyType);
        if ($key === null) {
            return '';
        }

        try {
            return Crypto::encrypt($value, $key);
        } catch (\Exception) {
            return '';
        }
    }

    /**
     * Decrypt a ciphertext value.
     *
     * Returns empty string when the input is blank, the key is missing,
     * or the ciphertext is invalid / tampered.
     */
    public function decrypt(string $value, string $keyType = 'cookie'): string
    {
        if (trim($value) === '') {
            return '';
        }

        $key = $this->resolveKey($keyType);
        if ($key === null) {
            return '';
        }

        try {
            return Crypto::decrypt($value, $key);
        } catch (\Exception) {
            return '';
        }
    }

    private function resolveKey(string $keyType): ?Key
    {
        return $keyType === 'decrypt'
            ? $this->config->decryptKey
            : $this->config->cookieKey;
    }
}
