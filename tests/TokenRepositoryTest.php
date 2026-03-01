<?php

declare(strict_types=1);

namespace OAuth\Tests;

use PHPUnit\Framework\TestCase;
use OAuth\Repository\TokenRepository;
use OAuth\Services\EncryptionService;
use Defuse\Crypto\Key;

/**
 * Tests for the TokenRepository class.
 * 
 * Note: These tests require a database connection. They will be skipped
 * if the database is not available.
 */
class TokenRepositoryTest extends TestCase
{
    private TokenRepository $repository;
    private EncryptionService $encryption;
    private string $testUsername;
    private static ?bool $dbAvailable = null;

    protected function setUp(): void
    {
        $cookieKey = Key::loadFromAsciiSafeString(getenv('COOKIE_KEY'));
        $decryptKey = Key::loadFromAsciiSafeString(getenv('DECRYPT_KEY'));
        
        $this->encryption = new EncryptionService($cookieKey, $decryptKey);
        $this->repository = new TokenRepository($this->encryption);
        $this->testUsername = 'test_user_' . time() . '_' . mt_rand(1000, 9999);

        // Check database availability once
        if (self::$dbAvailable === null) {
            self::$dbAvailable = $this->checkDatabaseConnection();
        }

        if (!self::$dbAvailable) {
            $this->markTestSkipped('Database not available');
        }
    }

