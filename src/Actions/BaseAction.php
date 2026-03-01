<?php

declare(strict_types=1);

namespace OAuth\Actions;

/**
 * Base class for OAuth action handlers.
 * 
 * Provides common functionality for error handling, redirects, and responses.
 */
abstract class BaseAction
{
    protected \Settings $settings;

    public function __construct(?\Settings $settings = null)
    {
        $this->settings = $settings ?? \Settings::getInstance();
    }

    /**
     * Execute the action. Must be implemented by subclasses.
     */
    abstract public function execute(): void;

    /**
     * Display a styled error message and terminate execution.
     *
     * @param string $message The error message to display
     * @param string|null $linkUrl Optional link URL
     * @param string|null $linkText Optional link text
     */
    protected function showErrorAndExit(
        string $message,
        ?string $linkUrl = null,
        ?string $linkText = null
    ): void {
        error_log("[OAuth Error] User was shown the following message: " . $message);

        echo "<div style='border:1px solid red; padding:10px; background:#ffe6e6; color:#900;'>";
        echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        if ($linkUrl && $linkText) {
            echo "<br><a href='" . htmlspecialchars($linkUrl, ENT_QUOTES, 'UTF-8') . "'>";
            echo htmlspecialchars($linkText, ENT_QUOTES, 'UTF-8') . "</a>";
        }
        echo "</div>";
        exit;
    }

    /**
     * Redirect to a URL.
     *
     * @param string $url The URL to redirect to
     */
    protected function redirect(string $url): void
    {
        header("Location: $url");
        exit;
    }

    /**
     * Send a JSON response.
     *
     * @param mixed $data The data to encode as JSON
     */
    protected function jsonResponse(mixed $data): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }
        echo json_encode($data);
    }

    /**
     * Ensure session is started.
     */
    protected function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Check if running in development mode.
     */
    protected function isDevelopment(): bool
    {
        return $this->settings->domain === 'localhost';
    }

    /**
     * Create state parameters from GET request.
     *
     * @param array<string> $keys The parameter keys to include
     * @return array<string, string> The state array
     */
    protected function createState(array $keys): array
    {
        $state = [];
        foreach ($keys as $key) {
            // Use $_GET directly for CLI compatibility, with sanitization
            $value = isset($_GET[$key]) ? htmlspecialchars((string)$_GET[$key], ENT_QUOTES, 'UTF-8') : '';
            if (!empty($value)) {
                $state[$key] = $value;
            }
        }
        return $state;
    }

    /**
     * Create a safe return URL from HTTP referer.
     *
     * @param string $httpReferer The HTTP referer header value
     * @return string The validated return URL or empty string
     */
    protected function createReturnTo(string $httpReferer): string
    {
        if (empty($httpReferer)) {
            return '';
        }

        $allowedDomains = ['mdwiki.toolforge.org', 'localhost'];
        $parsed = parse_url($httpReferer);

        if (!isset($parsed['host']) || !in_array($parsed['host'], $allowedDomains)) {
            return '';
        }

        // Don't redirect back to auth pages
        if (strpos($httpReferer, '/auth/') !== false) {
            return '';
        }

        return $httpReferer;
    }
}
