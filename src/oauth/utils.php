<?php

namespace OAuth\Utils;

/********************
 * Utility functions for the OAuth implementation.
 ********************/

function create_state($keys)
{
    $state = [];

    foreach ($keys as $key) {
        $da = filter_input(INPUT_GET, $key, FILTER_SANITIZE_STRING);
        if (!empty($da)) {
            $state[$key] = $da;
        }
    }
    return $state;
}

function ba_alert($text)
{
    return <<<HTML
	<div class='container'>
		<div class="alert alert-danger" role="alert">
			<i class="bi bi-exclamation-triangle"></i> $text
		</div>
	</div>
	HTML;
}

function create_return_to($http_referer)
{

    $allowed_domains = ['mdwiki.toolforge.org', 'localhost'];
    $return_to = '';
    if (isset($http_referer)) {
        $parsed = parse_url($http_referer);
        if (isset($parsed['host']) && in_array($parsed['host'], $allowed_domains)) {
            $return_to = $http_referer;
        }
    }

    if (!empty($return_to) && (strpos($return_to, '/auth/') !== false)) {
        $return_to = "";
    }

    return $return_to;
}
