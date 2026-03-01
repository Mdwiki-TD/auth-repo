<?php

declare(strict_types=1);

namespace App\Action;

use App\Config;
use App\Http\CookieManager;
use App\Http\Helpers;

/**
 * Clear session and cookies, then redirect.
 */
final class LogoutAction
{
    public function execute(): void
    {
        $config  = Config::getInstance();
        $cookies = new CookieManager($config);

        session_start();
        session_destroy();

        $cookies->remove('username');
        $cookies->remove('accesskey');
        $cookies->remove('access_secret');

        $returnTo = Helpers::createReturnTo($_SERVER['HTTP_REFERER'] ?? '')
            ?: '/Translation_Dashboard/index.php';

        header("Location: {$returnTo}");
    }
}
