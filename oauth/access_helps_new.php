<?php
/**
 * OAuth Access Token Storage (keys_new table).
 *
 * This module provides CRUD operations for OAuth access tokens using the
 * `keys_new` database table with enhanced encryption. All values are
 * encrypted before storage using the 'decrypt' encryption key, which
 * provides stronger isolation from cookie-based encryption.
 *
 * @package    OAuth\AccessHelpsNew
 * @author     MDWiki Team
 * @copyright  2024 MDWiki
 * @license    MIT
 * @version    2.0.0
 * @since      1.0.0
 *
 * @see \OAuth\AccessHelps For the legacy implementation (deprecated).
 *
 * @example Basic usage:
 * ```php
 * use function OAuth\AccessHelpsNew\add_access_to_dbs_new;
 * use function OAuth\AccessHelpsNew\get_access_from_dbs_new;
 * use function OAuth\AccessHelpsNew\del_access_from_dbs_new;
 *
 * // Store tokens after OAuth callback
 * add_access_to_dbs_new('JohnDoe', $token->key, $token->secret);
 *
 * // Retrieve tokens for API calls
 * $access = get_access_from_dbs_new('JohnDoe');
 * if ($access !== null) {
 *     $token = new Token($access['access_key'], $access['access_secret']);
 * }
 *
 * // Delete tokens on logout
 * del_access_from_dbs_new('JohnDoe');
 * ```
 *
 * @security Access tokens are encrypted using AES-256 via defuse/php-encryption.
 *           Usernames are also encrypted for privacy compliance (GDPR).
 *
 * @performance User ID lookups are cached in memory per request.
 *              WARNING: get_user_id() loads the entire keys_new table.
 *              Consider adding an indexed lookup column for large-scale deployments.
 */

declare(strict_types=1);

namespace OAuth\AccessHelpsNew;

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
 * In-memory cache for user ID lookups.
 *
 * Maps usernames to their database IDs to avoid redundant lookups
 * within a single request. Cache is per-request (not persistent).
 *
 * @var array<string, int|null>
 */
$user_ids_cache = [];

/**
 * Access token data structure returned by get_access_from_dbs_new().
 *
 * @typedef AccessToken
 * @type array{access_key: string, access_secret: string}
 */

/**
 * Look up a user's database ID by username.
 *
 * Queries the keys_new table and decrypts usernames to find a match.
 * Results are cached in memory for the duration of the request.
 *
 * **Performance Warning:** This function loads ALL rows from keys_new
 * and decrypts each username until a match is found. This is O(n) where
 * n is the total number of users. For production with many users,
 * consider adding an indexed lookup column with deterministic encryption.
 *
 * @param string $user The Wikimedia username to look up (case-sensitive).
 *
 * @return int|null The user's database ID, or null if not found.
 *
 * @internal This function is not exported; use get_access_from_dbs_new() instead.
 *
 * @example
 * ```php
 * $userId = get_user_id('JohnDoe');
 * if ($userId === null) {
 *     // User not in database - needs to authenticate
 * }
 * ```
 *
 * @performance First call: O(n) where n = total users in keys_new.
 *              Subsequent calls for same user: O(1) from cache.
 */
function get_user_id(string $user): ?int
{
    global $user_ids_cache;

    $sanitizedUser = trim($user);

    if (isset($user_ids_cache[$sanitizedUser])) {
        return $user_ids_cache[$sanitizedUser];
    }

    $query = "SELECT id, u_n FROM keys_new";
    $result = fetch_queries($query);

    if (empty($result)) {
        $user_ids_cache[$sanitizedUser] = null;
        return null;
    }

    foreach ($result as $row) {
        $decryptedUsername = de_code_value($row['u_n'], 'decrypt');

        if ($decryptedUsername === $sanitizedUser) {
            $userId = (int) $row['id'];
            $user_ids_cache[$sanitizedUser] = $userId;
            return $userId;
        }
    }

    $user_ids_cache[$sanitizedUser] = null;
    return null;
}

