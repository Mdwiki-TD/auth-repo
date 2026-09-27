<?php

use OAuth\Settings\Settings;

use function OAuth\Utils\create_return_to;

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

foreach (['username'] as $name) {
    setcookie($name, '', $cookieOpts);
}

$return_to = create_return_to($_SERVER['HTTP_REFERER'] ?? '') ?: '/Translation_Dashboard/index.php';

header("Location: $return_to");
