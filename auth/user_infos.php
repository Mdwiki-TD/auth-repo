<?php
//---
include_once __DIR__ . '/config.php';
include_once __DIR__ . '/helps.php';
//---
require_once __DIR__ . '/access_helps.php';
//---
use function OAuth\Helps\get_from_cookie;
use function OAuth\AccessHelps\get_access_from_db;
//---
$secure = ($_SERVER['SERVER_NAME'] == "localhost") ? false : true;
if ($_SERVER['SERVER_NAME'] != 'localhost') {
	session_name("mdwikitoolforgeoauth");
	session_set_cookie_params(0, "/", $domain, $secure, $secure);
}
//---
function banner_alert($text)
{
	return <<<HTML
	<div class='container'>
		<div class="alert alert-danger d-flex align-items-center" role="alert">
			<svg xmlns="http://www.w3.org/2000/svg" class="d-none">
				<symbol id="check-circle-fill" viewBox="0 0 16 16">
					<path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z" />
				</symbol>
				<symbol id="info-fill" viewBox="0 0 16 16">
					<path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
				</symbol>
				<symbol id="exclamation-triangle-fill" viewBox="0 0 16 16">
					<path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z" />
				</symbol>
			</svg>
			<svg class="bi flex-shrink-0 me-2" role="img" aria-label="Danger:">
				<use xlink:href="#exclamation-triangle-fill" />
			</svg>
			<div>
				$text
			</div>
		</div>
	</div>
	HTML;
}
//---
$username = get_from_cookie('username');
//---
if ($_SERVER['SERVER_NAME'] == 'localhost') {
	session_start();
	$username = $_SESSION['username'] ?? '';
} elseif (!empty($username)) {
	// ---
	$access_key = get_from_cookie('accesskey');
	$access_secret = get_from_cookie('access_secret');
	// ---
	$access = get_access_from_db($username);
	// ---
	if (empty($access_key) || empty($access_secret) || $access == null) {
		echo banner_alert("No access keys found. Login again.");
		setcookie('username', '', time() - 3600, "/", $domain, true, true);
		$username = '';
	}
}
//---
define('global_username', $username);
//---
function echo_login()
{
	global $username;
	$safeUsername = htmlspecialchars($username); // Escape characters to prevent XSS

	if (empty($username)) {
		echo <<<HTML
			Go to this URL to authorize this tool:<br />
			<a href='index.php?a=login'>Login</a><br />
		HTML;
	} else {
		echo <<<HTML
			You are authenticated as $safeUsername.<br />
			Continue to <a href='index.php?a=edit'>edit</a><br>
			<a href='index.php?a=logout'>logout</a>
		HTML;
	};
	//---
};