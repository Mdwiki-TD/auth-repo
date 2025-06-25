<?php

namespace OAuth\JWT;
/*
use function OAuth\JWT\create_jwt;
use function OAuth\JWT\verify_jwt;
*/

include_once __DIR__ . '/../vendor_load.php';

use Firebase\JWT\JWT;
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

    return JWT::encode($payload, $jwt_key, 'HS256');
}

function verify_jwt(string $token): object
{
    global $jwt_key;
    return JWT::decode($token, new Key($jwt_key, 'HS256'));
}
