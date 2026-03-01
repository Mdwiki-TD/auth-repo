<?php

declare(strict_types=1);

namespace OAuth\Actions;

use OAuth\Services\CookieService;

/**
 * Handles user logout.
 * 
 * This action:
 * 1. Destroys the session
 * 2. Clears authentication cookies
 * 3. Redirects to the appropriate destination
 */
final class LogoutAction extends BaseAction
{
    private const DEFAULT_REDIRECT = '/Translation_Dashboard/index.php';

    private CookieService $cookieService;

    public function __construct(
        ?\Settings $settings = null,
        ?CookieService $cookieService = null
    ) {
        parent::__construct($settings);
        $this->cookieService = $cookieService ?? CookieService::fromSettings();
    }

    public function execute(): void
    {
        $this->destroySession();
        $this->clearCookies();
        $this->redirectToDestination();
    }

    /**
     * Destroy the current session.
     */
    private function destroySession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        session_destroy();
    }

    /**
     * Clear all authentication cookies.
     */
    private function clearCookies(): void
    {
        $this->cookieService->deleteMultiple([
            'username',
            'accesskey',
            'access_secret',
            'jwt_token',
        ]);
    }

    /**
     * Redirect to the appropriate destination.
     */
    private function redirectToDestination(): void
    {
        $returnTo = $this->createReturnTo($_SERVER['HTTP_REFERER'] ?? '');
        $destination = $returnTo ?: self::DEFAULT_REDIRECT;
        $this->redirect($destination);
    }
}
