<?php
/**
 * OAuth API Endpoint for MediaWiki API Proxy.
 *
 * Provides authenticated access to the MediaWiki API using stored OAuth
 * credentials. Allows external tools to make API calls on behalf of
 * authenticated users without handling OAuth directly.
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
 * GET /auth/oauth/api.php?action=query&meta=userinfo&format=json
 * ```
 *
 * @security API calls are made with the user's OAuth credentials.
 *           Only authenticated users with valid access tokens can use this.
 *
 * @see https://www.mediawiki.org/wiki/API:Main_page
 */

declare(strict_types=1);

use MediaWiki\OAuthClient\Client;
use MediaWiki\OAuthClient\ClientConfig;
use MediaWiki\OAuthClient\Consumer;
use MediaWiki\OAuthClient\Token;
use function OAuth\Helps\get_from_cookies;
use function OAuth\AccessHelps\get_access_from_dbs;
use function OAuth\AccessHelpsNew\get_access_from_dbs_new;

// Load dependencies
require_once __DIR__ . '/../vendor/autoload.php';
include_once __DIR__ . '/config.php';
include_once __DIR__ . '/helps.php';

/**
 * Retrieve an edit (CSRF) token for making edits.
 *
 * Makes an API call to obtain a csrf token needed for write operations.
 *
 * @return string The CSRF token, or empty string on failure.
 *
 * @global Client     $client      The OAuth client instance.
 * @global Token      $accessToken The user's access token.
 * @global string     $apiUrl      The MediaWiki API URL.
 */
function get_edit_tokens(): string
{
    global $client, $accessToken, $apiUrl;

    $response = $client->makeOAuthCall(
        $accessToken,
        "{$apiUrl}?action=query&meta=tokens&format=json"
    );

    $data = json_decode($response);

    if (
        $data === null ||
        !isset($data->query->tokens->csrftoken)
    ) {
        error_log("[api.php] Failed to get CSRF token: " . json_last_error_msg());
        return '';
    }

    return $data->query->tokens->csrftoken;
}

/**
 * Make an authenticated API call to MediaWiki.
 *
 * Proxies API requests to MediaWiki with OAuth authentication.
 * Optionally adds a CSRF token for write operations.
 *
 * @param array<string, mixed> $params   API parameters.
 * @param bool                 $addToken Whether to add a CSRF token.
 *
 * @return array<string, mixed> The decoded API response.
 *
 * @global Client  $client      The OAuth client instance.
 * @global Token   $accessToken The user's access token.
 * @global string  $apiUrl      The MediaWiki API URL.
 */
function do_Api_Query(array $params, bool $addToken = false): array
{
    global $client, $accessToken, $apiUrl;

    if ($addToken) {
        $token = get_edit_tokens();
        if ($token === '') {
            return ['error' => 'Failed to obtain edit token'];
        }
        $params['token'] = $token;
    }

    $result = $client->makeOAuthCall(
        $accessToken,
        $apiUrl,
        true,
        $params
    );

    return json_decode($result, true) ?? ['error' => 'Invalid JSON response'];
}

// Get username from cookies
$username = get_from_cookies('username');

if (empty($username)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not authenticated']);
    exit(1);
}

// Retrieve access tokens (try new table first, fallback to legacy)
$access = get_access_from_dbs_new($username);

if ($access === null) {
    $access = get_access_from_dbs($username);
}

if ($access === null) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No access keys found. Please login again.']);
    exit(1);
}

$accessKey = $access['access_key'] ?? '';
$accessSecret = $access['access_secret'] ?? '';

if (empty($accessKey) || empty($accessSecret)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid access credentials']);
    exit(1);
}

// Initialize OAuth client
try {
    $conf = new ClientConfig($oauthUrl);
    $conf->setConsumer(new Consumer($consumerKey, $consumerSecret));
    $conf->setUserAgent($gUserAgent);
    $client = new Client($conf);
} catch (\Exception $e) {
    error_log("[api.php] OAuth client init failed: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['error' => 'OAuth configuration error']);
    exit(1);
}

// Create access token
$accessToken = new Token($accessKey, $accessSecret);

// Verify user identity
try {
    $ident = $client->identify($accessToken);
} catch (\Exception $e) {
    error_log("[api.php] Identity verification failed: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Token expired or invalid. Please login again.']);
    exit(1);
}

// Process API request if action parameter provided
$post = $_GET;

if (isset($post['action'])) {
    header('Content-Type: application/json');
    $result = do_Api_Query($post);
    echo json_encode($result, JSON_PRETTY_PRINT);
}
