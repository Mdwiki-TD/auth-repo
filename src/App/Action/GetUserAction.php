<?php

declare(strict_types=1);

namespace App\Action;

use App\Http\UserResolver;

/**
 * Return JSON `{"username":"..."}` for the currently authenticated user.
 */
final class GetUserAction
{
    public function execute(): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }

        $username = (new UserResolver())->resolve();

        echo json_encode(['username' => $username]);
    }
}
