<?php
/**
 * Authentication Status View.
 *
 * Displays the current authentication status to the user. Shows either
 * a login link (for unauthenticated users) or the username with logout
 * link (for authenticated users).
 *
 * @package    Auth
 * @author     MDWiki Team
 * @copyright  2024 MDWiki
 * @license    MIT
 * @version    2.1.0
 * @since      1.0.0
 *
 * @global string global_username The current username (from user_infos.php).
 */

declare(strict_types=1);

// Start session for CSRF token access
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load header (bootstrap/styles) and user info
include_once __DIR__ . '/header.php';
include_once __DIR__ . '/oauth/user_infos.php';

// Determine message based on authentication status
if (defined('global_username') && global_username !== '') {
    $username = htmlspecialchars(global_username, ENT_QUOTES, 'UTF-8');

    // Get CSRF token for logout link
    $csrfToken = $_SESSION['csrf_token'] ?? '';
    $logoutUrl = '/auth/index.php?a=logout';

    if ($csrfToken !== '') {
        $logoutUrl .= '&csrf_token=' . urlencode($csrfToken);
    }

    $message = <<<HTML
        You are authenticated as <strong>{$username}</strong>.<br />
        <a href='{$logoutUrl}'>Logout</a>
    HTML;
} else {
    $message = <<<HTML
        Go to this URL to authorize this tool:<br />
        <a href='/auth/index.php?a=login'>Login</a><br />
    HTML;
}
?>

<div class="card">
    <div class="card-header">
        Authentication
    </div>
    <div class="card-body">
        <?= $message ?>
    </div>
</div>
