<?php

use MediaWiki\OAuthClient\Client;
use MediaWiki\OAuthClient\ClientConfig;
use MediaWiki\OAuthClient\Consumer;

include_once __DIR__ . '/u.php';

/**
 * Show error message and exit script
 * @param string $message The error message
 * @param string|null $linkUrl Optional link URL
 * @param string|null $linkText Optional link text
 */
function showErrorAndExit(string $message, ?string $linkUrl = null, ?string $linkText = null) {
    echo "<div style='border:1px solid red; padding:10px; background:#ffe6e6; color:#900;'>";
    echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    if ($linkUrl && $linkText) {
        echo "<br><a href='" . htmlspecialchars($linkUrl, ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($linkText, ENT_QUOTES, 'UTF-8') . "</a>";
    }
    echo "</div>";
    exit;
}

// Configure the OAuth client with the URL and consumer details.
try {
    $conf = new ClientConfig($oauthUrl);
    $conf->setConsumer(new Consumer($consumerKey, $consumerSecret));
    $conf->setUserAgent($gUserAgent);
    $client = new Client($conf);
} catch (\Exception $e) {
    showErrorAndExit("Failed to initialize OAuth client: " . $e->getMessage());
}

function create_callback_url($url)
{
    //---
    $state = [];
    // ?action=login&cat=RTT&depth=1&code=&type=lead
    //---
    // $return_to = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
    $return_to = '';
    //---
    $allowed_domains = ['mdwiki.toolforge.org', 'localhost'];
    //---
    if (isset($_SERVER['HTTP_REFERER'])) {
        $parsed = parse_url($_SERVER['HTTP_REFERER']);
        if (in_array($parsed['host'], $allowed_domains)) {
            $return_to = $_SERVER['HTTP_REFERER'];
        }
    }
    //---
    if (!empty($return_to) && (strpos($return_to, '/auth/') === false )) {
        $state['return_to'] = $return_to;
    }
    //---
    foreach (['cat', 'code', 'type', 'test', 'doit'] as $key) {
        // $da = $_GET[$key] ?? '';
        $da = filter_input(INPUT_GET, $key, FILTER_SANITIZE_STRING);
        if (!empty($da)) {
            $state[$key] = $da;
        }
    };
    //---
    $sta = "";
    if (!empty($state)) {
        $sta = '&' . http_build_query($state);
    }
    //---
    // echo $sta;
    //---
    $oauth_call = $url . $sta;
    //---
    return $oauth_call;
}
// ---
$call_back_url = create_callback_url('https://mdwiki.toolforge.org/auth/index.php?a=callback');
// ---
try {
    $client->setCallback($call_back_url);
} catch (\Exception $e) {
    showErrorAndExit("Failed to set OAuth callback URL: " . $e->getMessage());
}

// Send an HTTP request to the wiki to get the authorization URL and a Request Token.
// These are returned together as two elements in an array (with keys 0 and 1).
try {
    list($authUrl, $token) = $client->initiate();
    if (!$authUrl || !$token) {
        showErrorAndExit("Failed to initiate OAuth authorization.");
    }
} catch (\Exception $e) {
    showErrorAndExit("OAuth initiation failed: " . $e->getMessage());
}

// Store the Request Token in the session. We will retrieve it from there when the user is sent back
// from the wiki (see demo/callback.php).
if (session_status() === PHP_SESSION_NONE) session_start();
try {
    $_SESSION['request_key'] = $token->key;
    $_SESSION['request_secret'] = $token->secret;
} catch (\Exception $e) {
    showErrorAndExit("Failed to store request token in session: " . $e->getMessage());
}

// Redirect the user to the authorization URL. This is usually done with an HTTP redirect, but we're
// making it a manual link here so you can see everything in action.
echo "Go to this URL to authorize this demo:<br /><a href='$authUrl'>$authUrl</a>";
if ($_SERVER['SERVER_NAME'] !== 'localhost') {
    header("Location: $authUrl");
    exit(0);
}
