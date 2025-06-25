<?php

namespace OAuth\JWT;
/*
use function OAuth\JWT\create_jwt;
use function OAuth\JWT\verify_jwt;
*/

include_once __DIR__ . '/../vendor_load.php';
include_once __DIR__ . '/config.php';

use Firebase\JWT\JWT;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\Key;

function create_jwt(string $username): string
{
    global $jwt_key, $domain;

    $payload = [
        'iss' => $domain,     // المصدر
        'iat' => time(),               // وقت الإنشاء
        'exp' => time() + 3600,        // وقت الانتهاء (ساعة واحدة مثلاً)
        'username' => $username        // بيانات إضافية (محتوى التوكن)
    ];

    try {
        return JWT::encode($payload, $jwt_key, 'HS256');
    } catch (\Exception $e) {
        error_log('Failed to create JWT token: ' . $e->getMessage());
        return '';
    }
}

function verify_jwt(string $token)
{
    global $jwt_key;
    // [$verified, $error] = verify_jwt($text2);

    // Input validation
    if (empty($token) || empty($jwt_key)) {
        error_log('Token and JWT key are required');
        return ["", 'Token and JWT key are required'];
    }

    try {
        $result = JWT::decode($token, new Key($jwt_key, 'HS256'));
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
