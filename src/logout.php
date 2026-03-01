<?php
use function OAuth\Utils\create_return_to;

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

$return_to = create_return_to($_SERVER['HTTP_REFERER']) ?: '/Translation_Dashboard/index.php';
//---
// echo json_encode($_SERVER);
header("Location: $return_to");
