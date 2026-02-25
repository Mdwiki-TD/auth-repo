<?php

// Get Settings instance
$settings = Settings::getInstance();

// Ensure required variables are defined
if (empty($settings->oauthUrl) || empty($settings->consumerKey) || empty($settings->consumerSecret) || empty($settings->userAgent)) {
    throw new \RuntimeException('Required OAuth configuration variables are not defined');
}

use function OAuth\JWT\create_jwt;
use MediaWiki\OAuthClient\Client;
use MediaWiki\OAuthClient\ClientConfig;
use MediaWiki\OAuthClient\Consumer;
use MediaWiki\OAuthClient\Token;
use function OAuth\Helps\add_to_cookies;
use function OAuth\AccessHelps\add_access_to_dbs;
use function OAuth\AccessHelpsNew\add_access_to_dbs_new;
use function OAuth\AccessHelps\sql_add_user;

/**
 * Display a user-facing error message in a red-bordered box, optionally with a link, then terminate execution.
 *
 * Also records the shown message to the server error log for diagnostic purposes.
 *
 * @param string $message The message to display to the user.
 * @param string|null $linkUrl Optional URL to include as a link beneath the message.
 * @param string|null $linkText Optional text label for the link; ignored if $linkUrl is null.
 */
function showErrorAndExit(string $message, ?string $linkUrl = null, ?string $linkText = null)
{
    // Log the error to server error log
    // The detailed message is logged before this function is called.
    // This log entry provides context that a user-facing error was shown.
    error_log("[OAuth Error] User was shown the following message: " . $message);

    echo "<div style='border:1px solid red; padding:10px; background:#ffe6e6; color:#900;'>";
    echo $message;
    if ($linkUrl && $linkText) {
        echo "<br><a href='" . $linkUrl . "'>" . $linkText . "</a>";
    }
    echo "</div>";
    exit;
}

if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_GET['oauth_verifier'])) {
    showErrorAndExit(
        "This page should only be accessed after redirection back from the wiki.",
        "index.php?a=login",
        "Login"
    );
}

if (!isset($_SESSION['request_key'], $_SESSION['request_secret'])) {
    showErrorAndExit(
        "OAuth session expired or invalid. Please start login again.",
        "index.php?a=login",
        "Login"
    );
}

// Initialize variables to satisfy static analysis
$client = null;
$requestToken = null;
$accessToken1 = null;
$ident = null;

try {
    $conf = new ClientConfig($settings->oauthUrl);
    $conf->setConsumer(new Consumer($settings->consumerKey, $settings->consumerSecret));
    $conf->setUserAgent($settings->userAgent);
    $client = new Client($conf);
} catch (\Exception $e) {
    // Log the detailed, internal error message for debugging.
    error_log("OAuth Error: Failed to initialize OAuth client: " . $e->getMessage());
    // Show a generic, user-friendly error message.
    showErrorAndExit("An internal error occurred while setting up authentication. Please try again later.");
}

try {
    $requestToken = new Token($_SESSION['request_key'], $_SESSION['request_secret']);
} catch (\Exception $e) {
    // Log the detailed error.
    error_log("OAuth Error: Invalid request token from session: " . $e->getMessage());
    // Show a generic error.
    showErrorAndExit("Your session contains an invalid token. Please try logging in again.");
}

try {
    $accessToken1 = $client->complete($requestToken, $_GET['oauth_verifier']);
    unset($_SESSION['request_key'], $_SESSION['request_secret']);
} catch (\MediaWiki\OAuthClient\Exception $e) {
    // Log the detailed error from the OAuth client.
    error_log("OAuth Error: Authentication failed during client->complete(): " . $e->getMessage());
    // Show a generic error with a link to retry.
    showErrorAndExit(
        "Authentication with the wiki failed. Please try again.",
        "index.php?a=login",
        "Try again"
    );
}

try {
    $accessToken = new Token($accessToken1->key, $accessToken1->secret);
    $ident = $client->identify($accessToken);
} catch (\Exception $e) {
    // Log the detailed error.
    error_log("OAuth Error: Failed during OAuth process: " . $e->getMessage());
    // Show a generic error.
    showErrorAndExit("Could not verify your identity after authentication. Please try again.");
}

// Verify all required objects were created
if ($client === null || $accessToken1 === null || $ident === null) {
    showErrorAndExit("Authentication failed. Please try logging in again.", "index.php?a=login", "Try again");
}

try {
    $_SESSION['username'] = $ident->username;
    $jwt = create_jwt($ident->username);
    add_to_cookies('jwt_token', $jwt);
    add_to_cookies('username', $ident->username);

    if (!isset($_SESSION['csrf_tokens']) || !is_array($_SESSION['csrf_tokens'])) {
        $_SESSION['csrf_tokens'] = [];
    }

    add_access_to_dbs_new($ident->username, $accessToken1->key, $accessToken1->secret);
    add_access_to_dbs($ident->username, $accessToken1->key, $accessToken1->secret);
    sql_add_user($ident->username);
} catch (\Exception $e) {
    // Log the detailed error.
    error_log("OAuth Error: Failed to store user session data or update database: " . $e->getMessage());
    // Show a generic error.
    showErrorAndExit("An error occurred while saving your session. Please try logging in again.");
}

$test = $_GET['test'] ?? '';
$return_to = $_GET['return_to'] ?? '';
$newurl = "/Translation_Dashboard/index.php";

if (!empty($return_to) && (strpos($return_to, '/Translation_Dashboard/index.php') === false)) {
    $newurl = filter_var($return_to, FILTER_VALIDATE_URL) ? $return_to : '/Translation_Dashboard/index.php';
} elseif (!empty($return_to) && (strpos($return_to, '/auth/') !== false)) {
    $newurl = '/Translation_Dashboard/index.php';
} else {
    $state = [];
    foreach (['cat', 'code', 'type', 'doit'] as $key) {
        $da1 = filter_input(INPUT_GET, $key, FILTER_SANITIZE_STRING);
        if (!empty($da1)) $state[$key] = $da1;
    }
    $state = http_build_query($state);
    $newurl = "/Translation_Dashboard/index.php?$state";
}

echo "You are authenticated as " . htmlspecialchars($ident->username, ENT_QUOTES, 'UTF-8') . ".<br>";
echo "<a href='$newurl'>Continue</a>";

if (empty($test)) {
    header("Location: $newurl");
    exit;
}
