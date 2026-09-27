<?php

use function OAuth\Utils\create_return_to;
use OAuth\Settings\Settings;

$settings = Settings::getInstance();
$domain = $settings->domain;
$secure = $domain !== 'localhost';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_SESSION = [];
session_destroy();

$cookieOpts = [
    'expires'  => time() - 3600,
    'path'     => '/',
    'domain'   => $domain,
    'secure'   => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
];

foreach (['jwt_token', 'username'] as $name) {
    setcookie($name, '', $cookieOpts);
}

$return_to = create_return_to($_SERVER['HTTP_REFERER'] ?? '') ?: '/Translation_Dashboard/index.php';

header("Location: $return_to");
