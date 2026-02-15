<?php

/**
 * OAuth Configuration Loader.
 *
 * This module loads OAuth credentials and encryption keys from an INI
 * configuration file. Configuration is exposed as global variables for
 * backward compatibility with legacy code.
 *
 * @package    OAuth
 * @author     MDWiki Team
 * @copyright  2024 MDWiki
 * @license    MIT
 * @version    2.0.0
 * @since      1.0.0
 *
 * @example Configuration file format (OAuthConfig.ini):
 * ```ini
 * agent = "My Tool Name"
 * consumerKey = "your-consumer-key"
 * consumerSecret = "your-consumer-secret"
 * consumerKey_new = "new-consumer-key"
 * consumerSecrety_new = "new-consumer-secret"
 * cookie_key = "defuse-key-for-cookies"
 * decrypt_key = "defuse-key-for-database"
 * jwt_key = "jwt-signing-secret"
 * ```
 *
 * @security Configuration file should be stored outside web root at:
 *           ~/confs/OAuthConfig.ini (on Toolforge)
 *           or I:/mdwiki/mdwiki/confs/OAuthConfig.ini (development)
 *
 * @deprecated Global variables should be replaced with a Config class.
 *             See refactor.md for migration plan.
 */

declare(strict_types=1);

use Defuse\Crypto\Key;

/** @var string Path to vendor autoload */
require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Determine the home directory for configuration files.
 *
 * On Toolforge/Linux, uses the HOME environment variable.
 * On Windows development, falls back to hardcoded path.
 *
 * @var string
 *
 * @todo Remove hardcoded Windows path in production.
 */
$ROOT_PATH = getenv("HOME") ?: 'I:/mdwiki/mdwiki';

/**
 * Path to the OAuth configuration INI file.
 *
 * @var string
 */
$inifile = $ROOT_PATH . '/confs/OAuthConfig.ini';

/**
 * Parse the INI configuration file.
 *
 * @var array<string, string>|false
 */
$ini = @parse_ini_file($inifile);

// Validate configuration was loaded
if ($ini === false) {
    header("HTTP/1.1 500 Internal Server Error");
    error_log("[config.php] Failed to parse INI file: {$inifile}");
    echo "Configuration could not be read. Please contact the administrator.";
    exit(1);
}

// Validate required OAuth credentials
if (
    !isset($ini['agent']) ||
    !isset($ini['consumerKey']) ||
    !isset($ini['consumerSecret'])
) {
    header("HTTP/1.1 500 Internal Server Error");
    error_log("[config.php] Missing required configuration keys");
    echo 'Required configuration directives not found in ini file';
    exit(1);
}

/**
 * User agent string for OAuth requests.
 *
 * Identifies the application to Wikimedia servers.
 *
 * @var string
 *
 * @deprecated Use a constant or config class property instead.
 */
$gUserAgent = 'mdwiki MediaWiki OAuth Client/1.0';

/**
 * Wikimedia OAuth endpoint URL.
 *
 * Points to Meta-Wiki's OAuth endpoint for central authentication.
 * Must use the long form with 'title=Special:OAuth'.
 *
 * @var string
 */
$oauthUrl = 'https://meta.wikimedia.org/w/index.php?title=Special:OAuth';

/**
 * MediaWiki API URL derived from OAuth URL.
 *
 * Replaces 'index.php' with 'api.php' in the OAuth URL.
 *
 * @var string
 */
$apiUrl = preg_replace('/index\.php.*/', 'api.php', $oauthUrl) ?: $oauthUrl;

/**
 * OAuth 1.0a consumer key (primary).
 *
 * Obtained when registering the OAuth consumer on Meta-Wiki.
 *
 * @var string
 */
$consumerKey = $ini['consumerKey'] ?? '';

/**
 * OAuth 1.0a consumer secret (primary).
 *
 * Private secret corresponding to the consumer key. Keep this secure!
 *
 * @var string
 *
 * @security Never expose this value in client-side code or logs.
 */
$consumerSecret = $ini['consumerSecret'] ?? '';

/**
 * OAuth 1.0a consumer key (secondary/new).
 *
 * Used for gradual migration to new OAuth consumer.
 *
 * @var string
 */
$consumerKey_new = $ini['consumerKey_new'] ?? '';

/**
 * OAuth 1.0a consumer secret (secondary/new).
 *
 * Note: Typo in key name 'consumerSecrety_new' is intentional (matches INI).
 *
 * @var string
 */
$consumerSecrety_new = $ini['consumerSecrety_new'] ?? '';

/**
 * Current server domain name.
 *
 * Used for cookie domain setting and environment detection.
 *
 * @var string
 */
$domain = $_SERVER['SERVER_NAME'] ?? 'localhost';

/**
 * Encryption key for cookie values.
 *
 * Defuse PHP Encryption key loaded from ASCII-safe string format.
 * Used for encrypting sensitive data stored in browser cookies.
 *
 * @var Key
 *
 * @see https://github.com/defuse/php-encryption
 */
$cookie_key_string = $ini['cookie_key'] ?? '';

if (empty($cookie_key_string)) {
    error_log("[config.php] cookie_key not configured");
}

$cookie_key = Key::loadFromAsciiSafeString($cookie_key_string);

/**
 * Encryption key for database values.
 *
 * Defuse PHP Encryption key loaded from ASCII-safe string format.
 * Used for encrypting OAuth tokens stored in the database.
 *
 * @var Key
 */
$decrypt_key_string = $ini['decrypt_key'] ?? '';

if (empty($decrypt_key_string)) {
    error_log("[config.php] decrypt_key not configured");
}

$decrypt_key = Key::loadFromAsciiSafeString($decrypt_key_string);

/**
 * Secret key for JWT token signing.
 *
 * Used by firebase/php-jwt to sign and verify authentication tokens.
 * Should be a long, random string kept secret.
 *
 * @var string
 *
 * @security Never expose this value or commit it to version control.
 */
$jwt_key = $ini['jwt_key'] ?? '';

if (empty($jwt_key)) {
    error_log("[config.php] jwt_key not configured - JWT tokens will fail");
}
