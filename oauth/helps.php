<?php
/**
 * Cookie and Encryption Utilities for OAuth Authentication.
 *
 * This module provides secure cookie handling with AES-256 encryption using
 * the defuse/php-encryption library. All sensitive values stored in cookies
 * are encrypted before transmission and decrypted on retrieval.
 *
 * @package    OAuth\Helps
 * @author     MDWiki Team
 * @copyright  2024 MDWiki
 * @license    MIT
 * @version    2.0.0
 * @since      1.0.0
 * @link       https://github.com/defuse/php-encryption
 *
 * @example Basic usage:
 * ```php
 * use function OAuth\Helps\add_to_cookies;
 * use function OAuth\Helps\get_from_cookies;
 *
 * // Store encrypted value in cookie
 * add_to_cookies('username', 'JohnDoe');
 *
 * // Retrieve and decrypt value
 * $username = get_from_cookies('username');
 * ```
 *
 * @security All cookie values are encrypted using AES-256-CBC with HMAC authentication.
 *           Cookies are set with HttpOnly and Secure flags (except on localhost).
 */

declare(strict_types=1);

namespace OAuth\Helps;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use RuntimeException;

/** @var string Path to vendor autoload, relative to this file */
const VENDOR_PATH = __DIR__ . '/../vendor/autoload.php';

/** @var string Path to config file, relative to this file */
const CONFIG_PATH = __DIR__ . '/config.php';

// Load dependencies
include_once VENDOR_PATH;
include_once CONFIG_PATH;

/**
 * Decrypt an encrypted value using the specified encryption key.
 *
 * Uses AES-256-CBC with HMAC authentication via defuse/php-encryption.
 * The function silently catches decryption failures and returns an empty string
 * to prevent information leakage through error messages.
 *
 * @param string $value    The encrypted value (base64-encoded ciphertext).
 * @param string $key_type The key type to use for decryption:
 *                         - "cookie": Uses $cookie_key (for cookie storage)
 *                         - "decrypt": Uses $decrypt_key (for database storage)
 *
 * @return string The decrypted plaintext value, or empty string on:
 *                - Empty input value
 *                - Decryption failure (invalid ciphertext, wrong key, tampering)
 *                - Missing encryption key
 *
 * @global Key $cookie_key  Encryption key for cookie values (loaded from config).
 * @global Key $decrypt_key Encryption key for database values (loaded from config).
 *
 * @example
 * ```php
 * // Decrypt a cookie value (default)
 * $username = de_code_value($_COOKIE['username']);
 *
 * // Decrypt a database-stored value
 * $decrypted = de_code_value($row['access_key'], 'decrypt');
 * ```
 *
 * @internal Exceptions are caught and logged to error_log() to prevent
 *           information disclosure via error messages.
 */
function de_code_value(string $value, string $key_type = "cookie"): string
{
    global $cookie_key, $decrypt_key;

    if (empty(trim($value))) {
        return "";
    }

    $use_key = ($key_type === "decrypt") ? $decrypt_key : $cookie_key;

    if (!$use_key instanceof Key) {
        error_log("[de_code_value] Encryption key not initialized for type: {$key_type}");
        return "";
    }

    try {
        return Crypto::decrypt($value, $use_key);
    } catch (\Exception $e) {
        error_log("[de_code_value] Decryption failed: " . $e->getMessage());
        return "";
    }
}

/**
 * Encrypt a plaintext value using the specified encryption key.
 *
 * Uses AES-256-CBC with HMAC authentication via defuse/php-encryption.
 * The resulting ciphertext is base64-encoded for safe storage.
 *
 * @param string $value    The plaintext value to encrypt.
 * @param string $key_type The key type to use for encryption:
 *                         - "cookie": Uses $cookie_key (for cookie storage)
 *                         - "decrypt": Uses $decrypt_key (for database storage)
 *
 * @return string The encrypted value (base64-encoded ciphertext), or empty string on:
 *                - Empty input value
 *                - Encryption failure
 *                - Missing encryption key
 *
 * @global Key $cookie_key  Encryption key for cookie values.
 * @global Key $decrypt_key Encryption key for database values.
 *
 * @example
 * ```php
 * // Encrypt for cookie storage (default)
 * $encrypted = en_code_value('sensitive_data');
 * setcookie('my_cookie', $encrypted, time() + 3600, '/');
 *
 * // Encrypt for database storage
 * $dbValue = en_code_value($accessToken, 'decrypt');
 * $db->query("INSERT INTO tokens (token) VALUES (?)", [$dbValue]);
 * ```
 */
