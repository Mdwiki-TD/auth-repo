<?php

declare(strict_types=1);

namespace App\Http;

use App\Config;
use App\Security\EncryptionService;

/**
 * Read / write encrypted cookies.
 *
 * Cookie values are symmetrically encrypted before being sent to the browser
 * and decrypted on read.  The domain, secure, and httponly flags are derived
 * from the current Config.
 */
final class CookieManager
{
    private readonly Config $config;
    private readonly EncryptionService $encryption;

    public function __construct(?Config $config = null, ?EncryptionService $encryption = null)
    {
        $this->config     = $config ?? Config::getInstance();
        $this->encryption = $encryption ?? new EncryptionService($this->config);
    }

    /**
     * Set an encrypted cookie.
     *
     * @param int $age  Expiry timestamp; 0 = two years from now (legacy default).
     */
    public function set(string $name, string $value, int $age = 0): void
    {
        $twoYears = time() + 60 * 60 * 24 * 365 * 2;
        if ($age === 0) {
            $age = $twoYears;
        }

        $secure    = $this->config->domain !== 'localhost';
        $encrypted = $this->encryption->encrypt($value);

        setcookie(
            $name,
            $encrypted,
            $age,
            '/',
            $this->config->domain,
            $secure,
            $secure,
        );
    }

    /**
     * Read and decrypt a cookie value.
     *
     * Returns empty string when the cookie is absent or cannot be decrypted.
     */
    public function get(string $name): string
    {
        if (!isset($_COOKIE[$name])) {
            return '';
        }

        $value = $this->encryption->decrypt($_COOKIE[$name]);

        if ($name === 'username') {
            $value = str_replace('+', ' ', $value);
        }

        return $value;
    }

    /**
     * Expire (remove) a cookie.
     */
    public function remove(string $name): void
    {
        $secure = $this->config->domain !== 'localhost';

        setcookie($name, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'domain'   => $this->config->domain,
            'secure'   => $secure,
            'httponly'  => true,
            'samesite' => 'Lax',
        ]);
    }
}
