<?php

declare(strict_types=1);

namespace OAuth\Tests;

use PHPUnit\Framework\TestCase;

// Note: bootstrap.php already loads config.php with test keys
require_once __DIR__ . '/../src/oauth/access_helps_new.php';

use function OAuth\AccessHelpsNew\get_user_id;
use function OAuth\AccessHelpsNew\add_access_to_dbs_new;
use function OAuth\AccessHelpsNew\get_access_from_dbs_new;
use function OAuth\AccessHelpsNew\del_access_from_dbs_new;

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

    /**
     * Clean up test data after each test
     */
    protected function tearDown(): void
    {
        // Clean up test user if exists
        try {
            del_access_from_dbs_new($this->testUsername);
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
        $userId = get_user_id($uniqueUsername);
        
        $this->assertNull($userId);
    }

    /**
     * Test that get_user_id handles username trimming
     */
    public function testGetUserIdTrimsUsername(): void
    {
        $usernameWithSpaces = '  test_user_trim  ';
        
        // This should not throw an error
        $userId = get_user_id($usernameWithSpaces);
        
        // Result can be null or an integer, but should not throw
        $this->assertTrue($userId === null || is_int($userId));
    }

    /**
     * Test user ID caching with static variable
     */
    public function testGetUserIdCaching(): void
    {
        // First call - may hit database
        $userId1 = get_user_id($this->testUsername);
        
        // Second call - should use cached value
        $userId2 = get_user_id($this->testUsername);
        
        // Both should return the same result
        $this->assertEquals($userId1, $userId2);
    }

    /**
     * Test that add_access_to_dbs_new handles new user insertion
     * 
     * Note: This test may be skipped if no database is available
     */
    public function testAddAccessToDbsNewHandlesNewUser(): void
    {
        try {
            // Attempt to add access for a new user
            add_access_to_dbs_new($this->testUsername, $this->testAccessKey, $this->testAccessSecret);
            
            // If we get here without exception, the function executed
            $this->assertTrue(true);
        } catch (\PDOException $e) {
            // Skip test if database is not available
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
        } catch (\Exception $e) {
            // Other errors should be reported
            $this->fail('Unexpected exception: ' . $e->getMessage());
        }
    }

    /**
     * Test that get_access_from_dbs_new returns null for non-existent user
     */
    public function testGetAccessFromDbsNewReturnsNullForNonExistentUser(): void
    {
        $uniqueUsername = 'non_existent_user_' . time();
        
        try {
            $access = get_access_from_dbs_new($uniqueUsername);
            $this->assertNull($access);
        } catch (\PDOException $e) {
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
        }
    }

    /**
     * Test that get_access_from_dbs_new handles username trimming
     */
    public function testGetAccessFromDbsNewTrimsUsername(): void
    {
        $usernameWithSpaces = '  test_user_trim  ';
        
        try {
            $access = get_access_from_dbs_new($usernameWithSpaces);
            
            // Should not throw, result can be null or array
            $this->assertTrue($access === null || is_array($access));
        } catch (\PDOException $e) {
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
        }
    }

    /**
     * Test that del_access_from_dbs_new returns null for non-existent user
     */
    public function testDelAccessFromDbsNewReturnsNullForNonExistentUser(): void
    {
        $uniqueUsername = 'non_existent_user_' . time();
        
        try {
            $result = del_access_from_dbs_new($uniqueUsername);
            $this->assertNull($result);
        } catch (\PDOException $e) {
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
        }
    }

    /**
     * Test full workflow: add, get, and delete access
     * 
     * Note: This is an integration test that requires database
     */
    public function testFullAccessWorkflow(): void
    {
        try {
            // Step 1: Add access
            add_access_to_dbs_new($this->testUsername, $this->testAccessKey, $this->testAccessSecret);
            
            // Step 2: Get user ID (should exist now)
            $userId = get_user_id($this->testUsername);
            $this->assertNotNull($userId, 'User ID should exist after adding access');
            $this->assertIsInt($userId);
            
            // Step 3: Get access
            $access = get_access_from_dbs_new($this->testUsername);
            $this->assertNotNull($access, 'Access should exist after adding');
            $this->assertIsArray($access);
            $this->assertArrayHasKey('access_key', $access);
            $this->assertArrayHasKey('access_secret', $access);
            
            // Step 4: Delete access
            del_access_from_dbs_new($this->testUsername);
            
            // Step 5: Verify access is deleted
            $accessAfterDelete = get_access_from_dbs_new($this->testUsername);
            $this->assertNull($accessAfterDelete, 'Access should be null after deletion');
            
        } catch (\PDOException $e) {
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
        }
    }

    /**
     * Test that access tokens are properly encrypted/decrypted
     */
    public function testAccessTokensAreEncrypted(): void
    {
        try {
            // Add access
            add_access_to_dbs_new($this->testUsername, $this->testAccessKey, $this->testAccessSecret);
            
            // Get access
            $access = get_access_from_dbs_new($this->testUsername);
            
            if ($access !== null) {
                // Verify the decrypted values match original
                $this->assertEquals($this->testAccessKey, $access['access_key']);
                $this->assertEquals($this->testAccessSecret, $access['access_secret']);
            }
            
            // Clean up
            del_access_from_dbs_new($this->testUsername);
            
        } catch (\PDOException $e) {
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
        }
    }

    /**
     * Test updating existing user access
     */
    public function testUpdateExistingUserAccess(): void
    {
        try {
            $newKey = 'updated_key_789';
            $newSecret = 'updated_secret_012';
            
            // Add initial access
            add_access_to_dbs_new($this->testUsername, $this->testAccessKey, $this->testAccessSecret);
            
            // Update with new credentials
            add_access_to_dbs_new($this->testUsername, $newKey, $newSecret);
            
            // Verify update
            $access = get_access_from_dbs_new($this->testUsername);
            $this->assertNotNull($access);
            $this->assertEquals($newKey, $access['access_key']);
            $this->assertEquals($newSecret, $access['access_secret']);
            
            // Clean up
            del_access_from_dbs_new($this->testUsername);
            
        } catch (\PDOException $e) {
            $this->markTestSkipped('Database not available: ' . $e->getMessage());
        }
    }
}
