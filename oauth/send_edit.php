<?php
/**
 * MediaWiki Edit Functionality via OAuth.
 *
 * Provides functions for making authenticated edits to MediaWiki wikis
 * using OAuth credentials. Supports any wiki in the Wikimedia ecosystem.
 *
 * @package    OAuth\SendEdit
 * @author     MDWiki Team
 * @copyright  2024 MDWiki
 * @license    MIT
 * @version    2.0.0
 * @since      1.0.0
 *
 * @example Usage:
 * ```php
 * use function OAuth\SendEdit\auth_make_edit;
 *
 * $result = auth_make_edit(
 *     'Project:Sandbox',
 *     'Test edit content',
 *     'Test edit summary',
 *     'en',
 *     $accessKey,
 *     $accessSecret
 * );
 * ```
 *
 * @see https://www.mediawiki.org/wiki/API:Edit
 */

declare(strict_types=1);

namespace OAuth\SendEdit;

use MediaWiki\OAuthClient\Client;
use MediaWiki\OAuthClient\ClientConfig;
use MediaWiki\OAuthClient\Consumer;
use MediaWiki\OAuthClient\Token;

// Load dependencies
require_once __DIR__ . '/../vendor/autoload.php';
include_once __DIR__ . '/config.php';
include_once __DIR__ . '/helps.php';

/**
 * Retrieve a CSRF token for making edits to a specific wiki.
 *
 * Each wiki in the Wikimedia ecosystem requires a separate CSRF token
 * obtained from that wiki's API.
 *
 * @param Client $client      The configured OAuth client.
 * @param Token  $accessToken The user's access token.
 * @param string $apiUrl      The target wiki's API URL.
 *
 * @return string|null The CSRF token, or null on failure.
 *
 * @example
 * ```php
 * $token = get_edits_tokens($client, $accessToken, 'https://en.wikipedia.org/w/api.php');
 * if ($token === null) {
 *     echo "Failed to get edit token";
 * }
 * ```
 */
function get_edits_tokens(
    Client $client,
    Token $accessToken,
    string $apiUrl
): ?string {
    $response = $client->makeOAuthCall(
        $accessToken,
        "{$apiUrl}?action=query&meta=tokens&format=json"
    );

    $data = json_decode($response);

    if (
        $data === null ||
        !isset($data->query->tokens->csrftoken)
    ) {
        error_log(
            "[get_edits_tokens] JSON decode error: " .
            json_last_error_msg()
        );
        return null;
    }

    return $data->query->tokens->csrftoken;
}

/**
 * Make an authenticated edit to a MediaWiki wiki.
 *
 * Performs an edit operation using the user's OAuth credentials.
 * The edit is attributed to the user who authorized the OAuth application.
 *
 * @param string $title        The page title to edit (wiki-formatted).
 * @param string $text         The new page content (replaces existing).
 * @param string $summary      The edit summary shown in history.
 * @param string $wiki         The wiki language code (e.g., 'en', 'ar').
 * @param string $accessKey    The OAuth access token key.
 * @param string $accessSecret The OAuth access token secret.
 *
 * @return array<string, mixed> The API response as an associative array:
 *                              - On success: ['edit' => ['result' => 'Success', ...]]
 *                              - On failure: ['error' => ['code' => '...', 'info' => '...']]
 *
 * @example
 * ```php
 * $result = auth_make_edit(
 *     'User:Example/Sandbox',
 *     'Hello, World!',
 *     'Testing OAuth edit',
 *     'en',
 *     $tokenKey,
 *     $tokenSecret
 * );
 *
 * if (isset($result['edit']['result']) && $result['edit']['result'] === 'Success') {
 *     echo "Edit successful! New revision: " . $result['edit']['newrevid'];
 * } else {
 *     echo "Edit failed: " . ($result['error']['info'] ?? 'Unknown error');
 * }
 * ```
 *
 * @global string $gUserAgent     The user agent for API requests.
 * @global string $consumerKey    The OAuth consumer key.
 * @global string $consumerSecret The OAuth consumer secret.
 */
function auth_make_edit(
    string $title,
    string $text,
    string $summary,
    string $wiki,
    string $accessKey,
    string $accessSecret
): array {
    global $gUserAgent, $consumerKey, $consumerSecret;

    // Construct wiki-specific URLs
    $oauthUrl = "https://{$wiki}.wikipedia.org/w/index.php?title=Special:OAuth";
    $apiUrl = "https://{$wiki}.wikipedia.org/w/api.php";

    // Initialize OAuth client for this wiki
    try {
        $conf = new ClientConfig($oauthUrl);
        $conf->setConsumer(new Consumer($consumerKey, $consumerSecret));
        $conf->setUserAgent($gUserAgent);
        $client = new Client($conf);
    } catch (\Exception $e) {
        error_log("[auth_make_edit] Client init failed: " . $e->getMessage());
        return ['error' => ['code' => 'oauth_error', 'info' => 'OAuth initialization failed']];
    }

    // Create access token
    $accessToken = new Token($accessKey, $accessSecret);

    // Get CSRF token for this wiki
    $editToken = get_edits_tokens($client, $accessToken, $apiUrl);

    if ($editToken === null) {
        return ['error' => ['code' => 'token_error', 'info' => 'Failed to obtain edit token']];
    }

    // Build edit parameters
    $apiParams = [
        'action'  => 'edit',
        'title'   => $title,
        'summary' => $summary,
        'text'    => $text,
        'token'   => $editToken,
        'format'  => 'json',
    ];

    // Make the edit
    $response = $client->makeOAuthCall(
        $accessToken,
        $apiUrl,
        true,
        $apiParams
    );

    return json_decode($response, true) ?? ['error' => ['info' => 'Invalid response']];
}
