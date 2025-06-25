<?php

include_once __DIR__ . '/../auth/jwt_config.php';

use function OAuth\JWT\verify_jwt;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

// تحقق من الهيدر "Authorization: Bearer TOKEN"
$headers = getallheaders();

if (!isset($headers['Authorization'])) {
    http_response_code(401);
    echo json_encode(['message' => 'رمز المصادقة مفقود'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$authHeader = $headers['Authorization'];
if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    http_response_code(401);
    echo json_encode(['message' => 'صيغة رمز المصادقة غير صحيحة'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

$token = $matches[1];

[$decoded, $error] = verify_jwt($token);

echo json_encode([$decoded, $error], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
