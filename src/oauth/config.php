<?php
//---
use Defuse\Crypto\Key;
//---
include_once __DIR__ . '/../vendor_load.php';
//---
$env = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'development');

if ($env === 'development' && file_exists(__DIR__ . '/load_env.php')) {
    include_once __DIR__ . '/load_env.php';
}
$domain = $_SERVER['SERVER_NAME'] ?? 'localhost';
$gUserAgent = 'mdwiki MediaWiki OAuth Client/1.0';
$oauthUrl = 'https://meta.wikimedia.org/w/index.php?title=Special:OAuth';

// Make the api.php URL from the OAuth URL.
$apiUrl = preg_replace('/index\.php.*/', 'api.php', $oauthUrl);

// ----------------
// ----------------
$CONSUMER_KEY        = getenv("CONSUMER_KEY") ?: $_ENV['CONSUMER_KEY'] ?? '';
$CONSUMER_SECRET     = getenv("CONSUMER_SECRET") ?: $_ENV['CONSUMER_SECRET'] ?? '';
$COOKIE_KEY          = getenv("COOKIE_KEY") ?: $_ENV['COOKIE_KEY'] ?? '';
$DECRYPT_KEY         = getenv("DECRYPT_KEY") ?: $_ENV['DECRYPT_KEY'] ?? '';
$JWT_KEY             = getenv("JWT_KEY") ?: $_ENV['JWT_KEY'] ?? '';
// ----------------
// ----------------

if ($env === "production" && (empty($CONSUMER_KEY) || empty($CONSUMER_SECRET) || empty($COOKIE_KEY) || empty($DECRYPT_KEY) || empty($JWT_KEY))) {
    header("HTTP/1.1 500 Internal Server Error");
    error_log("Required configuration directives not found in environment variables!");
    echo 'Required configuration directives not found';
    exit(0);
}

$cookie_key  = $COOKIE_KEY ? Key::loadFromAsciiSafeString($COOKIE_KEY) : null;
$decrypt_key = $DECRYPT_KEY ? Key::loadFromAsciiSafeString($DECRYPT_KEY) : null;
$jwt_key     = $JWT_KEY;
