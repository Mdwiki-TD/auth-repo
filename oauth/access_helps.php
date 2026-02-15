<?php
/**
 * Legacy OAuth Access Token Storage (access_keys table).
 *
 * This module provides CRUD operations for OAuth access tokens using the
 * `access_keys` database table. All values are encrypted before storage
 * using the 'cookie' encryption key.
 *
 * @package    OAuth\AccessHelps
 * @author     MDWiki Team
 * @copyright  2024 MDWiki
 * @license    MIT
 * @version    2.0.0
 * @since      1.0.0
 * @deprecated Use OAuth\AccessHelpsNew instead (keys_new table with 'decrypt' key)
 *
 * @see \OAuth\AccessHelpsNew For the new implementation with improved encryption.
 *
 * @example Basic usage:
 * ```php
 * use function OAuth\AccessHelps\add_access_to_dbs;
 * use function OAuth\AccessHelps\get_access_from_dbs;
 * use function OAuth\AccessHelps\del_access_from_dbs;
 *
 * // Store tokens after OAuth callback
 * add_access_to_dbs('JohnDoe', $token->key, $token->secret);
 *
 * // Retrieve tokens for API calls
 * $access = get_access_from_dbs('JohnDoe');
 * if ($access !== null) {
 *     $token = new Token($access['access_key'], $access['access_secret']);
 * }
 *
 * // Delete tokens on logout
 * del_access_from_dbs('JohnDoe');
 * ```
 *
 * @security Access tokens are encrypted before database storage using
 *           AES-256 via defuse/php-encryption library.
 */

declare(strict_types=1);

namespace OAuth\AccessHelps;

use function OAuth\MdwikiSql\execute_queries;
use function OAuth\MdwikiSql\fetch_queries;
use function OAuth\Helps\de_code_value;
use function OAuth\Helps\en_code_value;

/** @var string Path to database module, relative to this file */
const MDWIKI_SQL_PATH = __DIR__ . '/mdwiki_sql.php';

/** @var string Path to config module, relative to this file */
const CONFIG_PATH = __DIR__ . '/config.php';

/** @var string Path to helps module, relative to this file */
const HELPS_PATH = __DIR__ . '/helps.php';

// Load dependencies
include_once MDWIKI_SQL_PATH;
include_once CONFIG_PATH;
include_once HELPS_PATH;

/**
 * Access token data structure returned by get_access_from_dbs().
 *
 * @typedef AccessToken
 * @type array{access_key: string, access_secret: string}
 */

/**
 * Store or update OAuth access credentials for a user.
 *
 * If the user already exists in the database, their credentials are updated.
 * Otherwise, a new record is created. Uses ON DUPLICATE KEY UPDATE for
 * atomic upsert operation.
 *
 * @param string $user         The Wikimedia username (case-sensitive).
 * @param string $accessKey    The OAuth access token key.
 * @param string $accessSecret The OAuth access token secret (sensitive).
 *
 * @return void
 *
 * @example
 * ```php
 * // After successful OAuth callback
 * $accessToken = $client->complete($requestToken, $verifier);
 * add_access_to_dbs(
 *     $ident->username,
 *     $accessToken->key,
 *     $accessToken->secret
 * );
 * ```
 *
 * @security Values are encrypted using en_code_value() before storage.
 *           Uses the 'cookie' encryption key for backward compatibility.
 *
 * @deprecated Migrate to add_access_to_dbs_new() which uses the 'decrypt' key.
 */
function add_access_to_dbs(
    string $user,
    string $accessKey,
    string $accessSecret
): void {
    $encryptedUser = trim($user);
    $encryptedKey = en_code_value($accessKey);
    $encryptedSecret = en_code_value($accessSecret);

    $query = <<<SQL
        INSERT INTO access_keys (user_name, access_key, access_secret)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE
            access_key = VALUES(access_key),
            access_secret = VALUES(access_secret),
            created_at = NOW()
    SQL;

    execute_queries($query, [
        $encryptedUser,
        $encryptedKey,
        $encryptedSecret
    ]);
}

/**
 * Retrieve OAuth access credentials for a user.
 *
 * Looks up the user's access token in the access_keys table and decrypts
 * the stored values. Returns null if the user is not found or if
 * decryption fails.
 *
 * @param string $user The Wikimedia username to look up (case-sensitive).
 *
 * @return array{access_key: string, access_secret: string}|null
 *         The access credentials array, or null if not found.
 *
 * @example
 * ```php
 * $access = get_access_from_dbs('JohnDoe');
 *
 * if ($access === null) {
 *     // User not found - may need to re-authenticate
 *     header('Location: /auth/index.php?a=login');
 *     exit;
 * }
 *
 * // Use the access token for API calls
 * $token = new Token($access['access_key'], $access['access_secret']);
 * $ident = $client->identify($token);
 * ```
 *
 * @deprecated Migrate to get_access_from_dbs_new() which uses the 'decrypt' key.
 */
function get_access_from_dbs(string $user): ?array
{
    $sanitizedUser = trim($user);

    $query = <<<SQL
        SELECT access_key, access_secret
        FROM access_keys
        WHERE user_name = ?
    SQL;

    $result = fetch_queries($query, [$sanitizedUser]);

    if (empty($result)) {
        return null;
    }

    $row = $result[0];
    $decryptedKey = de_code_value($row['access_key']);
    $decryptedSecret = de_code_value($row['access_secret']);

    if ($decryptedKey === '' || $decryptedSecret === '') {
        error_log("[get_access_from_dbs] Decryption failed for user: {$sanitizedUser}");
        return null;
    }

    return [
        'access_key'    => $decryptedKey,
        'access_secret' => $decryptedSecret,
    ];
}

/**
 * Delete OAuth access credentials for a user.
 *
 * Removes the user's access token from the database. This should be
 * called during logout to invalidate stored credentials.
 *
 * @param string $user The Wikimedia username to delete (case-sensitive).
 *
 * @return void
 *
 * @example
 * ```php
 * // During logout
 * $username = get_from_cookies('username');
 * if (!empty($username)) {
 *     del_access_from_dbs($username);
 * }
 * session_destroy();
 * ```
 *
 * @deprecated Migrate to del_access_from_dbs_new() which uses the keys_new table.
 */
function del_access_from_dbs(string $user): void
{
    $sanitizedUser = trim($user);

    $query = <<<SQL
        DELETE FROM access_keys WHERE user_name = ?
    SQL;

    execute_queries($query, [$sanitizedUser]);
}

/**
 * Add a user to the users table if they don't already exist.
 *
 * Creates a record in the `users` table for tracking purposes.
 * Uses INSERT ... SELECT ... WHERE NOT EXISTS pattern for atomic operation.
 *
 * @param string $userName The Wikimedia username to add.
 *
 * @return array<int, array<string, mixed>> Query result (typically empty for INSERT).
 *
 * @example
 * ```php
 * // After successful OAuth callback
 * sql_add_user($ident->username);
 * ```
 */
function sql_add_user(string $userName): array
{
    $query = <<<SQL
        INSERT INTO users (username)
        SELECT ?
        WHERE NOT EXISTS (
            SELECT 1 FROM users WHERE username = ?
        )
    SQL;

    return execute_queries($query, [$userName, $userName]);
}
