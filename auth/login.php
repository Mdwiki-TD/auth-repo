<?php

use MediaWiki\OAuthClient\Client;
use MediaWiki\OAuthClient\ClientConfig;
use MediaWiki\OAuthClient\Consumer;

include_once __DIR__ . '/u.php';

// Configure the OAuth client with the URL and consumer details.
$conf = new ClientConfig($oauthUrl);
$conf->setConsumer(new Consumer($consumerKey, $consumerSecret));
$conf->setUserAgent($gUserAgent);
$client = new Client($conf);

function create_callback_url($url)
{
    //---
    $state = [];
    // ?action=login&cat=RTT&depth=1&code=&type=lead
    //---
    // $return_to = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    $return_to = '';
    //---
    $allowed_domains = ['mdwiki.toolforge.org', 'localhost'];
    //---
    if (isset($_SERVER['HTTP_REFERER'])) {
        $parsed = parse_url($_SERVER['HTTP_REFERER']);
        if (in_array($parsed['host'], $allowed_domains)) {
            $return_to = $_SERVER['HTTP_REFERER'];
        }
    }
    //---
    if (!empty($return_to)) {
        $state['return_to'] = $return_to;
    }
    //---
    foreach (['cat', 'code', 'type', 'test', 'doit'] as $key) {
        // $da = $_GET[$key] ?? '';
        $da = filter_input(INPUT_GET, $key, FILTER_SANITIZE_STRING);
        if (!empty($da)) {
            $state[$key] = $da;
        }
    };
    //---
    $sta = "";
    if (!empty($state)) {
        $sta = '&' . http_build_query($state);
    }
    //---
    // echo $sta;
    //---
    $oauth_call = $url . $sta;
    //---
    return $oauth_call;
}
// ---
$call_back_url = create_callback_url('https://mdwiki.toolforge.org/auth/index.php?a=callback');
// ---
$client->setCallback($call_back_url);

// Send an HTTP request to the wiki to get the authorization URL and a Request Token.
// These are returned together as two elements in an array (with keys 0 and 1).
list($authUrl, $token) = $client->initiate();

// Store the Request Token in the session. We will retrieve it from there when the user is sent back
// from the wiki (see demo/callback.php).
if (session_status() === PHP_SESSION_NONE) session_start();
$_SESSION['request_key'] = $token->key;
$_SESSION['request_secret'] = $token->secret;

// Redirect the user to the authorization URL. This is usually done with an HTTP redirect, but we're
// making it a manual link here so you can see everything in action.
echo "Go to this URL to authorize this demo:<br /><a href='$authUrl'>$authUrl</a>";
if ($_SERVER['SERVER_NAME'] !== 'localhost') {
    header("Location: $authUrl");
    exit(0);
}
