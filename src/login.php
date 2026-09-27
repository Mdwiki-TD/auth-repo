<?php

include_once __DIR__ . '/include_all.php';

$env = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'development');

if ($env === 'development' && file_exists(__DIR__ . '/dev/dev_login.php')) {
    include_once __DIR__ . '/dev/dev_login.php';
}

include_once __DIR__ . '/app/actions/login.php';
