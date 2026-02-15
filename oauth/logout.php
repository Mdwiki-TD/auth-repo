<?php
/**
 * OAuth Logout Handler.
 *
 * Terminates the user's session, clears authentication cookies,
 * and redirects back to the referring page (if from a trusted domain)
 * or to the default dashboard.
 *
 * @package    OAuth
 * @author     MDWiki Team
 * @copyright  2024 MDWiki
 * @license    MIT
 * @version    2.1.0
 * @since      1.0.0
 *
 * @example Usage:
 * ```
 * GET /auth/index.php?a=logout&csrf_token=xxx
 * ```
 *
 * @security Requires CSRF token for logout. Token must match session.
 *           If no token provided but Referer is from same domain,
 *           logout proceeds (for backward compatibility with UI links).
 *
 * @see login.php For re-authentication.
 */

declare(strict_types=1);

// Load configuration for domain setting
require_once __DIR__ . '/../vendor/autoload.php';
include_once __DIR__ . '/config.php';

/** @var list<string> List of trusted domains for redirects and referer checks */
$allowedDomains = ['mdwiki.toolforge.org', 'localhost'];

/**
 * Check if the request has valid CSRF protection.
 *
 * Accepts either:
 * - A csrf_token GET parameter matching the session token
 * - A Referer header from a trusted domain
 *
 * @param list<string> $allowedDomains Trusted domains for referer check.
 *
 * @return bool True if request is authorized.
 */
function isLogoutAuthorized(array $allowedDomains): bool
{
    // Start session to access CSRF token
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Method 1: Check for valid CSRF token
    $providedToken = $_GET['csrf_token'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    if ($providedToken !== '' && $sessionToken !== '' && hash_equals($sessionToken, $providedToken)) {
        return true;
    }

    // Method 2: Check Referer header is from our domain
    if (isset($_SERVER['HTTP_REFERER'])) {
        $parsed = parse_url($_SERVER['HTTP_REFERER']);

        if (
            $parsed !== false &&
            isset($parsed['host']) &&
            in_array($parsed['host'], $allowedDomains, true)
        ) {
            return true;
        }
    }

    return false;
}

// Validate logout request
if (!isLogoutAuthorized($allowedDomains)) {
    header("HTTP/1.1 403 Forbidden");
    echo "<h1>403 Forbidden</h1>";
    echo "<p>Logout request denied. Missing or invalid CSRF token.</p>";
    echo "<p><a href='/'>Return to home</a></p>";
    exit(1);
}

// Start/destroy PHP session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Clear session data
$_SESSION = [];

// Destroy session
session_destroy();

// Clear authentication cookies with expired timestamps
$cookieOptions = [
    'expires'  => time() - 3600,
    'path'     => '/',
    'domain'   => $domain ?? '',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Strict'
];

setcookie('username', '', $cookieOptions);
setcookie('accesskey', '', $cookieOptions);
setcookie('access_secret', '', $cookieOptions);
setcookie('jwt_token', '', $cookieOptions);

/**
 * Default redirect URL after logout.
 *
 * @var string
 */
$redirectTo = '/Translation_Dashboard/index.php';

// Check if we can redirect to the referrer (already validated above)
if (isset($_SERVER['HTTP_REFERER'])) {
    $parsed = parse_url($_SERVER['HTTP_REFERER']);

    if (
        $parsed !== false &&
        isset($parsed['host']) &&
        in_array($parsed['host'], $allowedDomains, true)
    ) {
        // Don't redirect back to logout page
        if (strpos($_SERVER['HTTP_REFERER'], 'a=logout') === false) {
            $redirectTo = $_SERVER['HTTP_REFERER'];
        }
    }
}

// Perform redirect
header("Location: {$redirectTo}");
exit(0);
