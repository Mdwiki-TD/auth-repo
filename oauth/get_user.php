<?php
/**
 * JSON User Info Endpoint.
 *
 * Returns the current authenticated user's username as JSON.
 * Used by external applications to check authentication status.
 *
 * @package    OAuth
 * @author     MDWiki Team
 * @copyright  2024 MDWiki
 * @license    MIT
 * @version    2.0.0
 * @since      1.0.0
 *
 * @example Response:
 * ```json
 * {"username": "JohnDoe"}
 * ```
 *
 * @example Usage:
 * ```javascript
 * fetch('/auth/oauth/get_user.php')
 *   .then(r => r.json())
 *   .then(data => console.log(data.username));
 * ```
 */

declare(strict_types=1);

// Set JSON content type
if (!headers_sent()) {
    header('Content-Type: application/json');
}

// Load user info (defines global_username constant)
include_once __DIR__ . '/user_infos.php';

// Return username as JSON
$data = ["username" => global_username];

echo json_encode($data, JSON_UNESCAPED_UNICODE);
