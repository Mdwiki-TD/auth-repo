<?php
/**
 * OAuth Module Entry Point (Router).
 *
 * Routes requests to appropriate action handlers based on the 'a' GET
 * parameter. Serves as the central dispatcher for OAuth operations.
 *
 * @package    OAuth
 * @author     MDWiki Team
 * @copyright  2024 MDWiki
 * @license    MIT
 * @version    2.0.0
 * @since      1.0.0
 *
 * @example URLs:
 * ```
 * /auth/index.php?a=login    - Initiate OAuth flow
 * /auth/index.php?a=callback - Handle OAuth callback
 * /auth/index.php?a=logout   - End session
 * /auth/index.php?a=edit     - Make wiki edit
 * /auth/index.php?a=get_user - Get current user info
 * ```
 *
 * @see index.php (root) For main entry point routing.
 */

declare(strict_types=1);

// Load vendor autoload and configuration
require_once __DIR__ . '/../vendor/autoload.php';

// Verify config file exists
$configFile = __DIR__ . '/config.php';

if (!file_exists($configFile)) {
    header("HTTP/1.1 500 Internal Server Error");
    echo "Configuration could not be read. Please create {$configFile}";
    exit(1);
}

// Load dependencies
include_once $configFile;
include_once __DIR__ . '/helps.php';

/**
 * List of allowed action handlers.
 *
 * Each action maps to a corresponding PHP file (e.g., 'login' -> 'login.php').
 *
 * @var list<string>
 */
$allowedActions = ['login', 'callback', 'logout', 'edit', 'get_user'];

/**
 * Current action from GET parameter.
 *
 * @var string
 */
$action = $_GET['a'] ?? 'user_infos';

// Route to action handler
if (in_array($action, $allowedActions, true)) {
    $actionFile = __DIR__ . "/{$action}.php";

    if (file_exists($actionFile)) {
        include_once $actionFile;
    } else {
        header("HTTP/1.1 404 Not Found");
        echo "Action handler not found: " . htmlspecialchars($action, ENT_QUOTES, 'UTF-8');
    }
} else {
    // Invalid action - could show error or default page
    header("HTTP/1.1 400 Bad Request");
    echo "Invalid action: " . htmlspecialchars($action, ENT_QUOTES, 'UTF-8');
}
