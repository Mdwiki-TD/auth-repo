<?php

declare(strict_types=1);

use Defuse\Crypto\Key;

final class Settings
{
    // Private properties — access is controlled via __get()
    private string $domain;
    private string $userAgent;
    private string $oauthUrl;
    private string $apiUrl;
    private string $consumerKey;
    private string $consumerSecret;
    private ?Key   $cookieKey;
    private ?Key   $decryptKey;
    private string $jwtKey;

    private static ?self $instance = null;

    private function __construct()
    {
        $env = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'development');

        if ($env === 'development' && file_exists(__DIR__ . '/load_env.php')) {
            include_once __DIR__ . '/load_env.php';
        }

        $this->domain    = $_SERVER['SERVER_NAME'] ?? 'localhost';
        $this->userAgent = 'mdwiki MediaWiki OAuth Client/1.0';
        $this->oauthUrl  = 'https://meta.wikimedia.org/w/index.php?title=Special:OAuth';
        $this->apiUrl    = preg_replace('/index\.php.*/', 'api.php', $this->oauthUrl);

        $consumerKey    = getenv('CONSUMER_KEY')    ?: $_ENV['CONSUMER_KEY']    ?? '';
        $consumerSecret = getenv('CONSUMER_SECRET') ?: $_ENV['CONSUMER_SECRET'] ?? '';
        $cookieKey      = getenv('COOKIE_KEY')      ?: $_ENV['COOKIE_KEY']      ?? '';
        $decryptKey     = getenv('DECRYPT_KEY')     ?: $_ENV['DECRYPT_KEY']     ?? '';
        $jwtKey         = getenv('JWT_KEY')         ?: $_ENV['JWT_KEY']         ?? '';

        if ($env === 'production' && (
            empty($consumerKey) || empty($consumerSecret) ||
            empty($cookieKey)   || empty($decryptKey)     || empty($jwtKey)
        )) {
            http_response_code(500);
            error_log('Required configuration directives not found in environment variables!');
            echo 'Required configuration directives not found';
            exit(0);
        }

        $this->consumerKey    = $consumerKey;
        $this->consumerSecret = $consumerSecret;
        $this->jwtKey         = $jwtKey;
        $this->cookieKey      = $cookieKey  ? Key::loadFromAsciiSafeString($cookieKey)  : null;
        $this->decryptKey     = $decryptKey ? Key::loadFromAsciiSafeString($decryptKey) : null;
    }

    /**
     * Allow reading private properties from outside the class.
     * Mimics the behaviour of readonly properties (PHP 8.1+).
     *
     * @return mixed
     */
    public function __get(string $name)
    {
        if (property_exists($this, $name)) {
            return $this->$name;
        }

        throw new \RuntimeException("Undefined setting: {$name}");
    }

    /**
     * Prevent modification from outside the class.
     * Mimics the behaviour of readonly properties (PHP 8.1+).
     *
     * @param mixed $value
     */
    public function __set(string $name, $value): void
    {
        throw new \RuntimeException("Settings are read-only. Cannot set: {$name}");
    }

    /**
     * Returns the single instance of Settings for the lifetime of the request.
     * Equivalent to @lru_cache(maxsize=1) in Python.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    // Prevent cloning and unserialization of the singleton instance
    private function __clone() {}

    public function __wakeup(): void
    {
        throw new \RuntimeException('Cannot unserialize a singleton.');
    }
}
