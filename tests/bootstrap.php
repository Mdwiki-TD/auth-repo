<?php

declare(strict_types=1);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Bootstrap for PHPUnit tests
// Sets up environment variables so no real DB/OAuth is needed

// Set test environment
putenv('APP_ENV=testing');
putenv('DB_HOST_TOOLS=localhost:3306');
putenv('DB_NAME=s54732__mdwikiz');

putenv('TOOL_TOOLSDB_USER=root');
putenv('TOOL_TOOLSDB_PASSWORD=root11');
putenv('CONSUMER_KEY=test_consumer_key');
putenv('CONSUMER_SECRET=test_consumer_secret');

// Set encryption keys directly (these must be set before loading config)
// These are test keys generated for Defuse Crypto

putenv('COOKIE_KEY=' . \Defuse\Crypto\Key::createNewRandomKey()->saveToAsciiSafeString());
putenv('CRYPTO_KEY=' . \Defuse\Crypto\Key::createNewRandomKey()->saveToAsciiSafeString());

// Also set in $_ENV for compatibility
$_ENV['COOKIE_KEY'] = getenv('COOKIE_KEY');
$_ENV['CRYPTO_KEY'] = getenv('CRYPTO_KEY');
$_ENV['CONSUMER_KEY'] = getenv('CONSUMER_KEY');
$_ENV['CONSUMER_SECRET'] = getenv('CONSUMER_SECRET');
$_ENV['APP_ENV'] = 'testing';

// Set server variables for testing
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['HTTP_HOST'] = 'localhost';

// Load vendor autoloader
require_once dirname(__DIR__) . '/src/bootstrap.php';
