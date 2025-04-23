<?php
//---
include_once __DIR__ . '/config.php';
include_once __DIR__ . '/helps.php';
//---
require_once __DIR__ . '/access_helps.php';
//---
use function OAuth\Helps\get_from_cookies;
use function OAuth\AccessHelps\get_access_from_dbs;
//---
$secure = ($_SERVER['SERVER_NAME'] == "localhost") ? false : true;
// ---
if ($_SERVER['SERVER_NAME'] != 'localhost') {
	if (session_status() === PHP_SESSION_NONE) {
		session_name("mdwikitoolforgeoauth");
		session_set_cookie_params(0, "/", $domain, $secure, $secure);
	}
}
//---
function ba_alert($text)
{
	return <<<HTML
	<div class='container'>
		<div class="alert alert-danger" role="alert">
			<i class="bi bi-exclamation-triangle"></i> $text
		</div>
	</div>
	HTML;
}
//---
$username = get_from_cookies('username');
//---
if ($_SERVER['SERVER_NAME'] == 'localhost') {
	if (session_status() === PHP_SESSION_NONE) session_start();
	$username = $_SESSION['username'] ?? '';
} elseif (!empty($username)) {
	// ---
	$access_key = get_from_cookies('accesskey');
	$access_secret = get_from_cookies('access_secret');
	// ---
	$access = get_access_from_dbs($username);
	// ---
	if (empty($access_key) || empty($access_secret) || $access == null) {
		echo ba_alert("No access keys found. Login again.");
		setcookie('username', '', time() - 3600, "/", $domain, true, true);
		$username = '';
	}
}
//---
$global_username = $username;
//---
define('global_username', $username);
