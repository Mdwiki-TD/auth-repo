<?php

use OAuth\Settings\Settings;
use OAuth\User\CurrentUser;
use function OAuth\Utils\ba_alert;

include_once __DIR__ . '/../include_all.php';

$currentUser = CurrentUser::getInstance();
$settings = Settings::getInstance();

$msg = $currentUser->getAlertMessage();

if ($msg) {
	echo ba_alert($msg);
}
