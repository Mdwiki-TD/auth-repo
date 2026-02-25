<?php

declare(strict_types=1);

use Defuse\Crypto\Key;

/**
 * @property string $domain
 * @property string $userAgent
 * @property string $oauthUrl
 * @property string $apiUrl
 * @property string $consumerKey
 * @property string $consumerSecret
 * @property Key|null $cookieKey
 * @property Key|null $decryptKey
 * @property string $jwtKey
 */
final class Settings
{
    // Private properties — access is controlled via __get()
    public string $domain;
    public string $userAgent;
    public string $oauthUrl;
    public string $apiUrl;
    public string $consumerKey;
    public string $consumerSecret;
    public ?Key   $cookieKey;
    public ?Key   $decryptKey;
    public string $jwtKey;

    private static ?self $instance = null;

    private function __construct()
    {
        $this->domain    = $_SERVER['SERVER_NAME'] ?? 'localhost';
        $this->userAgent = 'mdwiki MediaWiki OAuth Client/1.0';
        $this->oauthUrl  = 'https://meta.wikimedia.org/w/index.php?title=Special:OAuth';
        $this->apiUrl    = preg_replace('/index\.php.*/', 'api.php', $this->oauthUrl);

        $consumerKey    = $this->envVar('CONSUMER_KEY');
        $consumerSecret = $this->envVar('CONSUMER_SECRET');
        $cookieKey      = $this->envVar('COOKIE_KEY');
        $decryptKey     = $this->envVar('DECRYPT_KEY');
        $jwtKey         = $this->envVar('JWT_KEY');

        if (getenv('APP_ENV') === 'production' && (
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

    private function envVar(string $key)
    {
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }

        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        return "";
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
