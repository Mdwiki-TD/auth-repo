<?php
//---
include_once __DIR__ . '/../vendor_load.php';
//---
use Defuse\Crypto\Key;
//---
$domain = $_SERVER['SERVER_NAME'] ?? 'localhost';
$gUserAgent = 'mdwiki MediaWiki OAuth Client/1.0';
$oauthUrl = 'https://meta.wikimedia.org/w/index.php?title=Special:OAuth';

// Make the api.php URL from the OAuth URL.
$apiUrl = preg_replace('/index\.php.*/', 'api.php', $oauthUrl);

// ----------------
// ----------------
$consumerKey        = $ini['consumerKey'] ?? '';
$consumerSecret     = $ini['consumerSecret'] ?? '';
$cookie_key_str     = $ini['cookie_key'] ?? '';
$decrypt_key_str    = $ini['decrypt_key'] ?? '';
$jwt_key            = $ini['jwt_key'] ?? '';
// ----------------
// ----------------

$decrypt_key = Key::loadFromAsciiSafeString($decrypt_key_str);
$cookie_key = Key::loadFromAsciiSafeString($cookie_key_str);

if (empty($consumerKey) || empty($consumerSecret)) {
    header("HTTP/1.1 500 Internal Server Error");
    echo 'Required configuration directives not found in ini file';
    exit(0);
}
