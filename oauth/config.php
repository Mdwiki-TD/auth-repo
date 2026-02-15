<?php
//---
include_once __DIR__ . '/../vendor_load.php';
use Defuse\Crypto\Key;
//---
$env = getenv('APP_ENV') ?: 'development';

if ($env === 'development') {
    include_once __DIR__ . '/load_env.php';
}
//---
$gUserAgent = 'mdwiki MediaWiki OAuth Client/1.0';
$oauthUrl = 'https://meta.wikimedia.org/w/index.php?title=Special:OAuth';

// Make the api.php URL from the OAuth URL.
$apiUrl = preg_replace('/index\.php.*/', 'api.php', $oauthUrl);

$ROOT_PATH = getenv("HOME") ?: 'I:/mdwiki/mdwiki';
$inifile = $ROOT_PATH . '/confs/OAuthConfig.ini';
$ini = parse_ini_file($inifile);
//---
if ($ini === false) {
    header("HTTP/1.1 500 Internal Server Error");
    error_log("Failed to read ini file: $inifile");
    echo "Server configuration error. Please contact the administrator.";
    exit(0);
}

$domain = $_SERVER['SERVER_NAME'] ?? 'localhost';

// ----------------
// ----------------
$consumerKey        = $ini['consumerKey'] ?? '';
$consumerSecret     = $ini['consumerSecret'] ?? '';
$cookie_key_str     = $ini['cookie_key'] ?? '';
$decrypt_key_str    = $ini['decrypt_key'] ?? '';
$jwt_key            = $ini['jwt_key'] ?? '';
// ----------------
// ----------------

if (empty($consumerKey) || empty($consumerSecret)) {
    header("HTTP/1.1 500 Internal Server Error");
    echo 'Required configuration directives not found in ini file';
    exit(0);
}

$cookie_key  = Key::loadFromAsciiSafeString($cookie_key_str);
$decrypt_key = Key::loadFromAsciiSafeString($decrypt_key_str);
