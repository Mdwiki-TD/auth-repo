<?php

declare(strict_types=1);

if (isset($_REQUEST['test'])) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
};
/**
 * Modern entry point for the OAuth application.
 *
 * Boots the Composer autoloader, optionally loads a local .env file for
 * development, and dispatches the request through the Router.
 *
 * Produces identical output to the legacy src/index.php entry point.
 */

// Autoloader -----------------------------------------------------------------
$vendorPath = __DIR__ . '/vendor/autoload.php';
if (!file_exists($vendorPath)) {
    $vendorPath = __DIR__ . '/../vendor/autoload.php';
}
require $vendorPath;

include_once __DIR__ . '/include_all.php';

// Development environment loading -------------------------------------------
$env = \App\Config::env('APP_ENV') ?: 'development';
if ($env === 'development' && file_exists(__DIR__ . '/dev/load_env.php')) {
    include_once __DIR__ . '/dev/load_env.php';
}

// Dispatch ------------------------------------------------------------------
(new \App\Http\Router())->dispatch();
