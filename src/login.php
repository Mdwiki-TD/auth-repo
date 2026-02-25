<?php

use MediaWiki\OAuthClient\Client;
use MediaWiki\OAuthClient\ClientConfig;
use MediaWiki\OAuthClient\Consumer;

include_once __DIR__ . '/oauth/u.php';

// Get Settings instance
$settings = Settings::getInstance();

// Ensure required OAuth variables are available
if (empty($settings->consumerKey)) {
    throw new \RuntimeException('Required OAuth configuration variables are not defined: consumerKey is missing');
}
if (empty($settings->consumerSecret)) {
    throw new \RuntimeException('Required OAuth configuration variables are not defined: consumerSecret is missing');
}

/**
 * Display a styled error block to the user and terminate execution.
 *
 * Also writes a log entry confirming that a user-facing error was shown.
 *
 * @param string $message The message to display to the user; HTML will be escaped.
 * @param string|null $linkUrl Optional URL to include as a link; only used if `$linkText` is provided.
 * @param string|null $linkText Optional text for the link; the link is rendered only when both `$linkUrl` and `$linkText` are non-null.
 */
function showErrorAndExit(string $message, ?string $linkUrl = null, ?string $linkText = null)
{
    // The detailed error should be logged before calling this function.
    // This log entry confirms that a user-facing error was displayed.
    error_log("[OAuth Error] User was shown the following message: " . $message);

    echo "<div style='border:1px solid red; padding:10px; background:#ffe6e6; color:#900;'>";
    echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    if ($linkUrl && $linkText) {
        echo "<br><a href='" . htmlspecialchars($linkUrl, ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($linkText, ENT_QUOTES, 'UTF-8') . "</a>";
    }
    echo "</div>";
    exit;
}

// Initialize variables to satisfy static analysis
$client = null;
$authUrl = null;
$token = null;

// Configure the OAuth client with the URL and consumer details.
try {
    $conf = new ClientConfig($settings->oauthUrl);
    $conf->setConsumer(new Consumer($settings->consumerKey, $settings->consumerSecret));
    $conf->setUserAgent($settings->userAgent);
    $client = new Client($conf);
} catch (\Exception $e) {
    // Log the detailed, internal error message.
    error_log("OAuth Error: Failed to initialize OAuth client: " . $e->getMessage());
    // Show a generic, user-friendly error message.
    showErrorAndExit("An internal error occurred while preparing the authentication service. Please try again later.");
}

/**
 * Build a callback URL by appending selected state parameters.
 *
 * Constructs a query fragment from a sanitized subset of GET parameters
 * (cat, code, type, test, doit) and, when the HTTP Referer is present and
 * its host is one of mdwiki.toolforge.org or localhost and the referer path
 * does not contain "/auth/", includes a `return_to` parameter with that referer.
 *
 * @param string $url Base callback URL to which state parameters will be appended.
 * @return string The resulting callback URL including the serialized state query (or the original URL if no state added).
 */
function create_callback_url($url)
{
    $state = [];
    $return_to = '';
    $allowed_domains = ['mdwiki.toolforge.org', 'localhost'];

    if (isset($_SERVER['HTTP_REFERER'])) {
        $parsed = parse_url($_SERVER['HTTP_REFERER']);
        if (isset($parsed['host']) && in_array($parsed['host'], $allowed_domains)) {
            $return_to = $_SERVER['HTTP_REFERER'];
        }
    }

    if (!empty($return_to) && (strpos($return_to, '/auth/') === false)) {
        $state['return_to'] = $return_to;
    }

    foreach (['cat', 'code', 'type', 'test', 'doit'] as $key) {
        $da = filter_input(INPUT_GET, $key, FILTER_SANITIZE_STRING);
        if (!empty($da)) {
            $state[$key] = $da;
        }
    }

    $sta = "";
    if (!empty($state)) {
        $sta = '&' . http_build_query($state);
    }

    return $url . $sta;
}

$call_back_url = create_callback_url('https://mdwiki.toolforge.org/auth/index.php?a=callback');

try {
    $client->setCallback($call_back_url);
} catch (\Exception $e) {
    // Log the detailed error.
    error_log("OAuth Error: Failed to set OAuth callback URL: " . $e->getMessage());
    // Show a generic error.
    showErrorAndExit("An internal error occurred while configuring the authentication callback. Please try again.");
}

// Send an HTTP request to the wiki to get the authorization URL and a Request Token.
try {
    list($authUrl, $token) = $client->initiate();
    if (!$authUrl || !$token) {
        // Log this specific failure case.
        error_log("OAuth Error: client->initiate() returned empty authUrl or token.");
        showErrorAndExit("Failed to initiate the authentication process with the wiki. Please try again.");
    } else {
        // Store the Request Token in the session.
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['request_key'] = $token->key;
        $_SESSION['request_secret'] = $token->secret;
    }
} catch (\Exception $e) {
    // Log the detailed exception.
    error_log("OAuth Error: Exception during OAuth initiation: " . $e->getMessage());
    // Show a generic error.
    showErrorAndExit("An error occurred while starting the authentication process. Please try again.");
}

// Verify authentication was successful
if ($client === null || $authUrl === null) {
    showErrorAndExit("Authentication initialization failed. Please try again.");
}

// Redirect the user to the authorization URL.
if ($settings->domain !== 'localhost') {
    header("Location: $authUrl");
    exit(0);
} else {
    // For local development, show the link instead of auto-redirecting.
    echo "Go to this URL to authorize:<br /><a href='$authUrl'>$authUrl</a>";
}
