<?php

namespace OAuth\Helps;
/*
Usage:
use function OAuth\Helps\add_to_cookies;
use function OAuth\Helps\get_from_cookies;
use function OAuth\Helps\de_code_value;
use function OAuth\Helps\en_code_value;
*/

include_once __DIR__ . '/../vendor_load.php';
include_once __DIR__ . '/settings.php';

use Defuse\Crypto\Crypto;

function de_code_value($value, $key_type = "cookie")
{
    $settings = \Settings::getInstance();
    // ---
    if (empty(trim($value))) {
        return "";
    }
    // ---
    $use_key = ($key_type == "decrypt") ? $settings->decryptKey : $settings->cookieKey;
    // ---
    if ($use_key === null) {
        return "";
    }
    try {
        $value = Crypto::decrypt($value, $use_key);
    } catch (\Exception $e) {
        $value = "";
    }
    return $value;
}

function en_code_value($value, $key_type = "cookie")
{
    $settings = \Settings::getInstance();
    // ---
    $use_key = ($key_type == "decrypt") ? $settings->decryptKey : $settings->cookieKey;
    // ---
    if (empty(trim($value))) {
        return "";
    }
    // ---
    if ($use_key === null) {
        return "";
    }
    try {
        $value = Crypto::encrypt($value, $use_key);
    } catch (\Exception $e) {
        $value = "";
    };
    return $value;
}

function add_to_cookies($key, $value, $age = 0)
{
    $settings = \Settings::getInstance();
    $twoYears = time() + 60 * 60 * 24 * 365 * 2;
    if ($age == 0) {
        $age = $twoYears;
    }
    $secure = ($settings->domain == "localhost") ? false : true;

    $value = en_code_value($value);

    // echo "add_to_cookies: value: $value<br>";
    setcookie(
        $key,
        $value,
        $age,
        "/",
        $settings->domain, // "mdwiki.toolforge.org",
        $secure,  // only secure (https)
        $secure   // httponly
    );
}

function get_from_cookies($key)
{
    if (isset($_COOKIE[$key])) {
        $value = de_code_value($_COOKIE[$key]);
    } else {
        // echo "key: $key<br>";
        $value = "";
    };
    if ($key == "username") {
        $value = str_replace("+", " ", $value);
    };
    return $value;
}
