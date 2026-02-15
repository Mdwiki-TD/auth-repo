<?php
/**
 * Header/Bootstrap Loader.
 *
 * Loads the Bootstrap CSS framework and common header template.
 * Handles environment detection for local vs Toolforge deployment.
 *
 * @package    Auth
 * @author     MDWiki Team
 * @copyright  2024 MDWiki
 * @license    MIT
 * @version    2.0.0
 * @since      1.0.0
 */

declare(strict_types=1);

/**
 * Check if running on Windows development environment.
 *
 * On Windows (I: drive), load header from sibling mdwiki project.
 * Otherwise, load from parent directory (Toolforge structure).
 */
if (substr(__DIR__, 0, 2) === 'I:') {
    // Windows development environment
    $headerPath = __DIR__ . '/../../mdwiki/public_html/header.php';
} else {
    // Toolforge/Linux production environment
    $headerPath = __DIR__ . '/../header.php';
}

if (file_exists($headerPath)) {
    include_once $headerPath;
}
