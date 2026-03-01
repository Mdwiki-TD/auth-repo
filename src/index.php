<?php

declare(strict_types=1);

/**
 * OAuth Authentication Entry Point
 * 
 * Routes requests to appropriate action handlers.
 * Supports both legacy procedural code and modern class-based actions.
 */

if (isset($_REQUEST['test'])) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

$env = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'development');

if ($env === 'development' && file_exists(__DIR__ . '/dev/load_env.php')) {
    include_once __DIR__ . '/dev/load_env.php';
}

include_once __DIR__ . '/include_all.php';

// Check if modern mode is enabled via env var (default: use modern classes)
$useModernActions = getenv('USE_MODERN_ACTIONS') !== 'false';

// Show view page if no action specified
$onlyTestParam = count($_GET) === 1 && isset($_GET['test']);
if (empty($_GET) || $onlyTestParam) {
    include_once __DIR__ . '/view.php';
    exit();
}

// Route to appropriate action
$allowedActions = ['login', 'callback', 'logout', 'get_user', 'user_infos'];
$action = $_GET['a'] ?? 'user_infos';

if (!in_array($action, $allowedActions)) {
    http_response_code(404);
    echo 'Action not found';
    exit();
}

// Map action names to modern action classes
$modernActionMap = [
    'login' => \OAuth\Actions\LoginAction::class,
    'callback' => \OAuth\Actions\CallbackAction::class,
    'logout' => \OAuth\Actions\LogoutAction::class,
    'get_user' => \OAuth\Actions\GetUserAction::class,
    'user_infos' => \OAuth\Actions\GetUserAction::class,
];

if ($useModernActions && isset($modernActionMap[$action])) {
    // Use modern class-based actions
    $actionClass = $modernActionMap[$action];
    
    // For user_infos, don't output JSON - just set global
    if ($action === 'user_infos') {
        $actionInstance = new \OAuth\Actions\GetUserAction(null, null, null, false);
    } else {
        $actionInstance = new $actionClass();
    }
    
    $actionInstance->execute();
} else {
    // Fall back to legacy procedural code
    $actionFile = $action . '.php';
    include_once __DIR__ . "/" . $actionFile;
}
