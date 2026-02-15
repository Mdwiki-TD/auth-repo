<?php
/**
 * JWT (JSON Web Token) Management for OAuth Authentication.
 *
 * This module provides creation and verification of JWT tokens using the
 * HS256 (HMAC-SHA256) algorithm via the firebase/php-jwt library.
 * Tokens are used for stateless authentication between the OAuth callback
 * and subsequent requests.
 *
 * @package    OAuth\JWT
 * @author     MDWiki Team
 * @copyright  2024 MDWiki
 * @license    MIT
 * @version    2.0.0
 * @since      1.0.0
 * @link       https://github.com/firebase/php-jwt
 *
 * @example Basic usage:
 * ```php
 * use function OAuth\JWT\create_jwt;
 * use function OAuth\JWT\verify_jwt;
 *
 * // Create a token after successful authentication
 * $token = create_jwt('JohnDoe');
 *
 * // Verify a token from the client
 * [$username, $error] = verify_jwt($token);
 * if ($error !== '') {
 *     // Token invalid or expired
 * }
 * ```
 *
 * @security JWT tokens contain the username but no sensitive OAuth credentials.
 *           OAuth access tokens are stored in the database, not in JWTs.
 *           Tokens expire after 1 hour (JWT_TTL constant).
 */

declare(strict_types=1);

namespace OAuth\JWT;

use Firebase\JWT\JWT;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\Key;
use Firebase\JWT\BeforeValidException;

/** @var int Token time-to-live in seconds (1 hour) */
const JWT_TTL = 3600;

/** @var string JWT algorithm for signing (HMAC-SHA256) */
const JWT_ALGORITHM = 'HS256';

/** @var string Path to vendor autoload, relative to this file */
const VENDOR_PATH = __DIR__ . '/../vendor/autoload.php';

/** @var string Path to config file, relative to this file */
const CONFIG_PATH = __DIR__ . '/config.php';

// Load dependencies
include_once VENDOR_PATH;
include_once CONFIG_PATH;

/**
 * Result tuple returned by verify_jwt().
 *
 * This is a tuple (array) with exactly two elements:
 * - Index 0 (string): The username from the token (empty string on failure)
 * - Index 1 (string): Error message (empty string on success)
 *
 * @typedef JwtVerifyResult
 * @type array{0: string, 1: string}
 *
 * @example Checking results:
 * ```php
 * [$username, $error] = verify_jwt($token);
 *
 * if ($error !== '') {
 *     // Failure cases:
 *     // - 'Token and JWT key are required'
 *     // - 'JWT token has expired'
 *     // - 'JWT token signature is invalid'
 *     // - 'Failed to verify JWT token: ...'
 * }
 * ```
 */

/**
 * Create a JWT token for an authenticated user.
 *
 * Generates a signed JWT containing the username with standard claims:
 * - `iss`: Issuer (the server domain)
 * - `iat`: Issued at timestamp
 * - `exp`: Expiration timestamp (1 hour from now)
 * - `username`: The authenticated user's Wikimedia username
 *
 * @param string $username The authenticated Wikimedia username to encode.
 *                         Should be the canonical username from OAuth identity.
 *
 * @return string The encoded JWT token string, or empty string on failure.
 *                Empty string indicates JWT encoding failure (check error logs).
 *
 * @global string $jwt_key The secret key for signing tokens (from config).
 * @global string $domain  The issuer domain for the 'iss' claim.
 *
 * @example
 * ```php
 * // After successful OAuth callback
 * $username = $ident->username; // From OAuth identity
 * $token = create_jwt($username);
 *
 * if ($token === '') {
 *     error_log('Failed to create JWT for user: ' . $username);
 *     // Handle error - show login page
 * }
 *
 * // Store token in cookie for subsequent requests
 * setcookie('jwt_token', $token, [
 *     'expires' => time() + 3600,
 *     'path' => '/',
 *     'secure' => true,
 *     'httponly' => true,
 *     'samesite' => 'Strict'
 * ]);
 * ```
 *
 * @see verify_jwt() For token verification.
 */
function create_jwt(string $username): string
{
    global $jwt_key, $domain;

    if (empty($jwt_key)) {
        error_log('[create_jwt] JWT key not configured');
        return '';
    }

    if (empty($username)) {
        error_log('[create_jwt] Username cannot be empty');
        return '';
    }

    $issuedAt = time();

    $payload = [
        'iss'      => $domain ?? 'mdwiki.toolforge.org',
        'iat'      => $issuedAt,
        'exp'      => $issuedAt + JWT_TTL,
        'username' => $username,
    ];

    try {
        return JWT::encode($payload, $jwt_key, JWT_ALGORITHM);
    } catch (\Exception $e) {
        error_log('[create_jwt] Failed to encode JWT: ' . $e->getMessage());
        return '';
    }
}

/**
 * Verify and decode a JWT token.
 *
 * Validates the token signature, checks expiration, and extracts the username.
 * Returns a tuple of [username, error] for easy result handling.
 *
 * @param string $token The JWT token string to verify.
 *                      Should be the raw token from the cookie or header.
 *
 * @return array{0: string, 1: string} Result tuple:
 *         - Success: `['JohnDoe', '']`
 *         - Failure: `['', 'Error message']`
 *
 * @global string $jwt_key The secret key for verifying tokens.
 *
 * @example
 * ```php
 * // Verify token from cookie
 * $token = $_COOKIE['jwt_token'] ?? '';
 * [$username, $error] = verify_jwt($token);
 *
 * if ($error !== '') {
 *     switch ($error) {
 *         case 'JWT token has expired':
 *             // Prompt user to re-authenticate
 *             break;
 *         case 'JWT token signature is invalid':
 *             // Potential tampering - log security event
 *             break;
 *         default:
 *             // Other verification failure
 *     }
 *     header('Location: /login');
 *     exit;
 * }
 *
 * // Token valid - user is authenticated
 * $_SESSION['username'] = $username;
 * ```
 *
 * @throws void This function catches all exceptions internally and returns
 *              error messages in the result tuple instead of throwing.
 *
 * @see create_jwt() For token creation.
 */
function verify_jwt(string $token): array
{
    global $jwt_key;

    if (empty($token)) {
        return ["", 'Token is required'];
    }

    if (empty($jwt_key)) {
        error_log('[verify_jwt] JWT key not configured');
        return ["", 'Token and JWT key are required'];
    }

    try {
        $decoded = JWT::decode($token, new Key($jwt_key, JWT_ALGORITHM));

        if (!isset($decoded->username) || !is_string($decoded->username)) {
            return ["", 'Token payload missing username'];
        }

        return [$decoded->username, ''];
    } catch (ExpiredException $e) {
        error_log('[verify_jwt] Token expired');
        return ["", 'JWT token has expired'];
    } catch (SignatureInvalidException $e) {
        error_log('[verify_jwt] Invalid signature: ' . $e->getMessage());
        return ["", 'JWT token signature is invalid'];
    } catch (BeforeValidException $e) {
        error_log('[verify_jwt] Token not yet valid: ' . $e->getMessage());
        return ["", 'JWT token is not yet valid'];
    } catch (\Exception $e) {
        error_log('[verify_jwt] Verification failed: ' . $e->getMessage());
        return ["", 'Failed to verify JWT token: ' . $e->getMessage()];
    }
}
