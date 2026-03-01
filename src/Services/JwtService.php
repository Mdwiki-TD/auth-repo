<?php

declare(strict_types=1);

namespace OAuth\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

/**
 * Service for creating and verifying JWT tokens.
 * 
 * Consolidates functionality from jwt_config.php into a modern,
 * testable class with proper dependency injection.
 */
final class JwtService
{
    private const ALGORITHM = 'HS256';
    private const DEFAULT_EXPIRY_SECONDS = 3600; // 1 hour

    private string $secretKey;
    private string $issuer;
    private int $expirySeconds;

    public function __construct(
        string $secretKey,
        string $issuer = 'localhost',
        int $expirySeconds = self::DEFAULT_EXPIRY_SECONDS
    ) {
        $this->secretKey = $secretKey;
        $this->issuer = $issuer;
        $this->expirySeconds = $expirySeconds;
    }

    /**
     * Create instance from Settings singleton for backward compatibility.
     */
    public static function fromSettings(): self
    {
        $settings = \Settings::getInstance();
        return new self(
            $settings->jwtKey,
            $settings->domain,
            self::DEFAULT_EXPIRY_SECONDS
        );
    }

    /**
     * Create a JWT token for the given username.
     *
     * @param string $username The username to encode in the token
     * @return string The JWT token, or empty string on failure
     */
    public function createToken(string $username): string
    {
        if (empty($this->secretKey)) {
            error_log('JWT secret key is not configured');
            return '';
        }

        $payload = [
            'iss' => $this->issuer,
            'iat' => time(),
            'exp' => time() + $this->expirySeconds,
            'username' => $username,
        ];

        try {
            return JWT::encode($payload, $this->secretKey, self::ALGORITHM);
        } catch (\Exception $e) {
            error_log('Failed to create JWT token: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Verify a JWT token and extract the username.
     *
     * @param string $token The JWT token to verify
     * @return array{0: string, 1: string} [username, error] - username on success, error message on failure
     */
    public function verifyToken(string $token): array
    {
        if (empty($token) || empty($this->secretKey)) {
            return ['', 'Token and JWT key are required'];
        }

        try {
            $decoded = JWT::decode($token, new Key($this->secretKey, self::ALGORITHM));
            return [$decoded->username ?? '', ''];
        } catch (ExpiredException $e) {
            return ['', 'JWT token has expired'];
        } catch (SignatureInvalidException $e) {
            return ['', 'JWT token signature is invalid'];
        } catch (\Exception $e) {
            error_log('Failed to verify JWT token: ' . $e->getMessage());
            return ['', 'Failed to verify JWT token: ' . $e->getMessage()];
        }
    }

    /**
     * Check if a token is valid without throwing exceptions.
     *
     * @param string $token The JWT token to check
     * @return bool True if token is valid
     */
    public function isTokenValid(string $token): bool
    {
        [$username, $error] = $this->verifyToken($token);
        return !empty($username) && empty($error);
    }
}
