<?php

declare(strict_types=1);

namespace OAuth\Services;

/**
 * Service for managing encrypted cookies.
 * 
 * Consolidates cookie functionality from helps.php (add_to_cookies, get_from_cookies)
 * into a modern, testable class.
 */
final class CookieService
{
    private const TWO_YEARS_SECONDS = 60 * 60 * 24 * 365 * 2;

    private EncryptionService $encryption;
    private string $domain;
    private bool $secure;

    public function __construct(
        EncryptionService $encryption,
        string $domain = 'localhost',
        ?bool $secure = null
    ) {
        $this->encryption = $encryption;
        $this->domain = $domain;
        $this->secure = $secure ?? ($domain !== 'localhost');
    }

    /**
     * Create instance from Settings singleton for backward compatibility.
     */
    public static function fromSettings(): self
    {
        $settings = \Settings::getInstance();
        return new self(
            EncryptionService::fromSettings(),
            $settings->domain
        );
    }

    /**
     * Set an encrypted cookie.
     *
     * @param string $key The cookie name
     * @param string $value The value to store (will be encrypted)
     * @param int $maxAge Cookie expiration time in seconds (0 = 2 years)
     * @return bool True if cookie was set successfully
     */
    public function set(string $key, string $value, int $maxAge = 0): bool
    {
        $expiry = ($maxAge === 0) ? time() + self::TWO_YEARS_SECONDS : $maxAge;
        $encryptedValue = $this->encryption->encrypt($value);

        if ($encryptedValue === '' && $value !== '') {
            return false;
        }

        return setcookie(
            $key,
            $encryptedValue,
            $expiry,
            '/',
            $this->domain,
            $this->secure,
            $this->secure
        );
    }

    /**
     * Get and decrypt a cookie value.
     *
     * @param string $key The cookie name
     * @return string The decrypted value, or empty string if not found/invalid
     */
    public function get(string $key): string
    {
        if (!isset($_COOKIE[$key])) {
            return '';
        }

        $value = $this->encryption->decrypt($_COOKIE[$key]);

        // Handle username special case: replace + with space
        if ($key === 'username') {
            $value = str_replace('+', ' ', $value);
        }

        return $value;
    }

    /**
     * Delete a cookie by setting its expiration in the past.
     *
     * @param string $key The cookie name to delete
     * @return bool True if the deletion cookie was set
     */
    public function delete(string $key): bool
    {
        return setcookie($key, '', [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => $this->domain,
            'secure' => $this->secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    /**
     * Delete multiple cookies at once.
     *
     * @param array<string> $keys The cookie names to delete
     */
    public function deleteMultiple(array $keys): void
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
    }
}
