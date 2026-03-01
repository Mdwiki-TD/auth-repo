<?php

declare(strict_types=1);

namespace App\Database;

use App\Security\EncryptionService;

/**
 * Persists and retrieves OAuth access tokens.
 *
 * Wraps both the legacy `access_keys` table and the newer `keys_new` table
 * so the callback action can write to both, matching legacy behaviour.
 */
final class TokenRepository
{
    private readonly EncryptionService $encryption;

    public function __construct(?EncryptionService $encryption = null)
    {
        $this->encryption = $encryption ?? new EncryptionService();
    }

    // ──────────────────────────────────────────────────────
    //  Legacy table: access_keys  (cookie-key encryption)
    // ──────────────────────────────────────────────────────

    /**
     * Store (or update) an access token in the legacy `access_keys` table.
     */
    public function addLegacy(string $username, string $accessKey, string $accessSecret): void
    {
        $params = [
            trim($username),
            $this->encryption->encrypt($accessKey),
            $this->encryption->encrypt($accessSecret),
        ];

        $sql = <<<'SQL'
            INSERT INTO access_keys (user_name, access_key, access_secret)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                access_key    = VALUES(access_key),
                access_secret = VALUES(access_secret),
                created_at    = NOW()
        SQL;

        $this->db()->execute($sql, $params);
    }

    /**
     * Retrieve access tokens from the legacy table.
     *
     * @return array{access_key: string, access_secret: string}|null
     */
    public function getLegacy(string $username): ?array
    {
        $username = trim($username);

        $sql = <<<'SQL'
            SELECT access_key, access_secret
            FROM access_keys
            WHERE user_name = ?
        SQL;

        $rows = $this->db()->fetch($sql, [$username]);
        if ($rows === []) {
            return null;
        }

        return [
            'access_key'    => $this->encryption->decrypt($rows[0]['access_key']),
            'access_secret' => $this->encryption->decrypt($rows[0]['access_secret']),
        ];
    }

    /**
     * Delete access tokens from the legacy table.
     */
    public function deleteLegacy(string $username): void
    {
        $this->db()->execute(
            'DELETE FROM access_keys WHERE user_name = ?',
            [trim($username)]
        );
    }

    // ──────────────────────────────────────────────────────
    //  New table: keys_new  (decrypt-key encryption)
    // ──────────────────────────────────────────────────────

    /**
     * Store (or update) an access token in the `keys_new` table.
     */
    public function addNew(string $username, string $accessKey, string $accessSecret): void
    {
        $userId = $this->getUserId($username);

        if ($userId !== null) {
            $sql = <<<'SQL'
                UPDATE keys_new
                SET a_k = ?, a_s = ?, created_at = NOW()
                WHERE id = ?
            SQL;

            $this->db()->execute($sql, [
                $this->encryption->encrypt($accessKey, 'decrypt'),
                $this->encryption->encrypt($accessSecret, 'decrypt'),
                $userId,
            ]);
            return;
        }

        $sql = <<<'SQL'
            INSERT INTO keys_new (u_n, a_k, a_s)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE
                a_k        = VALUES(a_k),
                a_s        = VALUES(a_s),
                created_at = NOW()
        SQL;

        $this->db()->execute($sql, [
            $this->encryption->encrypt(trim($username), 'decrypt'),
            $this->encryption->encrypt($accessKey, 'decrypt'),
            $this->encryption->encrypt($accessSecret, 'decrypt'),
        ]);
    }

    /**
     * Retrieve access tokens from the `keys_new` table.
     *
     * @return array{access_key: string, access_secret: string}|null
     */
    public function getNew(string $username): ?array
    {
        $userId = $this->getUserId(trim($username));
        if ($userId === null) {
            return null;
        }

        $sql = <<<'SQL'
            SELECT a_k, a_s
            FROM keys_new
            WHERE id = ?
        SQL;

        $rows = $this->db()->fetch($sql, [$userId]);
        if ($rows === []) {
            return null;
        }

        return [
            'access_key'    => $this->encryption->decrypt($rows[0]['a_k'], 'decrypt'),
            'access_secret' => $this->encryption->decrypt($rows[0]['a_s'], 'decrypt'),
        ];
    }

    /**
     * Delete access tokens from the `keys_new` table.
     */
    public function deleteNew(string $username): void
    {
        $userId = $this->getUserId(trim($username));
        if ($userId === null) {
            return;
        }

        $this->db()->execute('DELETE FROM keys_new WHERE id = ?', [$userId]);
    }

    /**
     * Look up the internal user-ID in `keys_new` by decrypting the stored
     * username column and comparing it to the provided value.
     */
    public function getUserId(string $username): ?int
    {
        /** @var array<string, int> */
        static $cache = [];

        $username = trim($username);
        if (isset($cache[$username])) {
            return $cache[$username];
        }

        $rows = $this->db()->fetch('SELECT id, u_n FROM keys_new');
        foreach ($rows as $row) {
            $decrypted = $this->encryption->decrypt((string) $row['u_n'], 'decrypt');
            if ($decrypted === $username) {
                $id = (int) $row['id'];
                $cache[$username] = $id;
                return $id;
            }
        }

        return null;
    }

    // ──────────────────────────────────────────────────────
    //  Convenience: try new table first, fall back to legacy
    // ──────────────────────────────────────────────────────

    /**
     * Retrieve tokens, preferring `keys_new` over `access_keys`.
     *
     * @return array{access_key: string, access_secret: string}|null
     */
    public function get(string $username): ?array
    {
        return $this->getNew($username) ?? $this->getLegacy($username);
    }

    private function db(): Connection
    {
        return new Connection();
    }
}
