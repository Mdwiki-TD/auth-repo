<?php
/**
 * Vendor Autoload Loader.
 *
 * Loads Composer's autoload file. Include this file at the start of
 * any script that needs composer dependencies.
 *
 * @package    Auth
 * @author     MDWiki Team
 * @copyright  2024 MDWiki
 * @license    MIT
 * @version    2.0.0
 * @since      1.0.0
 *
 * @example Usage:
 * ```php
 * include_once __DIR__ . '/vendor_load.php';
 * // Now you can use any composer-installed library
 * use Firebase\JWT\JWT;
 * ```
 */

declare(strict_types=1);

/**
 * Load Composer's autoloader.
 *
 * Provides access to all installed packages:
 * - mediawiki/oauthclient
 * - firebase/php-jwt
 * - defuse/php-encryption
 * - phpmailer/phpmailer
 */
require __DIR__ . '/vendor/autoload.php';

