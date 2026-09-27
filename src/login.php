<?php
// src/login.php

use OAuth\Controllers\LoginController;
use OAuth\Controllers\DevLoginController;

include_once __DIR__ . '/include_all.php';

$env = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'development');

if ($env === 'development' && file_exists(__DIR__ . '/dev/DevLoginController.php')) {
    include_once __DIR__ . '/dev/DevLoginController.php';
    (new DevLoginController())->handle();
}

(new LoginController())->handle();
