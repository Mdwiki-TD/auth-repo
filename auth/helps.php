<?php

namespace OAuth\Helps;
/*
Usage:
use function OAuth\Helps\de_code_value;
use function OAuth\Helps\en_code_value;
*/

include_once __DIR__ . '/../vendor_load.php';
include_once __DIR__ . '/config.php';

use Defuse\Crypto\Crypto;

function de_code_value($value)
{
    global $decrypt_key;
    try {
        $value = Crypto::decrypt($value, $decrypt_key);
    } catch (\Exception $e) {
        $value = $value;
    }
    return $value;
}

function en_code_value($value)
{
    global $decrypt_key;
    try {
        $value = Crypto::encrypt($value, $decrypt_key);
    } catch (\Exception $e) {
        $value = $value;
    };
    return $value;
}
