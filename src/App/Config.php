<?php

declare(strict_types=1);

namespace App;

use Defuse\Crypto\Key;
use RuntimeException;

/**
 * Immutable application configuration.
 *
 * Reads OAuth credentials and encryption keys from environment variables.
 * In production, all required keys must be present; in development/testing,
 * empty strings are tolerated so that the application can still boot.
 */
final class Config
{
    public readonly string  $domain;
    public readonly string  $userAgent;
    public readonly string  $oauthUrl;
    public readonly string  $apiUrl;
    public readonly string  $consumerKey;
    public readonly string  $consumerSecret;
    public readonly ?Key    $cookieKey;
    public readonly ?Key    $decryptKey;
    public readonly string  $jwtKey;

    private static ?self $instance = null;

    private function __construct()
    {
        $this->domain    = $_SERVER['SERVER_NAME'] ?? 'localhost';
        $this->userAgent = 'mdwiki MediaWiki OAuth Client/1.0';
        $this->oauthUrl  = 'https://meta.wikimedia.org/w/index.php?title=Special:OAuth';
        $this->apiUrl    = (string) preg_replace('/index\.php.*/', 'api.php', $this->oauthUrl);

        $consumerKey    = self::env('CONSUMER_KEY');
        $consumerSecret = self::env('CONSUMER_SECRET');
        $cookieKey      = self::env('COOKIE_KEY');
        $decryptKey     = self::env('DECRYPT_KEY');
        $jwtKey         = self::env('JWT_KEY');

        if (self::env('APP_ENV') === 'production' && (
            $consumerKey === '' || $consumerSecret === '' ||
            $cookieKey === ''   || $decryptKey === ''     || $jwtKey === ''
        )) {
            http_response_code(500);
            error_log('Required configuration directives not found in environment variables!');
            echo 'Required configuration directives not found';
            exit(0);
        }

        $this->consumerKey    = $consumerKey;
        $this->consumerSecret = $consumerSecret;
        $this->jwtKey         = $jwtKey;
        $this->cookieKey      = $cookieKey !== ''  ? Key::loadFromAsciiSafeString($cookieKey)  : null;
        $this->decryptKey     = $decryptKey !== '' ? Key::loadFromAsciiSafeString($decryptKey) : null;
    }

    /**
     * Read a value from the environment.
     *
     * Checks getenv() first, then $_ENV, and falls back to empty string.
     */
    public static function env(string $key): string
    {
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }
        if (array_key_exists($key, $_ENV)) {
            return (string) $_ENV[$key];
        }
        return '';
    }

    /**
     * Singleton accessor – one instance per request.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Reset the singleton (for testing only).
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    private function __clone()
    {
    }

    public function __wakeup(): void
    {
        throw new RuntimeException('Cannot unserialize a singleton.');
    }
}
