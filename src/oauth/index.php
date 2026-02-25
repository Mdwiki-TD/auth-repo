<?php
include_once __DIR__ . '/../vendor_load.php';
include_once __DIR__ . '/settings.php';
include_once __DIR__ . '/helps.php';
//---
$allowedActions = ['login', 'callback', 'logout', 'edit', 'get_user'];
$action = $_GET['a'] ?? 'user_infos';
//---
if (in_array($action, $allowedActions)) {

	$actionFile = $action . '.php';

	// Redirect to the corresponding action file
	// header("Location: " . $actionFile);
	include_once __DIR__ . "/" . $actionFile;
};
