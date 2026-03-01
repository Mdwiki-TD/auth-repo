<?php

declare(strict_types=1);

namespace App\Security;

use App\Config;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

/**
 * Create and verify HS256 JSON Web Tokens.
 */
final class JwtService
{
    private readonly Config $config;

    public function __construct(?Config $config = null)
    {
        $this->config = $config ?? Config::getInstance();
    }

    /**
     * Create a signed JWT for the given username.
     *
     * Returns an empty string if encoding fails.
     */
    public function create(string $username): string
    {
        $payload = [
            'iss'      => $this->config->domain,
            'iat'      => time(),
            'exp'      => time() + 3600,
            'username' => $username,
        ];

        try {
            return JWT::encode($payload, $this->config->jwtKey, 'HS256');
        } catch (\Exception $e) {
            error_log('Failed to create JWT token: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Verify a JWT and extract the username.
     *
     * @return array{0: string, 1: string} [username, error]
     */
    public function verify(string $token): array
    {
        if ($token === '' || $this->config->jwtKey === '') {
            error_log('Token and JWT key are required');
            return ['', 'Token and JWT key are required'];
        }

        try {
            $result = JWT::decode($token, new Key($this->config->jwtKey, 'HS256'));
            return [$result->username, ''];
        } catch (ExpiredException) {
            error_log('JWT token has expired');
            return ['', 'JWT token has expired'];
        } catch (SignatureInvalidException) {
            error_log('JWT token signature is invalid');
            return ['', 'JWT token signature is invalid'];
        } catch (\Exception $e) {
            error_log('Failed to verify JWT token: ' . $e->getMessage());
            return ['', 'Failed to verify JWT token: ' . $e->getMessage()];
        }
    }
}
