<?php
// src/app/controllers/LogoutController.php

namespace OAuth\Controllers;

use OAuth\User\CurrentUser;

use function OAuth\Utils\create_return_to;

class LogoutController
{
    /**
     * Executes the logout process and redirects the user.
     */
    public function handle(): void
    {
        $currentUser = CurrentUser::getInstance();
        $currentUser->Logout();

        $return_to = $this->getReturnTo();

        header("Location: $return_to");
        exit;
    }

    /**
     * Determines the URL the user should be redirected to after logout.
     */
    private function getReturnTo(): string
    {
        $referer = $_SERVER['HTTP_REFERER'] ?? '';

        return create_return_to($referer) ?: '/Translation_Dashboard/index.php';
    }
}
