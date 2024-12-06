<?php
if (strpos(__FILE__, "I:\\") !== false) {
	include_once __DIR__ . '/../mdwiki/public_html/header.php';
} else {
	include_once __DIR__ . '/../header.php';
}

include_once __DIR__ . '/auth/user_infos.php';

$message = <<<HTML
	Go to this URL to authorize this tool:<br />
	<a href='/auth/index.php?a=login'>Login</a><br />
HTML;

if (defined('global_username') && global_username != '') {
	$u_name = global_username;
	$message = <<<HTML
		You are authenticated as $u_name.<br />
		Continue to <a href='/auth/index.php?a=edit'>edit</a><br>
		<a href='/auth/index.php?a=logout'>logout</a>
	HTML;
};

echo <<<HTML
	<div class="card">
		<div class="card-header">
			Auth!
		</div>
		<div class="card-body">
			$message
		</div>
	</div>
HTML;