function en_code_value(string $value, string $key_type = "cookie"): string
{
    global $cookie_key, $decrypt_key;

    $use_key = ($key_type === "decrypt") ? $decrypt_key : $cookie_key;

    if (!$use_key instanceof Key) {
        error_log("[en_code_value] Encryption key not initialized for type: {$key_type}");
        return "";
    }

    if (empty(trim($value))) {
        return "";
    }

    try {
        return Crypto::encrypt($value, $use_key);
    } catch (\Exception $e) {
        error_log("[en_code_value] Encryption failed: " . $e->getMessage());
        return "";
    }
}

/**
 * Store an encrypted value in an HTTP cookie with security attributes.
 *
 * Creates a cookie with the following security attributes:
 * - **HttpOnly**: Not accessible via JavaScript (mitigates XSS)
 * - **Secure**: HTTPS only (except on localhost for development)
 * - **SameSite**: Strict (via path restriction)
 * - **Encrypted**: Value is AES-256 encrypted before storage
 *
 * @param string $key   The cookie name (alphanumeric, underscores allowed).
 * @param string $value The plaintext value to store (will be encrypted).
 * @param int    $age   Cookie lifetime in seconds from now.
 *                      Default 0 = 2 years (63,072,000 seconds).
 *
 * @return void
 *
 * @global string $domain The domain for the cookie (e.g., 'mdwiki.toolforge.org').
 *
 * @example
 * ```php
 * // Store username for 2 years (default)
 * add_to_cookies('username', 'JohnDoe');
 *
 * // Store session token for 1 hour
 * add_to_cookies('session_token', $token, 3600);
 *
 * // Store temporary CSRF token for 30 minutes
 * add_to_cookies('csrf_token', bin2hex(random_bytes(32)), 1800);
 * ```
 *
 * @see get_from_cookies() For retrieving stored values.
 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/Cookies
 */
function add_to_cookies(string $key, string $value, int $age = 0): void
{
    global $domain;

    $expiry = ($age === 0)
        ? time() + (60 * 60 * 24 * 365 * 2)
        : time() + $age;

    $secure = ($_SERVER['SERVER_NAME'] ?? '') !== "localhost";
    $encryptedValue = en_code_value($value);

    if ($encryptedValue === "") {
        error_log("[add_to_cookies] Failed to encrypt value for cookie: {$key}");
        return;
    }

    setcookie(
        $key,
        $encryptedValue,
        [
            'expires'  => $expiry,
            'path'     => "/",
            'domain'   => $domain ?? '',
            'secure'   => $secure,
            'httponly' => $secure,
            'samesite' => 'Strict'
        ]
    );
}

/**
 * Retrieve and decrypt a value from HTTP cookies.
 *
 * Safely retrieves a cookie value, decrypts it, and handles the special
 * case of username cookies where plus signs are replaced with spaces
 * (workaround for historical URL encoding issues).
 *
 * @param string $key The cookie name to retrieve.
 *
 * @return string The decrypted cookie value, or empty string if:
 *                - Cookie does not exist
 *                - Decryption fails
 *                - Cookie value is malformed
 *
 * @example
 * ```php
 * $username = get_from_cookies('username');
 * if ($username === '') {
 *     // User not authenticated or cookie corrupted
 *     header('Location: /login');
 *     exit;
 * }
 * echo "Welcome, " . htmlspecialchars($username);
 * ```
 *
 * @note For 'username' cookies, plus signs (+) are replaced with spaces.
 *       This is a workaround for URL encoding inconsistencies in some
 *       MediaWiki username formats (e.g., usernames with spaces).
 *
 * @see add_to_cookies() For storing values.
 */
function get_from_cookies(string $key): string
{
    if (!isset($_COOKIE[$key])) {
        return "";
    }

    $cookieValue = is_string($_COOKIE[$key]) ? $_COOKIE[$key] : "";
    $value = de_code_value($cookieValue);

    if ($key === "username") {
        $value = str_replace("+", " ", $value);
    }

    return $value;
}
