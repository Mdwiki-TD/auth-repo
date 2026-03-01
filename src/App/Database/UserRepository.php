<?php

declare(strict_types=1);

namespace App\Database;

/**
 * Manages the `users` table.
 */
final class UserRepository
{
    /**
     * Insert a user row if one does not already exist.
     *
     * @return array<int, array<string, mixed>>
     */
    public function ensureExists(string $username): array
    {
        $sql = <<<'SQL'
            INSERT INTO users (username) SELECT ?
            WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = ?)
        SQL;

        return $this->db()->execute($sql, [$username, $username]);
    }

    private function db(): Connection
    {
        return new Connection();
    }
}
