<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Config;
use App\Security\JwtService;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the JwtService.
 */
class JwtServiceTest extends TestCase
{
    private JwtService $jwt;
    private string $testUsername = 'test_user';

    protected function setUp(): void
    {
        Config::resetInstance();
        $this->jwt = new JwtService(Config::getInstance());
    }

    protected function tearDown(): void
    {
        Config::resetInstance();
    }

    public function testCreateReturnsNonEmptyString(): void
    {
        $token = $this->jwt->create($this->testUsername);
        $this->assertNotEmpty($token);
        $this->assertIsString($token);
    }

    public function testTokenHasThreeParts(): void
    {
        $token = $this->jwt->create($this->testUsername);
        $parts = explode('.', $token);
        $this->assertCount(3, $parts);
    }

    public function testVerifyReturnsUsernameForValidToken(): void
    {
        $token = $this->jwt->create($this->testUsername);
        [$username, $error] = $this->jwt->verify($token);

        $this->assertEquals($this->testUsername, $username);
        $this->assertEmpty($error);
    }

    public function testVerifyReturnsErrorForEmptyToken(): void
    {
        [$username, $error] = $this->jwt->verify('');

        $this->assertEmpty($username);
        $this->assertNotEmpty($error);
        $this->assertStringContainsString('required', strtolower($error));
    }

    public function testVerifyReturnsErrorForInvalidToken(): void
    {
        [$username, $error] = $this->jwt->verify('invalid_token');

        $this->assertEmpty($username);
        $this->assertNotEmpty($error);
    }

    public function testVerifyReturnsErrorForTamperedToken(): void
    {
        $token = $this->jwt->create($this->testUsername);
        $tampered = substr($token, 0, -5) . 'XXXXX';

        [$username, $error] = $this->jwt->verify($tampered);

        $this->assertEmpty($username);
        $this->assertNotEmpty($error);
    }

    public function testDifferentUsernamesProduceDifferentTokens(): void
    {
        $token1 = $this->jwt->create('user1');
        $token2 = $this->jwt->create('user2');

        $this->assertNotEquals($token1, $token2);
    }

    public function testMultipleUsernamesRoundTrip(): void
    {
        $usernames = ['user1', 'admin', 'test@example.com', 'مستخدم'];

        foreach ($usernames as $username) {
            $token = $this->jwt->create($username);
            [$verified, $error] = $this->jwt->verify($token);

            $this->assertEquals($username, $verified, "Failed for: {$username}");
            $this->assertEmpty($error, "Unexpected error for: {$username}");
        }
    }

    public function testVerifyMalformedTokens(): void
    {
        $tokens = ['only_one_part', 'two.parts', '...', ''];

        foreach ($tokens as $token) {
            [$username, $error] = $this->jwt->verify($token);
            $this->assertEmpty($username, "Username should be empty for: {$token}");
            $this->assertNotEmpty($error, "Error should not be empty for: {$token}");
        }
    }
}
