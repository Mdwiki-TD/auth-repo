<?php
/**
 * Main Entry Point (Router).
 *
 * Routes incoming requests to either the UI view (view.php) or the
 * OAuth action handler (oauth/index.php) based on GET parameters.
 *
 * @package    Auth
 * @author     MDWiki Team
 * @copyright  2024 MDWiki
 * @license    MIT
 * @version    2.0.0
 * @since      1.0.0
 *
 * @example URLs:
 * ```
 * /                  - Shows login/status UI (view.php)
 * /?a=login          - Initiates OAuth flow (oauth/index.php)
 * /?a=logout         - Ends session (oauth/index.php)
 * ```
 *
 * @see view.php For UI rendering.
 * @see oauth/index.php For OAuth action routing.
 */

declare(strict_types=1);

/**
 * Check if this is a simple UI request (no action parameters).
 *
 * @var bool
 */
$isUiRequest = empty($_GET);

// Route to appropriate handler
if ($isUiRequest) {
    // Show UI page
    include_once __DIR__ . '/view.php';
} else {
    // Handle OAuth action
    require __DIR__ . '/oauth/index.php';
}

