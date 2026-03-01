<?php

declare(strict_types=1);

namespace OAuth\Repository;

use OAuth\Services\EncryptionService;

/**
 * Repository for managing OAuth access tokens in the database.
 * 
 * This class unifies the functionality from access_helps.php and access_helps_new.php
 * into a single, modern implementation using the `keys_new` table.
 * 
 * Features:
 * - Encrypted storage of usernames and tokens
 * - User ID caching for performance
 * - Clean API for CRUD operations
 */
final class TokenRepository
{
    private EncryptionService $encryption;
    
    /** @var array<string, int> Cache of username -> user_id mappings */
    private static array $userIdCache = [];

    public function __construct(EncryptionService $encryption)
    {
        $this->encryption = $encryption;
    }

    /**
     * Create instance from Settings singleton for backward compatibility.
     */
    public static function fromSettings(): self
    {
        return new self(EncryptionService::fromSettings());
    }

    /**
     * Store or update access tokens for a user.
     *
     * @param string $username The username
     * @param string $accessKey The OAuth access key
     * @param string $accessSecret The OAuth access secret
     * @return bool True on success
     */
    public function saveTokens(string $username, string $accessKey, string $accessSecret): bool
    {
        $username = trim($username);
        $encryptedUsername = $this->encryption->encrypt($username, 'decrypt');
        $encryptedKey = $this->encryption->encrypt($accessKey, 'decrypt');
        $encryptedSecret = $this->encryption->encrypt($accessSecret, 'decrypt');

        $userId = $this->getUserId($username);

        if ($userId !== null) {
            // Update existing user
            $query = <<<SQL
                UPDATE keys_new
                SET a_k = ?, a_s = ?, created_at = NOW()
                WHERE id = ?
            SQL;
            $this->executeQuery($query, [$encryptedKey, $encryptedSecret, $userId]);
        } else {
            // Insert new user
            $query = <<<SQL
                INSERT INTO keys_new (u_n, a_k, a_s)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    a_k = VALUES(a_k),
                    a_s = VALUES(a_s),
                    created_at = NOW()
            SQL;
            $this->executeQuery($query, [$encryptedUsername, $encryptedKey, $encryptedSecret]);
        }

        return true;
    }

    /**
     * Retrieve access tokens for a user.
     *
     * @param string $username The username
     * @return array{access_key: string, access_secret: string}|null The tokens or null if not found
     */
    public function getTokens(string $username): ?array
    {
        $username = trim($username);
        $userId = $this->getUserId($username);

        if ($userId === null) {
            return null;
        }

        $query = "SELECT a_k, a_s FROM keys_new WHERE id = ?";
        $result = $this->fetchQuery($query, [$userId]);

        if (empty($result)) {
            return null;
        }

        return [
            'access_key' => $this->encryption->decrypt($result[0]['a_k'], 'decrypt'),
            'access_secret' => $this->encryption->decrypt($result[0]['a_s'], 'decrypt'),
        ];
    }

    /**
     * Delete access tokens for a user.
     *
     * @param string $username The username
     * @return bool True if user existed and was deleted
     */
    public function deleteTokens(string $username): bool
    {
        $username = trim($username);
        $userId = $this->getUserId($username);

        if ($userId === null) {
            return false;
        }

        $query = "DELETE FROM keys_new WHERE id = ?";
        $this->executeQuery($query, [$userId]);

        // Clear from cache
        unset(self::$userIdCache[$username]);

        return true;
    }

    /**
     * Check if a user has stored tokens.
     *
     * @param string $username The username
     * @return bool True if tokens exist
     */
    public function hasTokens(string $username): bool
    {
        return $this->getUserId(trim($username)) !== null;
    }

    /**
     * Get the internal user ID for a username.
     * Results are cached for performance.
     *
     * @param string $username The username to look up
     * @return int|null The user ID or null if not found
     */
    public function getUserId(string $username): ?int
    {
        $username = trim($username);

        if (isset(self::$userIdCache[$username])) {
            return self::$userIdCache[$username];
        }

        $query = "SELECT id, u_n FROM keys_new";
        $results = $this->fetchQuery($query);

        if (empty($results)) {
            return null;
        }

        foreach ($results as $row) {
            $decryptedUsername = $this->encryption->decrypt($row['u_n'], 'decrypt');
            if ($decryptedUsername === $username) {
                $userId = (int) $row['id'];
                self::$userIdCache[$username] = $userId;
                return $userId;
            }
        }

        return null;
    }

    /**
     * Add a user to the users table (legacy support).
     *
     * @param string $username The username to add
     * @return bool True on success
     */
    public function addUser(string $username): bool
    {
        $query = <<<SQL
            INSERT INTO users (username) SELECT ?
            WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = ?)
        SQL;
        
        $this->executeQuery($query, [$username, $username]);
        return true;
    }

    /**
     * Clear the user ID cache (useful for testing).
     */
    public static function clearCache(): void
    {
        self::$userIdCache = [];
    }

    /**
     * Execute a database query (INSERT, UPDATE, DELETE).
     * 
     * @param string $query The SQL query
     * @param array<mixed> $params Query parameters
     * @return array<array<string, mixed>> Results for SELECT queries
     */
    private function executeQuery(string $query, array $params = []): array
    {
        return \OAuth\MdwikiSql\execute_queries($query, $params);
    }

    /**
     * Fetch results from a database query.
     * 
     * @param string $query The SQL query
     * @param array<mixed> $params Query parameters
     * @return array<array<string, mixed>> Query results
     */
    private function fetchQuery(string $query, array $params = []): array
    {
        return \OAuth\MdwikiSql\fetch_queries($query, $params);
    }
}
