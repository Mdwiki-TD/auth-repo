<?php
/**
 * Edit Endpoint for Testing OAuth Edits.
 *
 * Development endpoint for testing OAuth-based edits to Wikimedia wikis.
 * Accepts parameters via GET and makes edits using stored credentials.
 *
 * @package    OAuth
 * @author     MDWiki Team
 * @copyright  2024 MDWiki
 * @license    MIT
 * @version    2.0.0
 * @since      1.0.0
 *
 * @example Usage:
 * ```
 * GET /auth/oauth/edit.php?title=وب:ملعب&text=new!new!&lang=ar&summary=h!
 * ```
 *
 * @security This endpoint has no CSRF protection and should be disabled
 *           or protected in production. Used for development testing only.
 *
 * @deprecated Remove from production or add proper authentication/CSRF.
 */

declare(strict_types=1);

use function OAuth\SendEdit\auth_make_edit;
use function OAuth\Helps\get_from_cookies;
use function OAuth\AccessHelps\get_access_from_dbs;
use function OAuth\AccessHelpsNew\get_access_from_dbs_new;

// Load dependencies
include_once __DIR__ . '/../vendor/autoload.php';
include_once __DIR__ . '/send_edit.php';
include_once __DIR__ . '/helps.php';

// Get edit parameters from GET request
$title   = $_GET['title'] ?? 'وب:ملعب';    // Default: arwiki sandbox
$text    = $_GET['text'] ?? 'new!new!';     // Default test content
$lang    = $_GET['lang'] ?? 'ar';            // Default: Arabic Wikipedia
$summary = $_GET['summary'] ?? 'h!';        // Default summary

// Get current user from cookies
$username = get_from_cookies('username');

if (empty($username)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Not authenticated']);
    exit(1);
}

// Retrieve access tokens (try new table first, fallback to legacy)
$access = get_access_from_dbs_new($username);

if ($access === null) {
    $access = get_access_from_dbs($username);
}

if ($access === null) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No access keys found. Please login again.']);
    exit(1);
}

$accessKey = $access['access_key'] ?? '';
$accessSecret = $access['access_secret'] ?? '';

if (empty($accessKey) || empty($accessSecret)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Invalid access credentials']);
    exit(1);
}

// Perform the edit
$result = auth_make_edit(
    $title,
    $text,
    $summary,
    $lang,
    $accessKey,
    $accessSecret
);

// Output result
echo "\n== Edit Result ==<br>";
echo htmlspecialchars(
    json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
    ENT_QUOTES,
    'UTF-8'
);