    protected function tearDown(): void
    {
        if (self::$dbAvailable) {
            try {
                $this->repository->deleteTokens($this->testUsername);
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }
        TokenRepository::clearCache();
    }

    private function checkDatabaseConnection(): bool
    {
        try {
            $this->repository->getUserId('non_existent_user_check_' . time());
            return true;
        } catch (\PDOException $e) {
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function testGetUserIdReturnsNullForNonExistentUser(): void
    {
        $userId = $this->repository->getUserId('definitely_non_existent_' . time());
        $this->assertNull($userId);
    }

    public function testHasTokensReturnsFalseForNonExistentUser(): void
    {
        $hasTokens = $this->repository->hasTokens('definitely_non_existent_' . time());
        $this->assertFalse($hasTokens);
    }

    public function testGetTokensReturnsNullForNonExistentUser(): void
    {
        $tokens = $this->repository->getTokens('definitely_non_existent_' . time());
        $this->assertNull($tokens);
    }

    public function testDeleteTokensReturnsFalseForNonExistentUser(): void
    {
        $result = $this->repository->deleteTokens('definitely_non_existent_' . time());
        $this->assertFalse($result);
    }

    public function testSaveAndRetrieveTokens(): void
    {
        $accessKey = 'test_access_key_' . time();
        $accessSecret = 'test_access_secret_' . time();

        // Save tokens
        $result = $this->repository->saveTokens($this->testUsername, $accessKey, $accessSecret);
        $this->assertTrue($result);

        // Verify user exists
        $this->assertTrue($this->repository->hasTokens($this->testUsername));

        // Retrieve tokens
        $tokens = $this->repository->getTokens($this->testUsername);
        $this->assertNotNull($tokens);
        $this->assertIsArray($tokens);
        $this->assertArrayHasKey('access_key', $tokens);
        $this->assertArrayHasKey('access_secret', $tokens);
        $this->assertEquals($accessKey, $tokens['access_key']);
        $this->assertEquals($accessSecret, $tokens['access_secret']);
    }

    public function testSaveTokensUpdatesExistingUser(): void
    {
        $initialKey = 'initial_key_' . time();
        $initialSecret = 'initial_secret_' . time();
        $updatedKey = 'updated_key_' . time();
        $updatedSecret = 'updated_secret_' . time();

        // Save initial tokens
        $this->repository->saveTokens($this->testUsername, $initialKey, $initialSecret);

        // Get user ID before update
        $userIdBefore = $this->repository->getUserId($this->testUsername);

        // Clear cache to force fresh lookup
        TokenRepository::clearCache();

        // Update tokens
        $this->repository->saveTokens($this->testUsername, $updatedKey, $updatedSecret);

        // Get user ID after update
        $userIdAfter = $this->repository->getUserId($this->testUsername);

        // Should be same user
        $this->assertEquals($userIdBefore, $userIdAfter);

        // Should have updated tokens
        $tokens = $this->repository->getTokens($this->testUsername);
        $this->assertEquals($updatedKey, $tokens['access_key']);
        $this->assertEquals($updatedSecret, $tokens['access_secret']);
    }

    public function testDeleteTokensRemovesUser(): void
    {
        // Create user
        $this->repository->saveTokens($this->testUsername, 'key', 'secret');
        $this->assertTrue($this->repository->hasTokens($this->testUsername));

        // Delete
        $result = $this->repository->deleteTokens($this->testUsername);
        $this->assertTrue($result);

        // Clear cache
        TokenRepository::clearCache();

        // Verify deleted
        $this->assertFalse($this->repository->hasTokens($this->testUsername));
        $this->assertNull($this->repository->getTokens($this->testUsername));
    }

    public function testUserIdCaching(): void
    {
        // Create user
        $this->repository->saveTokens($this->testUsername, 'key', 'secret');

        // First lookup
        $userId1 = $this->repository->getUserId($this->testUsername);

        // Second lookup (should use cache)
        $userId2 = $this->repository->getUserId($this->testUsername);

        $this->assertEquals($userId1, $userId2);
    }

    public function testClearCacheWorks(): void
    {
        // Create user and cache the ID
        $this->repository->saveTokens($this->testUsername, 'key', 'secret');
        $this->repository->getUserId($this->testUsername);

        // Clear cache
        TokenRepository::clearCache();

        // Should still work (fetches from DB)
        $userId = $this->repository->getUserId($this->testUsername);
        $this->assertNotNull($userId);
    }

    public function testUsernameTrimmingOnSave(): void
    {
        $untrimmedUsername = '  ' . $this->testUsername . '  ';
        
        // Save with spaces
        $this->repository->saveTokens($untrimmedUsername, 'key', 'secret');
        
        // Should find with trimmed username
        $this->assertTrue($this->repository->hasTokens($this->testUsername));
    }

    public function testUsernameTrimmingOnGet(): void
    {
        // Save with trimmed username
        $this->repository->saveTokens($this->testUsername, 'key', 'secret');
        
        TokenRepository::clearCache();
        
        // Get with spaces
        $tokens = $this->repository->getTokens('  ' . $this->testUsername . '  ');
        $this->assertNotNull($tokens);
    }

    public function testFromSettingsFactory(): void
    {
        $repository = TokenRepository::fromSettings();
        $this->assertInstanceOf(TokenRepository::class, $repository);
    }

    public function testFullWorkflow(): void
    {
        $accessKey = 'workflow_key_' . time();
        $accessSecret = 'workflow_secret_' . time();

        // Step 1: User doesn't exist
        $this->assertFalse($this->repository->hasTokens($this->testUsername));

        // Step 2: Create user
        $this->repository->saveTokens($this->testUsername, $accessKey, $accessSecret);

        // Step 3: User exists
        $this->assertTrue($this->repository->hasTokens($this->testUsername));

        // Step 4: Get user ID
        $userId = $this->repository->getUserId($this->testUsername);
        $this->assertNotNull($userId);
        $this->assertIsInt($userId);

        // Step 5: Get tokens
        $tokens = $this->repository->getTokens($this->testUsername);
        $this->assertEquals($accessKey, $tokens['access_key']);
        $this->assertEquals($accessSecret, $tokens['access_secret']);

        // Step 6: Delete
        $this->repository->deleteTokens($this->testUsername);
        TokenRepository::clearCache();

        // Step 7: User no longer exists
        $this->assertFalse($this->repository->hasTokens($this->testUsername));
    }
}
