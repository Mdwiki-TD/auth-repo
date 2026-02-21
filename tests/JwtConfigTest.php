<?php

declare(strict_types=1);

namespace OAuth\Tests;

use PHPUnit\Framework\TestCase;

// Note: bootstrap.php already loads config.php with test keys
require_once __DIR__ . '/../src/oauth/jwt_config.php';

use function OAuth\JWT\create_jwt;
use function OAuth\JWT\verify_jwt;

/**
 * Tests for the jwt_config.php JWT functions
 * 
 * These tests cover:
 * - JWT token creation
 * - JWT token verification
 * - Token expiration handling
 * - Invalid token handling
 */
class JwtConfigTest extends TestCase
{
    private string $testUsername = 'test_user';

    /**
     * Test that create_jwt returns a non-empty string
     */
    public function testCreateJwtReturnsNonEmptyString(): void
    {
        $token = create_jwt($this->testUsername);
        
        $this->assertNotEmpty($token);
        $this->assertIsString($token);
    }

    /**
     * Test that verify_jwt returns username for valid token
     */
    public function testVerifyJwtReturnsUsernameForValidToken(): void
    {
        $token = create_jwt($this->testUsername);
        list($username, $error) = verify_jwt($token);
        
        $this->assertEquals($this->testUsername, $username);
        $this->assertEmpty($error);
    }

    /**
     * Test that verify_jwt returns error for empty token
     */
    public function testVerifyJwtReturnsErrorForEmptyToken(): void
    {
        list($username, $error) = verify_jwt('');
        
        $this->assertEmpty($username);
        $this->assertNotEmpty($error);
        $this->assertStringContainsString('required', strtolower($error));
    }

    /**
     * Test that verify_jwt returns error for invalid token
     */
    public function testVerifyJwtReturnsErrorForInvalidToken(): void
    {
        list($username, $error) = verify_jwt('invalid_token_string');
        
        $this->assertEmpty($username);
        $this->assertNotEmpty($error);
    }

    /**
     * Test that verify_jwt returns error for tampered token
     */
    public function testVerifyJwtReturnsErrorForTamperedToken(): void
    {
        $token = create_jwt($this->testUsername);
        
        // Tamper with the token by changing a character
        $tamperedToken = substr($token, 0, -5) . 'XXXXX';
        
        list($username, $error) = verify_jwt($tamperedToken);
        
        $this->assertEmpty($username);
        $this->assertNotEmpty($error);
    }

    /**
     * Test JWT token structure (should have 3 parts separated by dots)
     */
    public function testJwtTokenStructure(): void
    {
        $token = create_jwt($this->testUsername);
        $parts = explode('.', $token);
        
        $this->assertCount(3, $parts, 'JWT should have 3 parts: header, payload, signature');
    }

    /**
     * Test that different usernames produce different tokens
     */
    public function testDifferentUsernamesProduceDifferentTokens(): void
    {
        $token1 = create_jwt('user1');
        $token2 = create_jwt('user2');
        
        $this->assertNotEquals($token1, $token2);
    }

    /**
     * Test that same username can produce different tokens (due to timestamps)
     */
    public function testSameUsernameCanProduceDifferentTokens(): void
    {
        $token1 = create_jwt($this->testUsername);
        sleep(1); // Wait 1 second to ensure different timestamp
        $token2 = create_jwt($this->testUsername);
        
        $this->assertNotEquals($token1, $token2);
    }

    /**
     * Test verify_jwt with malformed token parts
     */
    public function testVerifyJwtWithMalformedToken(): void
    {
        $malformedTokens = [
            'only_one_part',
            'two.parts.only',
            'too.many.parts.here.extra',
            '',
            '...',
        ];

        foreach ($malformedTokens as $token) {
            list($username, $error) = verify_jwt($token);
            $this->assertEmpty($username, "Username should be empty for token: $token");
            $this->assertNotEmpty($error, "Error should not be empty for token: $token");
        }
    }

    /**
     * Test round-trip: create and verify multiple times
     */
    public function testMultipleRoundTrips(): void
    {
        $usernames = ['user1', 'user2', 'test_user', 'admin', 'user@example.com'];
        
        foreach ($usernames as $username) {
            $token = create_jwt($username);
            list($verifiedUsername, $error) = verify_jwt($token);
            
            $this->assertEquals($username, $verifiedUsername, "Failed for username: $username");
            $this->assertEmpty($error, "Error should be empty for username: $username");
        }
    }
}
