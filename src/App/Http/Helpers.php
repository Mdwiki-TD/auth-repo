<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Pure helper functions for request-state handling.
 */
final class Helpers
{
    /**
     * Collect selected GET parameters into a state array.
     *
     * @param  list<string> $keys  Parameter names to collect.
     * @return array<string, string>
     */
    public static function createState(array $keys): array
    {
        $state = [];
        foreach ($keys as $key) {
            $value = filter_input(INPUT_GET, $key, FILTER_SANITIZE_STRING);
            if (!empty($value)) {
                $state[$key] = $value;
            }
        }
        return $state;
    }

    /**
     * If the HTTP referer is from an allowed domain and is NOT inside /auth/,
     * return it as a valid return-to URL.  Otherwise return empty string.
     */
    public static function createReturnTo(string $httpReferer): string
    {
        if ($httpReferer === '') {
            return '';
        }

        $allowedDomains = ['mdwiki.toolforge.org', 'localhost'];
        $parsed = parse_url($httpReferer);

        if (!isset($parsed['host']) || !in_array($parsed['host'], $allowedDomains, true)) {
            return '';
        }

        if (str_contains($httpReferer, '/auth/')) {
            return '';
        }

        return $httpReferer;
    }

    /**
     * Render a Bootstrap-style danger alert.
     */
    public static function dangerAlert(string $text): string
    {
        return <<<HTML
        <div class='container'>
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle"></i> {$text}
            </div>
        </div>
        HTML;
    }
}
