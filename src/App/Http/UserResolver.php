<?php

declare(strict_types=1);

namespace App\Http;

use App\Config;
use App\Database\TokenRepository;

/**
 * Determine the currently authenticated username from session / cookies.
 *
 * On localhost the session is authoritative; on production the encrypted
 * username cookie is read and verified against the database.
 */
final class UserResolver
{
    private readonly Config          $config;
    private readonly CookieManager   $cookies;
    private readonly TokenRepository $tokens;

    public function __construct(
        ?Config          $config  = null,
        ?CookieManager   $cookies = null,
        ?TokenRepository $tokens  = null,
    ) {
        $this->config  = $config  ?? Config::getInstance();
        $this->cookies = $cookies ?? new CookieManager($this->config);
        $this->tokens  = $tokens  ?? new TokenRepository();
    }

    /**
     * Resolve the current username.
     *
     * Side-effects (matching legacy behaviour):
     *  - Starts or configures the PHP session.
     *  - May clear the username cookie if no access tokens exist.
     *  - May emit an HTML alert to the output buffer.
     */
    public function resolve(): string
    {
        $this->ensureSession();

        $username = $this->cookies->get('username');

        if ($this->config->domain === 'localhost') {
            return $_SESSION['username'] ?? '';
        }

        if ($username === '') {
            return '';
        }

        $access = $this->tokens->get($username);
        if ($access === null) {
            echo Helpers::dangerAlert('No access keys found. Login again.');
            $this->cookies->remove('username');
            unset($_SESSION['username']);
            return '';
        }

        return $username;
    }

    private function ensureSession(): void
    {
        $domain = $this->config->domain;
        $secure = $domain !== 'localhost';

        if ($domain !== 'localhost' && session_status() === PHP_SESSION_NONE) {
            session_name('mdwikitoolforgeoauth');
            session_set_cookie_params(0, '/', $domain, $secure, $secure);
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
}
