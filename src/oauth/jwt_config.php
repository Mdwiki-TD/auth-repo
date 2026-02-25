<?php

namespace OAuth\JWT;
/*
use function OAuth\JWT\create_jwt;
use function OAuth\JWT\verify_jwt;
*/

use Firebase\JWT\JWT;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\Key;

function create_jwt(string $username): string
{
    $settings = \Settings::getInstance();

    $payload = [
        'iss' => $settings->domain,    // source
        'iat' => time(),               // creation time
        'exp' => time() + 3600,        // expiration time (e.g., one hour)
        'username' => $username        // additional data (token content)
    ];

    try {
        return JWT::encode($payload, $settings->jwtKey, 'HS256');
    } catch (\Exception $e) {
        error_log('Failed to create JWT token: ' . $e->getMessage());
        return '';
    }
}

function verify_jwt(string $token)
{
    $settings = \Settings::getInstance();
    $jwtKey = $settings->jwtKey;
    // [$verified, $error] = verify_jwt($text2);

    // Input validation
    if (empty($token) || empty($jwtKey)) {
        error_log('Token and JWT key are required');
        return ["", 'Token and JWT key are required'];
    }

    try {
        $result = JWT::decode($token, new Key($jwtKey, 'HS256'));
        return [$result->username, ''];
    } catch (ExpiredException $e) {
        error_log('JWT token has expired');
        return ["", 'JWT token has expired'];
    } catch (SignatureInvalidException $e) {
        error_log('JWT token signature is invalid');
        return ["", 'JWT token signature is invalid'];
    } catch (\Exception $e) {
        error_log('Failed to verify JWT token: ' . $e->getMessage());
        return ["", 'Failed to verify JWT token: ' . $e->getMessage()];
    }
}
