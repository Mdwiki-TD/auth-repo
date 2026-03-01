<?php

declare(strict_types=1);

namespace App\Action;

use App\Http\UserResolver;

/**
 * Render the HTML view showing login status.
 *
 * Includes the site header and produces the same card markup as the legacy view.
 */
final class ViewAction
{
    public function execute(): void
    {
        // Include site header (same paths as legacy)
        if (str_starts_with(__DIR__, 'I:')) {
            include_once __DIR__ . '/../../../../mdwiki/public_html/header.php';
        } else {
            include_once __DIR__ . '/../../../header.php';
        }

        $username = (new UserResolver())->resolve();

        $message = <<<HTML
            Go to this URL to authorize this tool:<br />
            <a href='/auth/index.php?a=login'>Login</a><br />
        HTML;

        if ($username !== '') {
            $safeUsername = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
            $message = <<<HTML
                You are authenticated as {$safeUsername}.<br />
                <a href='/auth/index.php?a=logout'>logout</a>
            HTML;
        }

        echo <<<HTML
            <div class="card">
                <div class="card-header">
                    Auth!
                </div>
                <div class="card-body">
                    {$message}
                </div>
            </div>
        HTML;
    }
}
