<?php
// src/login.php

use OAuth\Controllers\LoginController;

include_once __DIR__ . '/bootstrap.php';

$env = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'development');

if ($env === 'development' && file_exists(__DIR__ . '/dev/DevLoginController.php')) {
    include_once __DIR__ . '/dev/DevLoginController.php';
    if (class_exists('\OAuth\Controllers\DevLoginController')) {
        (new \OAuth\Controllers\DevLoginController())->handle();
    }
}

(new LoginController())->handle();
