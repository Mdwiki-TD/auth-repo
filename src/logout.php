<?php

$settings = Settings::getInstance();
$domain = $settings->domain;

session_start();
session_destroy();
setcookie('username', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'domain' => $domain,
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax',
]);
setcookie('accesskey', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'domain' => $domain,
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax',
]);
setcookie('access_secret', '', [
    'expires' => time() - 3600,
    'path' => '/',
    'domain' => $domain,
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax',
]);
// echo "You are now logged out. <a href='index.php?a=index'>Log in.</a>";

// return to the previous page
// $return_to = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/Translation_Dashboard/index.php';

$allowed_domains = ['mdwiki.toolforge.org', 'localhost'];
$return_to = '/Translation_Dashboard/index.php';
//---
if (isset($_SERVER['HTTP_REFERER'])) {
    $parsed = parse_url($_SERVER['HTTP_REFERER']);
    if (in_array($parsed['host'], $allowed_domains)) {
        $return_to = $_SERVER['HTTP_REFERER'];
    }
}
//---
// echo json_encode($_SERVER);
header("Location: $return_to");
