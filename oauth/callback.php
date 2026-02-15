<?php
/**
 * OAuth Callback Handler.
 *
 * Handles the OAuth 1.0a callback after the user authorizes the application
 * on Wikimedia. Exchanges the request token for an access token, retrieves
 * the user's identity, stores credentials, and redirects to the application.
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
 * GET /auth/index.php?a=callback&oauth_verifier=xxx&oauth_token=xxx
 * ```
 *
 * @see login.php For initiating the OAuth flow.
 * @see https://www.mediawiki.org/wiki/OAuth/For_Developers
 */

declare(strict_types=1);

use MediaWiki\OAuthClient\Client;
use MediaWiki\OAuthClient\ClientConfig;
use MediaWiki\OAuthClient\Consumer;
use MediaWiki\OAuthClient\Token;
use function OAuth\JWT\create_jwt;
use function OAuth\Helps\add_to_cookies;
use function OAuth\AccessHelps\add_access_to_dbs;
use function OAuth\AccessHelpsNew\add_access_to_dbs_new;
use function OAuth\AccessHelps\sql_add_user;

// Load dependencies
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/access_helps.php';
require_once __DIR__ . '/access_helps_new.php';
require_once __DIR__ . '/jwt_config.php';
require_once __DIR__ . '/helps.php';

/**
 * Display a styled error message and terminate execution.
 *
 * @param string      $message  The error message to display (HTML escaped).
 * @param string|null $linkUrl  Optional URL for a help link.
 * @param string|null $linkText Optional text for the help link.
 *
 * @return never This function terminates execution.
 */
function showErrorAndExit(
    string $message,
    ?string $linkUrl = null,
    ?string $linkText = null
): never {
    error_log("[OAuth Callback Error] User shown: " . $message);

    echo "<div style='border:1px solid red; padding:10px; background:#ffe6e6; color:#900;'>";
    echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

    if ($linkUrl !== null && $linkText !== null) {
        echo "<br><a href='" . htmlspecialchars($linkUrl, ENT_QUOTES, 'UTF-8') . "'>";
        echo htmlspecialchars($linkText, ENT_QUOTES, 'UTF-8');
        echo "</a>";
    }

    echo "</div>";
    exit(1);
}

// Start session for request token retrieval
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Validate OAuth callback parameters
if (!isset($_GET['oauth_verifier'])) {
    showErrorAndExit(
        "This page should only be accessed after redirection back from the wiki.",
        "index.php?a=login",
        "Login"
    );
}

// Validate session has required request token
if (!isset($_SESSION['request_key'], $_SESSION['request_secret'])) {
    showErrorAndExit(
        "OAuth session expired or invalid. Please start login again.",
        "index.php?a=login",
        "Login"
    );
}

// Initialize OAuth client
try {
    $conf = new ClientConfig($oauthUrl);
    $conf->setConsumer(new Consumer($consumerKey, $consumerSecret));
    $conf->setUserAgent($gUserAgent);
    $client = new Client($conf);
} catch (\Exception $e) {
    error_log("[callback.php] OAuth client init failed: " . $e->getMessage());
    showErrorAndExit(
        "An internal error occurred while setting up authentication. Please try again later."
    );
}

// Reconstruct request token from session
try {
    $requestToken = new Token(
        $_SESSION['request_key'],
        $_SESSION['request_secret']
    );
} catch (\Exception $e) {
    error_log("[callback.php] Invalid request token: " . $e->getMessage());
    showErrorAndExit(
        "Your session contains an invalid token. Please try logging in again."
    );
}

// Exchange request token for access token
try {
    $accessToken = $client->complete($requestToken, $_GET['oauth_verifier']);
    unset($_SESSION['request_key'], $_SESSION['request_secret']);
} catch (\MediaWiki\OAuthClient\Exception $e) {
    error_log("[callback.php] Token exchange failed: " . $e->getMessage());
    showErrorAndExit(
        "Authentication with the wiki failed. Please try again.",
        "index.php?a=login",
        "Try again"
    );
}

// Get user identity from access token
try {
    $accessTokenObj = new Token($accessToken->key, $accessToken->secret);
    $ident = $client->identify($accessTokenObj);
} catch (\Exception $e) {
    error_log("[callback.php] Identity retrieval failed: " . $e->getMessage());
    showErrorAndExit(
        "Could not verify your identity after authentication. Please try again."
    );
}

// SECURITY: Regenerate session ID to prevent session fixation
session_regenerate_id(true);

// Generate CSRF token for this session
$csrfToken = bin2hex(random_bytes(32));

// Store user data in session and cookies
try {
    $_SESSION['username'] = $ident->username;
    $_SESSION['csrf_token'] = $csrfToken;

    // Create and store JWT token
    $jwt = create_jwt($ident->username);
    if ($jwt !== '') {
        add_to_cookies('jwt_token', $jwt);
    }

    add_to_cookies('username', $ident->username);

    // Store access tokens in both tables (legacy + new)
    add_access_to_dbs_new($ident->username, $accessToken->key, $accessToken->secret);
    add_access_to_dbs($ident->username, $accessToken->key, $accessToken->secret);

    // Add user to users table
    sql_add_user($ident->username);
} catch (\Exception $e) {
    error_log("[callback.php] Data storage failed: " . $e->getMessage());
    showErrorAndExit(
        "An error occurred while saving your session. Please try logging in again."
    );
}

// Determine redirect URL with whitelist validation
$returnTo = $_GET['return_to'] ?? '';
$defaultUrl = "/Translation_Dashboard/index.php";

/** @var list<string> Allowed domains for redirect */
$allowedRedirectDomains = ['mdwiki.toolforge.org', 'localhost'];

/**
 * Validate redirect URL against whitelist.
 *
 * @param string $url            The URL to validate.
 * @param list<string> $allowedDomains List of allowed domains.
 *
 * @return string The validated URL or default if invalid.
 */
function validateRedirectUrl(string $url, array $allowedDomains): string
{
    // Must be a valid URL
    if (filter_var($url, FILTER_VALIDATE_URL) === false) {
        return '';
    }

    $parsed = parse_url($url);

    // Must have a host
    if ($parsed === false || !isset($parsed['host'])) {
        return '';
    }

    // Host must be in allowed list
    if (!in_array($parsed['host'], $allowedDomains, true)) {
        return '';
    }

    return $url;
}

if (!empty($returnTo)) {
    // Validate URL against whitelist
    $validatedUrl = validateRedirectUrl($returnTo, $allowedRedirectDomains);

    if ($validatedUrl !== '' && strpos($validatedUrl, '/auth/') === false) {
        $newUrl = $validatedUrl;
    } else {
        $newUrl = $defaultUrl;
    }
} else {
    // Build URL from state parameters
    $state = [];
    $stateParams = ['cat', 'code', 'type', 'doit'];

    foreach ($stateParams as $key) {
        $value = filter_input(INPUT_GET, $key, FILTER_SANITIZE_SPECIAL_CHARS);
        if (!empty($value)) {
            $state[$key] = $value;
        }
    }

    $queryString = http_build_query($state);
    $newUrl = "/Translation_Dashboard/index.php" . ($queryString ? "?{$queryString}" : "");
}

// Redirect to the application
header("Location: {$newUrl}");
exit(0);
