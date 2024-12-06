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
include_once __DIR__ . '/config.php';

use Defuse\Crypto\Crypto;

function de_code_value($value)
{
    global $cookie_key;
    try {
        $value = Crypto::decrypt($value, $cookie_key);
    } catch (\Exception $e) {
        $value = $value;
    }
    return $value;
}

function en_code_value($value)
{
    global $cookie_key;
    try {
        $value = Crypto::encrypt($value, $cookie_key);
    } catch (\Exception $e) {
        $value = $value;
    };
    return $value;
}
function add_to_cookies($key, $value, $age = 0)
{
    global $domain;
    if ($age == 0) {
        $twoYears = time() + 60 * 60 * 24 * 365 * 2;
        $age = $twoYears;
    }
    $secure = ($_SERVER['SERVER_NAME'] == "localhost") ? false : true;

    $value = en_code_value($value);

    // echo "add_to_cookies: value: $value<br>";
    setcookie(
        $key,
        $value,
        $twoYears,
        "/",
        $domain, // "mdwiki.toolforge.org",
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
