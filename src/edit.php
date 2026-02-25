<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include_once __DIR__ . '/oauth/include_all.php';

use function OAuth\SendEdit\auth_make_edit;
use function OAuth\Helps\get_from_cookies;
use function OAuth\AccessHelps\get_access_from_dbs;
use function OAuth\AccessHelpsNew\get_access_from_dbs_new;

$title   = $_GET['title'] ?? 'وب:ملعب';
$text    = $_GET['text'] ?? 'new!new!';
$lang    = $_GET['lang'] ?? 'ar';
$summary = $_GET['summary'] ?? 'h!';

// ---
$username = get_from_cookies('username');
// ---
$access = get_access_from_dbs_new($username);
// ---
if ($access == null) {
    $access = get_access_from_dbs($username);
}
// ---
$access_key = $access['access_key'] ?? "";
$access_secret = $access['access_secret'] ?? "";
// ---

$editit = auth_make_edit($title, $text, $summary, $lang, $access_key, $access_secret);

echo "\n== You made an edit ==<br>";

print(json_encode($editit, JSON_PRETTY_PRINT));
