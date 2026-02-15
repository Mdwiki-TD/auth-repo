<?php
/**
 * User Information Loader.
 *
 * Loads the currently authenticated user's information from cookies,
 * validates their access tokens from the database, and makes the
 * username available globally via a constant.
 *
 * @package    OAuth
 * @author     MDWiki Team
 * @copyright  2024 MDWiki
 * @license    MIT
 * @version    2.0.0
 * @since      1.0.0
 *
 * @example Usage:
 * ```php
 * include_once __DIR__ . '/user_infos.php';
 *
 * if (global_username !== '') {
 *     echo "Welcome, " . htmlspecialchars(global_username);
 * }
 * ```
 *
 * @global string global_username The authenticated username (or empty string).
 */

declare(strict_types=1);

// Load dependencies
include_once __DIR__ . '/../vendor/autoload.php';
include_once __DIR__ . '/config.php';
include_once __DIR__ . '/helps.php';
require_once __DIR__ . '/access_helps.php';
require_once __DIR__ . '/access_helps_new.php';

use function OAuth\Helps\get_from_cookies;
use function OAuth\AccessHelps\get_access_from_dbs;
use function OAuth\AccessHelpsNew\get_access_from_dbs_new;

/**
 * Determine secure cookie settings based on environment.
 *
 * @var bool
 */
$secure = (($_SERVER['SERVER_NAME'] ?? '') !== "localhost");

// Configure session for production (non-localhost)
if (($_SERVER['SERVER_NAME'] ?? '') !== 'localhost') {
    if (session_status() === PHP_SESSION_NONE) {
        session_name("mdwikitoolforgeoauth");
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => $domain ?? '',
            'secure'   => $secure,
            'httponly' => $secure,
            'samesite' => 'Strict'
        ]);
    }
}

/**
 * Display a Bootstrap-styled alert message.
 *
 * @param string $text The alert message text.
 *
 * @return string HTML for the alert div.
 *
 * @example
 * ```php
 * echo ba_alert("Session expired. Please login again.");
 * ```
 */
function ba_alert(string $text): string
{
    $escapedText = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

    return <<<HTML
        <div class='container'>
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle"></i> {$escapedText}
            </div>
        </div>
    HTML;
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Retrieve username from cookies
$username = get_from_cookies('username');

// On localhost, prefer session (for development bypass)
if (($_SERVER['SERVER_NAME'] ?? '') === 'localhost') {
    $username = $_SESSION['username'] ?? '';
} elseif (!empty($username)) {
    // Validate user has valid access tokens
    $access = get_access_from_dbs_new($username);

    // Fallback to legacy table
    if ($access === null) {
        $access = get_access_from_dbs($username);
    }

    // Clear invalid sessions
    if ($access === null) {
        echo ba_alert("No access keys found. Please login again.");

        // Clear invalid cookies
        setcookie('username', '', [
            'expires' => time() - 3600,
            'path'    => '/',
            'domain'  => $domain ?? '',
            'secure'  => true,
            'httponly' => true
        ]);

        $username = '';
        unset($_SESSION['username']);
    }
}

/**
 * Global variable holding the current username.
 *
 * @var string
 *
 * @deprecated Use global_username constant instead.
 */
$global_username = $username;

/**
 * Define global username constant for access throughout the application.
 *
 * @var string
 */
define('global_username', $username);
