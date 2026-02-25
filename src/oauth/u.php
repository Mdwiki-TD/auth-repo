<?php
if (isset($_REQUEST['test'])) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
};

include_once __DIR__ . '/include_all.php';

use function OAuth\Helps\add_to_cookies;
use function OAuth\JWT\create_jwt;

$settings = \Settings::getInstance();

if ($settings->domain === 'localhost') {
    $fa = $_GET['test'] ?? '';
    // if ($fa != 'xx') {
    // Get the Request Token's details from the session and create a new Token object.
    if (session_status() === PHP_SESSION_NONE) session_start();
    // ---
    $user = 'Mr. Ibrahem';
    $_SESSION['username'] = $user;
    //---
    add_to_cookies('username', $user);
    //---
    $jwt = create_jwt($user);
    add_to_cookies('jwt_token', $jwt);
    //---
    $return_to = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/Translation_Dashboard/index.php';
    //---
    header("Location: $return_to");
    //---
    exit(0);
};
