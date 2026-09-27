<?php
if (isset($_REQUEST['test'])) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
};

include_once __DIR__ . '/include_all.php';

$ye = count($_GET) == 1 && isset($_GET['test']);

if (empty($_GET) || $ye) {
    exit();
}

$allowedActions = ['login', 'callback', 'logout', 'get_user', 'user_infos'];
$action = $_GET['a'] ?? 'user_infos';

if (in_array($action, $allowedActions)) {

    $actionFile = $action . '.php';

    include_once __DIR__ . "/" . $actionFile;
};
