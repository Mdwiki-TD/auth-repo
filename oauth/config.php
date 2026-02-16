<?php
//---
use Defuse\Crypto\Key;
//---
include_once __DIR__ . '/../vendor_load.php';
//---
$env = getenv('APP_ENV') ?: 'development';

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
$CONSUMER_KEY        = getenv("CONSUMER_KEY") ?: '';
$CONSUMER_SECRET     = getenv("CONSUMER_SECRET")
?: '';
$COOKIE_KEY          = getenv("COOKIE_KEY") ?: $_ENV['COOKIE_KEY'] ?? '';
$DECRYPT_KEY         = getenv("DECRYPT_KEY") ?: $_ENV['DECRYPT_KEY'] ?? '';
$JWT_KEY             = getenv("JWT_KEY") ?: $_ENV['JWT_KEY'] ?? '';
// ----------------
// ----------------

if (empty($CONSUMER_KEY) || empty($CONSUMER_SECRET)) {
    header("HTTP/1.1 500 Internal Server Error");
    error_log("Required configuration directives not found in environment variables!");
    exit(0);
}

$decrypt_key = Key::loadFromAsciiSafeString($DECRYPT_KEY);
$cookie_key = Key::loadFromAsciiSafeString($COOKIE_KEY);
