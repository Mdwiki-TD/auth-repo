<?php
//---
if (isset($_REQUEST['test'])) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
};
//---
include_once __DIR__ . '/include_all.php';

use MediaWiki\OAuthClient\Client;
use MediaWiki\OAuthClient\ClientConfig;
use MediaWiki\OAuthClient\Consumer;
use MediaWiki\OAuthClient\Token;
use function OAuth\Helps\get_from_cookies;
use function OAuth\AccessHelps\get_access_from_dbs;
use function OAuth\AccessHelpsNew\get_access_from_dbs_new;

// header( 'Content-type: text/plain' );

// Get the wiki URL and OAuth consumer details from the settings.

// Get Settings instance
$settings = Settings::getInstance();

// Ensure required variables are defined
if (
    empty(trim((string)$settings->oauthUrl)) ||
    empty(trim((string)$settings->consumerKey)) ||
    empty(trim((string)$settings->consumerSecret)) ||
    empty(trim((string)$settings->userAgent))
) {
    throw new \RuntimeException('Required OAuth configuration variables are not defined');
}

// Configure the OAuth client with the URL and consumer details.
$conf = new ClientConfig($settings->oauthUrl);
$conf->setConsumer(new Consumer($settings->consumerKey, $settings->consumerSecret));
$conf->setUserAgent($settings->userAgent);
$client = new Client($conf);
// ---
$username = get_from_cookies('username');
// ---
$access = get_access_from_dbs_new($username);
// ---
if ($access == null) {
    $access = get_access_from_dbs($username);
}
// ---
$access_key = $access['access_key'] ?? "";
$access_secret = $access['access_secret'] ?? "";
// ---
if (empty($access_key) || empty($access_secret)) {
    echo json_encode(['error' => 'No access key or secret found.']);
    exit;
}

$accessToken = new Token($access_key, $access_secret);

// Example 1: get the authenticated user's identity.
$ident = $client->identify($accessToken);

function get_edit_tokens()
{
    global $client, $accessToken;
    $settings = Settings::getInstance();
    // Example 3: make an edit (getting the edit token first).
    $editToken = json_decode($client->makeOAuthCall(
        $accessToken,
        $settings->apiUrl . "?action=query&meta=tokens&format=json"
    ))->query->tokens->csrftoken;
    //---
    return $editToken;
}

function do_Api_Query($Params, $addtoken = null)
{
    global $client, $accessToken;
    $settings = Settings::getInstance();
    //---
    if ($addtoken !== null) $Params['token'] = get_edit_tokens();
    //---
    $Result = $client->makeOAuthCall(
        $accessToken,
        $settings->apiUrl,
        true,
        $Params
    );
    //---
    return json_decode($Result, true);
}

$post = $_GET;
if (isset($post['action'])) {
    $result = do_Api_Query($post);
    echo json_encode($result, JSON_THROW_ON_ERROR);
}
