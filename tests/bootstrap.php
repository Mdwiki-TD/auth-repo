<?php

declare(strict_types=1);

// Set test environment
putenv('APP_ENV=testing');
putenv('DB_HOST=localhost:3306');
putenv('DB_NAME=s54732__mdwiki');
putenv('DB_NAME_NEW=s54732__mdwiki_new');
putenv('TOOL_TOOLSDB_USER=root');
putenv('TOOL_TOOLSDB_PASSWORD=root11');
putenv('CONSUMER_KEY=CONSUMER_KEY');
putenv('CONSUMER_SECRET=CONSUMER_SECRET');

// putenv('COOKIE_KEY='); // already set in phpunit.xml
// putenv('DECRYPT_KEY='); // already set in phpunit.xml
// putenv('JWT_KEY='); // already set in phpunit.xml

// Set server variables for testing
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['HTTP_HOST'] = 'localhost';

// Load vendor autoloader
require_once __DIR__ . '/../src/vendor_load.php';

// Note: Encryption keys are set in phpunit.xml via <env> tags
// This ensures they're available before any test files are loaded

// Load config which will pick up the environment variables
require_once __DIR__ . '/../src/oauth/config.php';
