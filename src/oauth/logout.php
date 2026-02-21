<?php

include_once __DIR__ . '/config.php';

global $domain;
if (!isset($domain)) {
    $domain = $_SERVER['SERVER_NAME'] ?? 'localhost';
}

session_start();
session_destroy();
setcookie('username', '', time() - 3600, "/", $domain, true, true);
setcookie('accesskey', '', time() - 3600, "/", $domain, true, true);
setcookie('access_secret', '', time() - 3600, "/", $domain, true, true);
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
