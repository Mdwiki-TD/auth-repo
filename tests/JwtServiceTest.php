<?php

declare(strict_types=1);

namespace OAuth\Tests;

use PHPUnit\Framework\TestCase;
use OAuth\Services\JwtService;

/**
 * Tests for the JwtService class.
 */
class JwtServiceTest extends TestCase
{
    private JwtService $service;
    private string $testSecretKey;

    protected function setUp(): void
    {
        $this->testSecretKey = getenv('JWT_KEY') ?: 'test_secret_key_for_unit_tests';
        $this->service = new JwtService($this->testSecretKey, 'localhost');
    }

    public function testCreateTokenReturnsNonEmptyString(): void
    {
        $token = $this->service->createToken('test_user');
        
        $this->assertNotEmpty($token);
        $this->assertIsString($token);
    }

    public function testVerifyTokenReturnsUsernameForValidToken(): void
    {
        $username = 'test_user';
        $token = $this->service->createToken($username);
        
        [$verifiedUsername, $error] = $this->service->verifyToken($token);
        
        $this->assertEquals($username, $verifiedUsername);
        $this->assertEmpty($error);
    }

    public function testVerifyTokenReturnsErrorForEmptyToken(): void
    {
        [$username, $error] = $this->service->verifyToken('');
        
        $this->assertEmpty($username);
        $this->assertNotEmpty($error);
        $this->assertStringContainsString('required', strtolower($error));
    }

    public function testVerifyTokenReturnsErrorForInvalidToken(): void
    {
        [$username, $error] = $this->service->verifyToken('invalid_token_string');
        
        $this->assertEmpty($username);
        $this->assertNotEmpty($error);
    }

    public function testVerifyTokenReturnsErrorForTamperedToken(): void
    {
        $token = $this->service->createToken('test_user');
        $tamperedToken = substr($token, 0, -5) . 'XXXXX';
        
        [$username, $error] = $this->service->verifyToken($tamperedToken);
        
        $this->assertEmpty($username);
        $this->assertNotEmpty($error);
    }

    public function testJwtTokenStructure(): void
    {
        $token = $this->service->createToken('test_user');
        $parts = explode('.', $token);
        
        $this->assertCount(3, $parts, 'JWT should have 3 parts: header, payload, signature');
    }

    public function testDifferentUsernamesProduceDifferentTokens(): void
    {
        $token1 = $this->service->createToken('user1');
        $token2 = $this->service->createToken('user2');
        
        $this->assertNotEquals($token1, $token2);
    }

    public function testIsTokenValidReturnsTrueForValidToken(): void
    {
        $token = $this->service->createToken('test_user');
        
        $this->assertTrue($this->service->isTokenValid($token));
    }

    public function testIsTokenValidReturnsFalseForInvalidToken(): void
    {
        $this->assertFalse($this->service->isTokenValid('invalid'));
        $this->assertFalse($this->service->isTokenValid(''));
    }

    public function testMultipleRoundTrips(): void
    {
        $usernames = ['user1', 'user2', 'test_user', 'admin', 'user@example.com'];
        
        foreach ($usernames as $username) {
            $token = $this->service->createToken($username);
            [$verifiedUsername, $error] = $this->service->verifyToken($token);
            
            $this->assertEquals($username, $verifiedUsername, "Failed for: $username");
            $this->assertEmpty($error);
        }
    }

    public function testCreateTokenWithEmptySecretKeyReturnsEmpty(): void
    {
        $serviceWithNoKey = new JwtService('', 'localhost');
        $token = $serviceWithNoKey->createToken('test_user');
        
        $this->assertEmpty($token);
    }

    public function testVerifyTokenWithEmptySecretKeyReturnsError(): void
    {
        $serviceWithNoKey = new JwtService('', 'localhost');
        [$username, $error] = $serviceWithNoKey->verifyToken('any_token');
        
        $this->assertEmpty($username);
        $this->assertNotEmpty($error);
    }

    public function testFromSettingsFactory(): void
    {
        $service = JwtService::fromSettings();
        
        $username = 'factory_test_user';
        $token = $service->createToken($username);
        [$verifiedUsername, $error] = $service->verifyToken($token);
        
        $this->assertEquals($username, $verifiedUsername);
        $this->assertEmpty($error);
    }

    public function testTokenWithDifferentSecretKeysDoNotVerify(): void
    {
        $service1 = new JwtService('secret_key_1', 'localhost');
        $service2 = new JwtService('secret_key_2', 'localhost');
        
        $token = $service1->createToken('test_user');
        
        // Should not verify with different key
        [$username, $error] = $service2->verifyToken($token);
        $this->assertEmpty($username);
        $this->assertNotEmpty($error);
    }

    public function testCustomExpiryTime(): void
    {
        // Create service with 1 second expiry
        $shortLivedService = new JwtService($this->testSecretKey, 'localhost', 1);
        
        $token = $shortLivedService->createToken('test_user');
        
        // Token should be valid immediately
        $this->assertTrue($shortLivedService->isTokenValid($token));
        
        // Wait just over 1 second for the token to expire
        usleep(1100000); // 1.1 seconds
        
        [$username, $error] = $shortLivedService->verifyToken($token);
        $this->assertEmpty($username);
        $this->assertStringContainsString('expired', strtolower($error));
    }
}
