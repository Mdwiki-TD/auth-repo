<?php

declare(strict_types=1);

// Set test environment first
putenv('APP_ENV=testing');
putenv('DB_HOST=localhost:3306');
putenv('DB_NAME=s54732__mdwiki');
putenv('DB_NAME_NEW=s54732__mdwiki_new');
putenv('TOOL_TOOLSDB_USER=root');
putenv('TOOL_TOOLSDB_PASSWORD=root11');

// Set server variables for testing
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['HTTP_HOST'] = 'localhost';

// Load vendor autoloader first
require_once __DIR__ . '/../src/vendor_load.php';

use Defuse\Crypto\Key;

// Generate test encryption keys BEFORE loading config
$key = Key::createNewRandomKey();
putenv('COOKIE_KEY=' . $key->saveToAsciiSafeString());
$_ENV['COOKIE_KEY'] = $key->saveToAsciiSafeString();

$key = Key::createNewRandomKey();
putenv('DECRYPT_KEY=' . $key->saveToAsciiSafeString());
$_ENV['DECRYPT_KEY'] = $key->saveToAsciiSafeString();

// Generate a random JWT key
$jwtKey = bin2hex(random_bytes(32));
putenv('JWT_KEY=' . $jwtKey);
$_ENV['JWT_KEY'] = $jwtKey;

// Now load config which will pick up the keys
require_once __DIR__ . '/../src/oauth/config.php';
