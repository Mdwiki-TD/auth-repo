<?php
/**
 * OAuth Login Handler.
 *
 * Initiates the OAuth 1.0a authentication flow by redirecting the user
 * to Wikimedia's OAuth authorization page. Stores the request token
 * in the session for later verification during the callback.
 *
 * @package    OAuth
 * @author     MDWiki Team
 * @copyright  2024 MDWiki
 * @license    MIT
 * @version    2.0.0
 * @since      1.0.0
 *
 * @example Usage:
 * ```
 * GET /auth/index.php?a=login
 * GET /auth/index.php?a=login&return_to=/some/page
 * ```
 *
 * @see callback.php For handling the OAuth response.
 * @see https://www.mediawiki.org/wiki/OAuth/For_Developers
 */

declare(strict_types=1);

use MediaWiki\OAuthClient\Client;
use MediaWiki\OAuthClient\ClientConfig;
use MediaWiki\OAuthClient\Consumer;
use MediaWiki\OAuthClient\Token;

/** @var string Path to vendor autoload */
require_once __DIR__ . '/../vendor/autoload.php';

/**
 * Display a styled error message and terminate execution.
 *
 * Outputs an error in a red-bordered box with optional link.
 * Also logs the error message for debugging purposes.
 *
 * @param string      $message  The error message to display (HTML escaped).
 * @param string|null $linkUrl  Optional URL for a help link.
 * @param string|null $linkText Optional text for the help link.
 *
 * @return never This function terminates execution with exit.
 *
 * @example
 * ```php
 * showErrorAndExit(
 *     "Authentication failed. Please try again.",
 *     "/auth/index.php?a=login",
 *     "Retry Login"
 * );
 * ```
 */
function showErrorAndExit(
    string $message,
    ?string $linkUrl = null,
    ?string $linkText = null
): never {
    error_log("[OAuth Login Error] User shown: " . $message);

    echo "<div style='border:1px solid red; padding:10px; background:#ffe6e6; color:#900;'>";
    echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

    if ($linkUrl !== null && $linkText !== null) {
        $safeUrl = htmlspecialchars($linkUrl, ENT_QUOTES, 'UTF-8');
        $safeText = htmlspecialchars($linkText, ENT_QUOTES, 'UTF-8');
        echo "<br><a href='{$safeUrl}'>{$safeText}</a>";
    }

    echo "</div>";
    exit(1);
}

/**
 * Build a callback URL with preserved state parameters.
 *
 * Constructs the OAuth callback URL by appending selected GET parameters
 * (cat, code, type, doit) and optionally a return_to URL from
 * the HTTP Referer header (if from a trusted domain).
 *
 * @param string $baseUrl The base callback URL.
 *
 * @return string The callback URL with state parameters appended.
 *
 * @security Referer is validated against an allowlist of domains.
 *           Only whitelisted GET parameters are preserved.
 */
function create_callback_url(string $baseUrl): string
{
    $state = [];
    $returnTo = '';
    $allowedDomains = ['mdwiki.toolforge.org', 'localhost'];

    // Extract return_to from Referer if from trusted domain
    if (isset($_SERVER['HTTP_REFERER'])) {
        $parsed = parse_url($_SERVER['HTTP_REFERER']);

        if (
            $parsed !== false &&
            isset($parsed['host']) &&
            in_array($parsed['host'], $allowedDomains, true)
        ) {
            $returnTo = $_SERVER['HTTP_REFERER'];
        }
    }

    // Add return_to if not pointing to auth pages
    if (
        !empty($returnTo) &&
        strpos($returnTo, '/auth/') === false
    ) {
        $state['return_to'] = $returnTo;
    }

    // Preserve allowed state parameters
    $allowedParams = ['cat', 'code', 'type', 'doit'];

    foreach ($allowedParams as $key) {
        $value = filter_input(INPUT_GET, $key, FILTER_SANITIZE_SPECIAL_CHARS);

        if (!empty($value)) {
            $state[$key] = $value;
        }
    }

    $queryString = !empty($state) ? '&' . http_build_query($state) : '';

    return $baseUrl . $queryString;
}

// Load configuration (defines global OAuth settings)
include_once __DIR__ . '/config.php';

// Initialize OAuth client
try {
    $conf = new ClientConfig($oauthUrl);
    $conf->setConsumer(new Consumer($consumerKey, $consumerSecret));
    $conf->setUserAgent($gUserAgent);
    $client = new Client($conf);
} catch (\Exception $e) {
    error_log("[login.php] OAuth client init failed: " . $e->getMessage());
    showErrorAndExit(
        "An internal error occurred while preparing authentication. Please try again later."
    );
}

// Build callback URL with state preservation
$callbackUrl = create_callback_url(
    'https://mdwiki.toolforge.org/auth/index.php?a=callback'
);

try {
    $client->setCallback($callbackUrl);
} catch (\Exception $e) {
    error_log("[login.php] Failed to set callback: " . $e->getMessage());
    showErrorAndExit(
        "An internal error occurred while configuring the authentication callback. Please try again."
    );
}

// Initiate OAuth flow - get request token and authorization URL
try {
    [$authUrl, $token] = $client->initiate();

    if (empty($authUrl) || empty($token)) {
        error_log("[login.php] initiate() returned empty authUrl or token");
        showErrorAndExit(
            "Failed to initiate authentication with the wiki. Please try again."
        );
    }
} catch (\Exception $e) {
    error_log("[login.php] OAuth initiation failed: " . $e->getMessage());
    showErrorAndExit(
        "An error occurred while starting the authentication process. Please try again."
    );
}

// Store request token in session for callback verification
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

try {
    $_SESSION['request_key'] = $token->key;
    $_SESSION['request_secret'] = $token->secret;
} catch (\Exception $e) {
    error_log("[login.php] Session storage failed: " . $e->getMessage());
    showErrorAndExit(
        "A session error occurred. Please ensure cookies are enabled and try again."
    );
}

// Redirect user to authorization page
if (($_SERVER['SERVER_NAME'] ?? '') !== 'localhost') {
    header("Location: {$authUrl}");
    exit(0);
}

// Development mode: show link instead of auto-redirect
echo "Go to this URL to authorize:<br />";
echo "<a href='" . htmlspecialchars($authUrl, ENT_QUOTES, 'UTF-8') . "'>";
echo htmlspecialchars($authUrl, ENT_QUOTES, 'UTF-8');
echo "</a>";
