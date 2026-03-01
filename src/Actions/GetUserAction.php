<?php

declare(strict_types=1);

namespace OAuth\Actions;

use OAuth\Services\CookieService;
use OAuth\Repository\TokenRepository;

/**
 * Handles retrieving the current user's information.
 * 
 * This action:
 * 1. Checks for authenticated user via cookies/session
 * 2. Validates that tokens still exist in database
 * 3. Returns user information or clears invalid sessions
 */
final class GetUserAction extends BaseAction
{
    private CookieService $cookieService;
    private TokenRepository $tokenRepository;
    private ?string $username = null;
    private bool $outputJson;

    public function __construct(
        ?\Settings $settings = null,
        ?CookieService $cookieService = null,
        ?TokenRepository $tokenRepository = null,
        bool $outputJson = true
    ) {
        parent::__construct($settings);
        $this->cookieService = $cookieService ?? CookieService::fromSettings();
        $this->tokenRepository = $tokenRepository ?? TokenRepository::fromSettings();
        $this->outputJson = $outputJson;
    }

    public function execute(): void
    {
        // Always define the global for backward compatibility
        $this->defineGlobalUsername();
        
        // Only output JSON if requested (e.g., for get_user action)
        if ($this->outputJson) {
            $this->jsonResponse(['username' => $this->getUsername()]);
        }
    }

    /**
     * Get the authenticated username if valid.
     */
    public function getUsername(): string
    {
        if ($this->username !== null) {
            return $this->username;
        }

        $this->initializeSession();
        $username = $this->resolveUsername();

        if (!empty($username) && !$this->isDevelopment()) {
            $username = $this->validateUserTokens($username);
        }

        $this->username = $username;
        return $username;
    }

    /**
     * Initialize session with appropriate settings.
     */
    private function initializeSession(): void
    {
        $domain = $this->settings->domain;
        $secure = $domain !== 'localhost';

        if ($domain !== 'localhost' && session_status() === PHP_SESSION_NONE) {
            session_name('mdwikitoolforgeoauth');
            session_set_cookie_params(0, '/', $domain, $secure, $secure);
        }

        $this->ensureSession();
    }

    /**
     * Resolve the username from cookies or session.
     */
    private function resolveUsername(): string
    {
        if ($this->isDevelopment()) {
            return $_SESSION['username'] ?? '';
        }

        return $this->cookieService->get('username');
    }

    /**
     * Validate that the user's tokens exist in the database.
     * If not, clear the invalid session.
     */
    private function validateUserTokens(string $username): string
    {
        if (!$this->tokenRepository->hasTokens($username)) {
            $this->clearInvalidSession();
            return '';
        }

        return $username;
    }

    /**
     * Clear an invalid user session.
     */
    private function clearInvalidSession(): void
    {
        $this->cookieService->delete('username');
        unset($_SESSION['username']);
    }

    /**
     * Get the username as a global constant (for legacy compatibility).
     */
    public function defineGlobalUsername(): void
    {
        $username = $this->getUsername();
        if (!defined('global_username')) {
            define('global_username', $username);
        }
    }
}
