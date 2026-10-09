<?php
// src/bootstrap.php

/**
 * WARNING / DEPENDENCY NOTICE:
 *
 * The file used in:
 * https://github.com/Mdwiki-TD/mdwiki.toolforge.org/blob/main/src/public_html/userinfos_wrap.php
 *  - ```include_once __DIR__ . '/auth/bootstrap.php';```
 * Any structural or behavioral changes made to this file must be synchronized
 * and reflected in the referenced file to avoid breaking external functionality.
 */
$env = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'development');

if (isset($_REQUEST['test']) && $env !== "production") {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
};


if ($env === 'development' && file_exists(__DIR__ . '/dev/load_env.php')) {
    include_once __DIR__ . '/dev/load_env.php';
}

include_once __DIR__ . '/vendor_load.php';
include_once __DIR__ . '/app/bootstrap.php';

\OAuth\User\CurrentUser::ensureSessionStarted();
