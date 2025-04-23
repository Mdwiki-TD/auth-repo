<?php
//---
if (isset($_REQUEST['test'])) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
};
//---
include_once __DIR__ . '/../vendor_load.php';

use MediaWiki\OAuthClient\Client;
use MediaWiki\OAuthClient\ClientConfig;
use MediaWiki\OAuthClient\Consumer;
use MediaWiki\OAuthClient\Token;
use function OAuth\Helps\get_from_cookies;
use function OAuth\AccessHelps\get_access_from_dbs;
use function OAuth\AccessHelpsNew\get_access_from_dbs_new;

// Output the demo as plain text, for easier formatting.
// header( 'Content-type: text/plain' );

// Get the wiki URL and OAuth consumer details from the config file.
include_once __DIR__ . '/config.php';
include_once __DIR__ . '/helps.php';

// Configure the OAuth client with the URL and consumer details.
$conf = new ClientConfig($oauthUrl);
$conf->setConsumer(new Consumer($consumerKey, $consumerSecret));
$conf->setUserAgent($gUserAgent);
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
    global $client, $accessToken, $apiUrl;
    // Example 3: make an edit (getting the edit token first).
    $editToken = json_decode($client->makeOAuthCall(
        $accessToken,
        "$apiUrl?action=query&meta=tokens&format=json"
    ))->query->tokens->csrftoken;
    //---
    return $editToken;
}

function do_Api_Query($Params, $addtoken = null)
{
    global $client, $accessToken, $apiUrl;
    //---
    if ($addtoken !== null) $Params['token'] = get_edit_tokens();
    //---
    $Result = $client->makeOAuthCall(
        $accessToken,
        $apiUrl,
        true,
        $Params
    );
    //---
    return json_decode($Result, true);
}

$post = $_GET;
if (isset($post['action'])) {
    $result = do_Api_Query($post);
    echo json_encode($result, true);
}
