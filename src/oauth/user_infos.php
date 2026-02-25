<?php
//---
include_once __DIR__ . '/settings.php';
include_once __DIR__ . '/helps.php';
//---
require_once __DIR__ . '/access_helps.php';
require_once __DIR__ . '/access_helps_new.php';
//---
use function OAuth\Helps\get_from_cookies;
use function OAuth\AccessHelps\get_access_from_dbs;
use function OAuth\AccessHelpsNew\get_access_from_dbs_new;
//---
$settings = Settings::getInstance();
//---
$cookieDomain = $settings->domain;
$secure = $cookieDomain ? false : true;
// ---
if ($cookieDomain != 'localhost') {
	if (session_status() === PHP_SESSION_NONE) {
		session_name("mdwikitoolforgeoauth");
		// Ensure $domain is defined, fallback to server name
		session_set_cookie_params(0, "/", $cookieDomain, $secure, $secure);
	}
}

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
if ($settings->domain == 'localhost') {
	$username = $_SESSION['username'] ?? '';
} elseif (!empty($username)) {
	// ---
	$access = get_access_from_dbs_new($username);
	// ---
	if ($access == null) {
		$access = get_access_from_dbs($username);
	}
	// ---
	if ($access == null) {
		echo ba_alert("No access keys found. Login again.");
		setcookie('username', '', time() - 3600, "/", $cookieDomain, true, true);
		$username = '';
		unset($_SESSION['username']);
	}
}
//---
$global_username = $username;
//---
define('global_username', $username);
