<?php
if (isset($_REQUEST['test'])) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
};

$env = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'development');

if ($env === 'development' && file_exists(__DIR__ . '/oauth/load_env.php')) {
    include_once __DIR__ . '/oauth/load_env.php';
}

include_once __DIR__ . '/include_all.php';

// length of $_GET == 1 and isset($_GET['test'])
$ye = count($_GET) == 1 && isset($_GET['test']);

// if not $_GET or only $_GET['test'] isset

if (empty($_GET) || $ye) {
    include_once __DIR__ . '/view.php';
    exit();
}

//---
$allowedActions = ['login', 'callback', 'logout', 'get_user'];
$action = $_GET['a'] ?? 'user_infos';
//---
if (in_array($action, $allowedActions)) {

    $actionFile = $action . '.php';

    include_once __DIR__ . "/" . $actionFile;
};
