<?php
require_once __DIR__ . '/access_helps.php';
require_once __DIR__ . '/access_helps_new.php';
require_once __DIR__ . '/jwt_config.php';

use function OAuth\JWT\create_jwt;
use MediaWiki\OAuthClient\Client;
use MediaWiki\OAuthClient\ClientConfig;
use MediaWiki\OAuthClient\Consumer;
use MediaWiki\OAuthClient\Token;
use function OAuth\Helps\add_to_cookies;
use function OAuth\AccessHelps\add_access_to_dbs;
use function OAuth\AccessHelpsNew\add_access_to_dbs_new;
use function OAuth\AccessHelps\sql_add_user;

if (!isset($_GET['oauth_verifier'])) {
	echo "This page should only be access after redirection back from the wiki.";
	echo <<<HTML
		Go to this URL to authorize this tool:<br />
		<a href='index.php?a=login'>Login</a><br />
	HTML;
	exit(1);
}

// Configure the OAuth client with the URL and consumer details.
$conf = new ClientConfig($oauthUrl);
$conf->setConsumer(new Consumer($consumerKey, $consumerSecret));
$conf->setUserAgent($gUserAgent);
$client = new Client($conf);

// Get the Request Token's details from the session and create a new Token object.
if (session_status() === PHP_SESSION_NONE) session_start();
$requestToken = new Token(
	$_SESSION['request_key'],
	$_SESSION['request_secret']
);

// Send an HTTP request to the wiki to retrieve an Access Token.
$accessToken1 = $client->complete($requestToken, $_GET['oauth_verifier']);

// At this point, the user is authenticated, and the access token can be used to make authenticated
// API requests to the wiki. You can store the Access Token in the session or other secure
// user-specific storage and re-use it for future requests.
// $_SESSION['accesskey'] = $accessToken1->key;

// $_SESSION['access_secret'] = $accessToken1->secret;

// You also no longer need the Request Token.
unset($_SESSION['request_key'], $_SESSION['request_secret']);

// include_once __DIR__ . '/userinfo.php';
// The demo continues in demo/edit.php
// echo "Continue to <a href='index.php?a=edit'>edit</a><br>";
echo "Continue to <a href='index.php?a=index'>index</a><br>";

// $accessToken = new Token($_SESSION['accesskey'], $_SESSION['access_secret']);
$accessToken = new Token($accessToken1->key, $accessToken1->secret);

$ident = $client->identify($accessToken);
// Use htmlspecialchars to properly encode the output and prevent XSS vulnerabilities.
echo "You are authenticated as " . htmlspecialchars($ident->username, ENT_QUOTES, 'UTF-8') . ".\n\n";
//---
$_SESSION['username'] = $ident->username;

$jwt = create_jwt($ident->username);
add_to_cookies('jwt_token', $jwt);

if (!isset($_SESSION['csrf_tokens']) || !is_array($_SESSION['csrf_tokens'])) {
	$_SESSION['csrf_tokens'] = [];
}

add_access_to_dbs_new($ident->username, $accessToken1->key, $accessToken1->secret);
add_access_to_dbs($ident->username, $accessToken1->key, $accessToken1->secret);

// ---
sql_add_user($ident->username);
// ---
add_to_cookies('username', $ident->username);

echo "Continue to <a href='index.php?a=index'>index</a><br>";

$test = $_GET['test'] ?? '';
$return_to = $_GET['return_to'] ?? '';
// ---
if (!empty($return_to) && (strpos($return_to, '/Translation_Dashboard/index.php') === false)) {
	// $newurl = $return_to;
	$newurl = filter_var($return_to, FILTER_VALIDATE_URL) ? $return_to : '/Translation_Dashboard/index.php';
} else {
	$state = [];
	foreach (['cat', 'code', 'type', 'doit'] as $key) {
		// $da1 = $_GET[$key] ?? '';
		$da1 = filter_input(INPUT_GET, $key, FILTER_SANITIZE_STRING);
		if (!empty($da1)) {
			$state[$key] = $da1;
		};
	};
	//---
	$state = http_build_query($state);
	//---
	$newurl = "/Translation_Dashboard/index.php?$state";
}
// ---
// echo "header('Location: $newurl');<br>";
//---
if (empty($test)) {
	header("Location: $newurl");
}
