<?php
// if (isset($_REQUEST['test'])) {
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// };
include_once __DIR__ . '/include_all.php';

$env = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'development');

if ($env === 'development' && file_exists(__DIR__ . '/dev/dev_login.php')) {
    include_once __DIR__ . '/dev/dev_login.php';
}

include_once __DIR__ . '/actions/login.php';
