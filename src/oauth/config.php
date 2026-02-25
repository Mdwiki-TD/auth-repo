<?php
//---
use Defuse\Crypto\Key;
//---
include_once __DIR__ . '/../vendor_load.php';
include_once __DIR__ . '/settings.php';

// Get the singleton Settings instance
$settings = Settings::getInstance();

// Backward-compatible global variables for existing code
global $domain, $gUserAgent, $oauthUrl, $apiUrl;
global $CONSUMER_KEY, $CONSUMER_SECRET, $COOKIE_KEY, $DECRYPT_KEY, $JWT_KEY;
global $cookie_key, $decrypt_key;

$domain = $settings->domain;
$gUserAgent = $settings->userAgent;
$oauthUrl = $settings->oauthUrl;
$apiUrl = $settings->apiUrl;
$CONSUMER_KEY = $settings->consumerKey;
$CONSUMER_SECRET = $settings->consumerSecret;
$JWT_KEY = $settings->jwtKey;
$COOKIE_KEY = $settings->cookieKey ? $settings->cookieKey->saveToAsciiSafeString() : '';
$DECRYPT_KEY = $settings->decryptKey ? $settings->decryptKey->saveToAsciiSafeString() : '';
$cookie_key = $settings->cookieKey;
$decrypt_key = $settings->decryptKey;
