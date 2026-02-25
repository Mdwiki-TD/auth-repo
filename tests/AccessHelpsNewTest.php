<?php

declare(strict_types=1);

namespace OAuth\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Tests for the access_helps_new.php database access functions
 *
 * These tests cover:
 * - User ID retrieval and caching
 * - Access token storage
 * - Access token retrieval
 * - Access token deletion
 *
 * Note: These tests require a database connection. In a CI environment,
 * you may want to use a test database or mock the database functions.
 */
class AccessHelpsNewTest extends TestCase
{
    private string $testUsername = 'test_user_oauth';
    private string $testAccessKey = 'test_access_key_123';
    private string $testAccessSecret = 'test_access_secret_456';
    private static $dbAvailable = null;

    protected function setUp(): void
    {
        // Load the source file after bootstrap has set up the environment
        require_once __DIR__ . '/../src/oauth/access_helps_new.php';

        // Check database availability once
        if (self::$dbAvailable === null) {
            self::$dbAvailable = $this->checkDatabaseConnection();
        }

        if (!self::$dbAvailable) {
            $this->markTestSkipped('Database not available');
        }
    }

    /**
     * Check if database connection is available
     */
    private function checkDatabaseConnection(): bool
    {
        try {
            // Try a simple query to check if database is accessible
            $result = \OAuth\AccessHelpsNew\get_user_id('non_existent_test_user_' . time());
            return true; // If we get here without exception, DB is available
        } catch (\PDOException $e) {
            return false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Clean up test data after each test
     */
    protected function tearDown(): void
    {
        if (!self::$dbAvailable) {
            return;
        }

        // Clean up test user if exists
        try {
            \OAuth\AccessHelpsNew\del_access_from_dbs_new($this->testUsername);
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }
    }

    /**
     * Test that get_user_id returns null for non-existent user
     */
    public function testGetUserIdReturnsNullForNonExistentUser(): void
    {
        $uniqueUsername = 'non_existent_user_' . time();
        $userId = \OAuth\AccessHelpsNew\get_user_id($uniqueUsername);

        $this->assertNull($userId);
    }

    /**
     * Test that get_user_id handles username trimming
     */
    public function testGetUserIdTrimsUsername(): void
    {
        $uniqueUsername = 'trim_test_user_' . time();

        // First add the user with the trimmed username
        \OAuth\AccessHelpsNew\add_access_to_dbs_new($uniqueUsername, 'key', 'secret');

        // Now query with spaces - should find the user due to trimming
        $userId = \OAuth\AccessHelpsNew\get_user_id('  ' . $uniqueUsername . '  ');

        // Clean up
        \OAuth\AccessHelpsNew\del_access_from_dbs_new($uniqueUsername);

        $this->assertNotNull($userId);
    }

    /**
     * Test that get_user_id caches results
     */
    public function testGetUserIdCaching(): void
    {
        $uniqueUsername = 'cache_test_user_' . time();

        // Add user
        \OAuth\AccessHelpsNew\add_access_to_dbs_new($uniqueUsername, 'key', 'secret');

        // First call
        $userId1 = \OAuth\AccessHelpsNew\get_user_id($uniqueUsername);

        // Second call should use cache
        $userId2 = \OAuth\AccessHelpsNew\get_user_id($uniqueUsername);

        // Clean up
        \OAuth\AccessHelpsNew\del_access_from_dbs_new($uniqueUsername);

        $this->assertEquals($userId1, $userId2);
    }

    /**
     * Test that add_access_to_dbs_new handles new user insertion
     */
    public function testAddAccessToDbsNewHandlesNewUser(): void
    {
        $uniqueUsername = 'new_user_' . time();

        // Should not throw exception
        \OAuth\AccessHelpsNew\add_access_to_dbs_new($uniqueUsername, 'key', 'secret');

        // Verify user was created
        $userId = \OAuth\AccessHelpsNew\get_user_id($uniqueUsername);

        // Clean up
        \OAuth\AccessHelpsNew\del_access_from_dbs_new($uniqueUsername);

        $this->assertNotNull($userId);
    }

    /**
     * Test that get_access_from_dbs_new returns null for non-existent user
     */
    public function testGetAccessFromDbsNewReturnsNullForNonExistentUser(): void
    {
        $uniqueUsername = 'non_existent_access_user_' . time();
        $access = \OAuth\AccessHelpsNew\get_access_from_dbs_new($uniqueUsername);

        $this->assertNull($access);
    }

    /**
     * Test that get_access_from_dbs_new handles username trimming
     */
    public function testGetAccessFromDbsNewTrimsUsername(): void
    {
        $uniqueUsername = 'access_trim_test_' . time();

        // Add user
        \OAuth\AccessHelpsNew\add_access_to_dbs_new($uniqueUsername, 'key', 'secret');

        // Query with spaces
        $access = \OAuth\AccessHelpsNew\get_access_from_dbs_new('  ' . $uniqueUsername . '  ');

        // Clean up
        \OAuth\AccessHelpsNew\del_access_from_dbs_new($uniqueUsername);

        $this->assertNotNull($access);
        $this->assertIsArray($access);
    }

    /**
     * Test that del_access_from_dbs_new returns null for non-existent user
     */
    public function testDelAccessFromDbsNewReturnsNullForNonExistentUser(): void
    {
        $uniqueUsername = 'non_existent_delete_user_' . time();

        // Should not throw exception
        $result = \OAuth\AccessHelpsNew\del_access_from_dbs_new($uniqueUsername);

        $this->assertNull($result);
    }

    /**
     * Test full workflow: add, get, delete
     */
    public function testFullAccessWorkflow(): void
    {
        $uniqueUsername = 'workflow_test_' . time();

        // Step 1: Add access
        \OAuth\AccessHelpsNew\add_access_to_dbs_new($uniqueUsername, $this->testAccessKey, $this->testAccessSecret);

        // Step 2: Get user ID (should exist now)
        $userId = \OAuth\AccessHelpsNew\get_user_id($uniqueUsername);
        $this->assertNotNull($userId, 'User ID should exist after adding access');
        $this->assertIsInt($userId);

        // Step 3: Get access
        $access = \OAuth\AccessHelpsNew\get_access_from_dbs_new($uniqueUsername);
        $this->assertNotNull($access, 'Access should exist after adding');
        $this->assertIsArray($access);
        $this->assertArrayHasKey('access_key', $access);
        $this->assertArrayHasKey('access_secret', $access);

        // Step 4: Delete access
        \OAuth\AccessHelpsNew\del_access_from_dbs_new($uniqueUsername);

        // Step 5: Verify access is deleted
        $accessAfterDelete = \OAuth\AccessHelpsNew\get_access_from_dbs_new($uniqueUsername);
        $this->assertNull($accessAfterDelete, 'Access should be null after deletion');
    }

    /**
     * Test that access tokens are properly encrypted/decrypted
     */
    public function testAccessTokensAreEncrypted(): void
    {
        $uniqueUsername = 'encryption_test_' . time();

        // Add access
        \OAuth\AccessHelpsNew\add_access_to_dbs_new($uniqueUsername, $this->testAccessKey, $this->testAccessSecret);

        // Get access
        $access = \OAuth\AccessHelpsNew\get_access_from_dbs_new($uniqueUsername);

        $this->assertNotNull($access, 'Access should exist');

        // Verify the decrypted values match original
        $this->assertEquals($this->testAccessKey, $access['access_key']);
        $this->assertEquals($this->testAccessSecret, $access['access_secret']);

        // Clean up
        \OAuth\AccessHelpsNew\del_access_from_dbs_new($uniqueUsername);
    }

    /**
     * Test updating existing user access
     */
    public function testUpdateExistingUserAccess(): void
    {
        $uniqueUsername = 'update_test_' . time();
        $newKey = 'updated_key_789';
        $newSecret = 'updated_secret_012';

        // Add initial access
        \OAuth\AccessHelpsNew\add_access_to_dbs_new($uniqueUsername, $this->testAccessKey, $this->testAccessSecret);

        // Get initial access to verify it was created
        $initialAccess = \OAuth\AccessHelpsNew\get_access_from_dbs_new($uniqueUsername);
        $this->assertNotNull($initialAccess, 'Initial access should exist');

        // Update with new credentials
        \OAuth\AccessHelpsNew\add_access_to_dbs_new($uniqueUsername, $newKey, $newSecret);

        // Verify update
        $access = \OAuth\AccessHelpsNew\get_access_from_dbs_new($uniqueUsername);
        $this->assertNotNull($access, 'Updated access should exist');
        $this->assertEquals($newKey, $access['access_key']);
        $this->assertEquals($newSecret, $access['access_secret']);

        // Clean up
        \OAuth\AccessHelpsNew\del_access_from_dbs_new($uniqueUsername);
    }
}
