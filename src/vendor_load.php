<?php

$vendor_path = __DIR__ . '/vendor/autoload.php';
if (!file_exists($vendor_path)) {
    $vendor_path = __DIR__ . '/../vendor/autoload.php';
}

require $vendor_path;
