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
if (session_status() === PHP_SESSION_NONE) session_start();
//---
$username = get_from_cookies('username');
//---
if ($_SERVER['SERVER_NAME'] == 'localhost') {
	$username = $_SESSION['username'] ?? '';
} elseif (!empty($username)) {
	// ---
	$access = get_access_from_dbs($username);
	// ---
	if ($access == null) {
		echo ba_alert("No access keys found. Login again.");
		setcookie('username', '', time() - 3600, "/", $domain, true, true);
		$username = '';
		unset($_SESSION['username']);
	}
}
//---
$global_username = $username;
//---
define('global_username', $username);
