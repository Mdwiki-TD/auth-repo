<?php

namespace OAuth\SendEdit;
/*
Usage:
use function OAuth\SendEdit\auth_make_edit;
use function OAuth\SendEdit\do_edit;
use function OAuth\SendEdit\get_edits_tokens;
*/

include_once __DIR__ . '/../vendor_load.php';

use MediaWiki\OAuthClient\Client;
use MediaWiki\OAuthClient\ClientConfig;
use MediaWiki\OAuthClient\Consumer;
use MediaWiki\OAuthClient\Token;

// header( 'Content-type: text/plain' );

// Get the wiki URL and OAuth consumer details from the settings.

function get_edits_tokens($client, $accessToken, $apiUrl)
{
    $response = $client->makeOAuthCall($accessToken, "$apiUrl?action=query&meta=tokens&format=json");
    // ---
    $data = json_decode($response);
    // ---
    if ($data == null || !isset($data->query->tokens->csrftoken)) {
        // Handle error
        echo "<br>get_edits_tokens Error: " . json_last_error() . " " . json_last_error_msg();
        return null;
    }
    // ---
    return $data->query->tokens->csrftoken;
}

function auth_make_edit($title, $text, $summary, $wiki, $access_key, $access_secret)
{
    $settings = \Settings::getInstance();
    // ---
    $wikiOauthUrl = "https://$wiki.wikipedia.org/w/index.php?title=Special:OAuth";
    $apiUrl = "https://$wiki.wikipedia.org/w/api.php";
    // ---
    // Configure the OAuth client with the URL and consumer details.
    $conf = new ClientConfig($wikiOauthUrl);
    $conf->setConsumer(new Consumer($settings->consumerKey, $settings->consumerSecret));
    $conf->setUserAgent($settings->userAgent);
    $client = new Client($conf);
    // ---
    $accessToken = new Token($access_key, $access_secret);
    // ---
    $editToken = get_edits_tokens($client, $accessToken, $apiUrl);
    // ---
    $apiParams = [
        'action' => 'edit',
        'title' => $title,
        // 'section' => 'new',
        'summary' => $summary,
        'text' => $text,
        'token' => $editToken,
        'format' => 'json',
    ];
    // ---
    $req = $client->makeOAuthCall(
        $accessToken,
        $apiUrl,
        true,
        $apiParams
    );
    // ---
    $editResult = json_decode($req, true);
    // ---
    return $editResult;
}
