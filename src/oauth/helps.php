<?php

namespace OAuth\Helps;
/*
Usage:
use function OAuth\Helps\add_to_cookies;
use function OAuth\Helps\get_from_cookies;
use function OAuth\Helps\decode_value;
use function OAuth\Helps\encode_value;
*/

use Defuse\Crypto\Crypto;
use OAuth\Settings\Settings;

function decode_value($value, $key_type = "cookie")
{
    if (empty(trim($value))) return "";

    $settings = Settings::getInstance();
    $use_key  = ($key_type === "decrypt") ? $settings->decryptKey : $settings->cookieKey;

    if ($use_key === null) return "";

    try {
        return Crypto::decrypt($value, $use_key);
    } catch (\Exception $e) {
        return "";
    }
}

function encode_value($value, $key_type = "cookie")
{
    if (empty(trim($value))) return "";

    $settings = Settings::getInstance();
    $use_key = ($key_type === "decrypt") ? $settings->decryptKey : $settings->cookieKey;

    if ($use_key === null) return "";

    try {
        return Crypto::encrypt($value, $use_key);
    } catch (\Exception $e) {
        return "";
    }
}

function add_to_cookies($key, $value, $age = 0)
{
    $settings = Settings::getInstance();
    $twoYears = time() + 60 * 60 * 24 * 365 * 2;
    if ($age == 0) {
        $age = $twoYears;
    }
    $secure = ($settings->domain == "localhost") ? false : true;

    $value = encode_value($value, "cookie");

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
        $value = decode_value($_COOKIE[$key], "cookie");
    } else {
        // echo "key: $key<br>";
        $value = "";
    };
    if ($key == "username") {
        $value = str_replace("+", " ", $value);
    };
    return $value;
}
