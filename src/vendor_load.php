<?php
/*
usage:

include_once __DIR__ . '/vendor_load.php';
*/

if (isset($_REQUEST['test'])) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
};
$vendor_path = __DIR__ . '/vendor/autoload.php';
if (!file_exists($vendor_path)) {
    $vendor_path = __DIR__ . '/../vendor/autoload.php';
}
require $vendor_path;
