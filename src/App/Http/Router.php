<?php

declare(strict_types=1);

namespace App\Http;

use App\Action\CallbackAction;
use App\Action\GetUserAction;
use App\Action\LoginAction;
use App\Action\LogoutAction;
use App\Action\ViewAction;

/**
 * Minimal HTTP router.
 *
 * Dispatches the incoming request to the appropriate Action based on the
 * `?a=` query-string parameter.  When no action is specified the view is
 * rendered.
 */
final class Router
{
    /** @var array<string, class-string> */
    private const ACTION_MAP = [
        'login'      => LoginAction::class,
        'callback'   => CallbackAction::class,
        'logout'     => LogoutAction::class,
        'get_user'   => GetUserAction::class,
        'user_infos' => GetUserAction::class,
    ];

    public function dispatch(): void
    {
        // Optional debug mode
        if (isset($_REQUEST['test'])) {
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');
            error_reporting(E_ALL);
        }

        // No action or only ?test → render view
        $onlyTest = count($_GET) === 1 && isset($_GET['test']);
        if ($_GET === [] || $onlyTest) {
            (new ViewAction())->execute();
            return;
        }

        $action = $_GET['a'] ?? 'user_infos';

        if (!isset(self::ACTION_MAP[$action])) {
            return; // unknown action → do nothing (matches legacy)
        }

        $class = self::ACTION_MAP[$action];
        (new $class())->execute();
    }
}
