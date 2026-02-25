<?php

declare(strict_types=1);

// Set test environment
putenv('APP_ENV=testing');
putenv('DB_HOST=localhost:3306');
putenv('DB_NAME=s54732__mdwiki');
putenv('DB_NAME_NEW=s54732__mdwiki_new');
putenv('TOOL_TOOLSDB_USER=root');
putenv('TOOL_TOOLSDB_PASSWORD=root11');
putenv('CONSUMER_KEY=test_consumer_key');
putenv('CONSUMER_SECRET=test_consumer_secret');

// Set encryption keys directly (these must be set before loading config)
// These are test keys generated for Defuse Crypto
putenv('COOKIE_KEY=def000008f0992fd44f7b71bc86a13c50ffa0295fabd0b8b008fc19d75774746ae6ef19e0328d36d9b457496158ae01fa22dc7638759aadf6c45fd4cda76edb865b0222f');
putenv('DECRYPT_KEY=def000005b2df5554c4d4f3edb8dffbb27da983f3bd3e121aedf49608a799d973f840ce936bd8570b334d5fab61e2121d9252d55dbb79f02f0bf6f5262590c149438e60b');
putenv('JWT_KEY=c4179b8a35d373e222e351b2d96f79c1c21b6c697d450f6c26d73518e8836ec0');

// Also set in $_ENV for compatibility
$_ENV['COOKIE_KEY'] = getenv('COOKIE_KEY');
$_ENV['DECRYPT_KEY'] = getenv('DECRYPT_KEY');
$_ENV['JWT_KEY'] = getenv('JWT_KEY');
$_ENV['CONSUMER_KEY'] = getenv('CONSUMER_KEY');
$_ENV['CONSUMER_SECRET'] = getenv('CONSUMER_SECRET');
$_ENV['APP_ENV'] = 'testing';

// Set server variables for testing
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['HTTP_HOST'] = 'localhost';

// Load vendor autoloader
require_once __DIR__ . '/../src/vendor_load.php';

// Load config which will pick up the environment variables
require_once __DIR__ . '/../src/oauth/config.php';