/**
 * Store or update OAuth access credentials for a user.
 *
 * If the user exists (found via get_user_id()), updates their credentials.
 * Otherwise, inserts a new record with encrypted username and tokens.
 * All values are encrypted using the 'decrypt' key for database storage.
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
 *
 * // Store in new table (preferred)
 * add_access_to_dbs_new(
 *     $ident->username,
 *     $accessToken->key,
 *     $accessToken->secret
 * );
 *
 * // Also store in legacy table for backward compatibility
 * add_access_to_dbs(
 *     $ident->username,
 *     $accessToken->key,
 *     $accessToken->secret
 * );
 * ```
 *
 * @security All values encrypted using en_code_value() with 'decrypt' key.
 *           Username encryption provides GDPR-compliant pseudonymization.
 */
function add_access_to_dbs_new(
    string $user,
    string $accessKey,
    string $accessSecret
): void {
    $encryptedUser = en_code_value(trim($user), 'decrypt');
    $encryptedKey = en_code_value($accessKey, 'decrypt');
    $encryptedSecret = en_code_value($accessSecret, 'decrypt');

    $userId = get_user_id($user);

    if ($userId !== null) {
        $query = <<<SQL
            UPDATE keys_new
            SET a_k = ?, a_s = ?, created_at = NOW()
            WHERE id = ?
        SQL;

        execute_queries($query, [
            $encryptedKey,
            $encryptedSecret,
            $userId
        ]);
    } else {
        $query = <<<SQL
            INSERT INTO keys_new (u_n, a_k, a_s)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                a_k = VALUES(a_k),
                a_s = VALUES(a_s),
                created_at = NOW()
        SQL;

        execute_queries($query, [
            $encryptedUser,
            $encryptedKey,
            $encryptedSecret
        ]);
    }
}

/**
 * Retrieve OAuth access credentials for a user.
 *
 * Looks up the user's ID, fetches the encrypted tokens, and decrypts them.
 * Returns null if the user is not found or if decryption fails.
 *
 * @param string $user The Wikimedia username to look up (case-sensitive).
 *
 * @return array{access_key: string, access_secret: string}|null
 *         The access credentials array, or null if not found.
 *
 * @example
 * ```php
 * $access = get_access_from_dbs_new('JohnDoe');
 *
 * if ($access === null) {
 *     // Try legacy table as fallback
 *     $access = get_access_from_dbs('JohnDoe');
 * }
 *
 * if ($access !== null) {
 *     $token = new Token($access['access_key'], $access['access_secret']);
 *     $ident = $client->identify($token);
 * }
 * ```
 */
function get_access_from_dbs_new(string $user): ?array
{
    $sanitizedUser = trim($user);
    $userId = get_user_id($sanitizedUser);

    if ($userId === null) {
        return null;
    }

    $query = <<<SQL
        SELECT a_k, a_s
        FROM keys_new
        WHERE id = ?
    SQL;

    $result = fetch_queries($query, [$userId]);

    if (empty($result)) {
        return null;
    }

    $row = $result[0];
    $decryptedKey = de_code_value($row['a_k'], 'decrypt');
    $decryptedSecret = de_code_value($row['a_s'], 'decrypt');

    if ($decryptedKey === '' || $decryptedSecret === '') {
        error_log("[get_access_from_dbs_new] Decryption failed for user ID: {$userId}");
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
 * Looks up the user's ID and removes their record from keys_new.
 * Returns false if the user is not found (no deletion performed).
 *
 * @param string $user The Wikimedia username to delete (case-sensitive).
 *
 * @return bool True if deleted, false if user not found.
 *
 * @example
 * ```php
 * // During logout
 * $username = get_from_cookies('username');
 * if (!empty($username)) {
 *     $deleted = del_access_from_dbs_new($username);
 *     if (!$deleted) {
 *         error_log("User not found in keys_new: {$username}");
 *     }
 * }
 * session_destroy();
 * ```
 */
function del_access_from_dbs_new(string $user): bool
{
    $sanitizedUser = trim($user);
    $userId = get_user_id($sanitizedUser);

    if ($userId === null) {
        return false;
    }

    $query = <<<SQL
        DELETE FROM keys_new WHERE id = ?
    SQL;

    execute_queries($query, [$userId]);

    return true;
}
