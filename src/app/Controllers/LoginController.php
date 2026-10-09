<?php
// src/app/Controllers/LoginController.php

namespace OAuth\Controllers;

use OAuth\Utils;
use MediaWiki\OAuthClient\Client;
use MediaWiki\OAuthClient\ClientConfig;
use MediaWiki\OAuthClient\Consumer;
use OAuth\Settings;
use OAuth\User\CurrentUser;

class LoginController
{
    private Settings $settings;

    public function __construct()
    {
        $this->settings = Settings::getInstance();
    }

    /**
     * Runs the full OAuth login flow: validates configuration, initializes
     * the OAuth client, requests a token, and redirects the user to the
     * authorization URL.
     */
    public function handle(): void
    {
        $this->validateConfig();

        $client = $this->createClient();

        $callback = $this->settings->generateCallbackUrl('/auth/callback.php');
        // 'https://mdwiki.toolforge.org/auth/callback.php'

        $callbackWithState = $this->addCallbackState($callback);

        $this->setClientCallback($client, $callbackWithState);

        [$authUrl, $token] = $this->initiateAuth($client);

        // Defensive check in case control ever reaches here with invalid values
        if ($authUrl === null) {
            $this->showErrorAndExit("Authentication initialization failed. Please try again.");
            exit;
        }

        $this->storeRequestToken($token);

        $this->redirectToAuthUrl($authUrl);
    }

    /**
     * Ensures required OAuth configuration variables are available.
     */
    private function validateConfig(): void
    {
        if (empty($this->settings->consumerKey)) {
            throw new \RuntimeException('Required OAuth configuration variables are not defined: consumerKey is missing');
        }
        if (empty($this->settings->consumerSecret)) {
            throw new \RuntimeException('Required OAuth configuration variables are not defined: consumerSecret is missing');
        }
    }

    /**
     * Configures and returns the OAuth client with the URL and consumer details.
     */
    private function createClient(): Client
    {
        // Configure the OAuth client with the URL and consumer details.
        try {
            $conf = new ClientConfig($this->settings->oauthUrl);
            $conf->setConsumer(new Consumer($this->settings->consumerKey, $this->settings->consumerSecret));
            $conf->setUserAgent($this->settings->userAgent);

            return new Client($conf);
        } catch (\Exception $e) {
            // Log the detailed, internal error message for debugging.
            error_log("OAuth Error: Failed to initialize OAuth client: " . $e->getMessage());
            // Show a generic, user-friendly error message.
            $this->showErrorAndExit("An internal error occurred while preparing the authentication service. Please try again later.");
            exit;
        }
    }

    /**
     * Builds a callback URL by appending selected state parameters.
     *
     * Constructs a query fragment from a sanitized subset of GET parameters
     * (camp, cat, code, test) and, when the HTTP Referer is present and
     * its host is one of mdwiki.toolforge.org or localhost and the referer path
     * does not contain "/auth/", includes a `return_to` parameter with that referer.
     *
     * @param string $url Base callback URL to which state parameters will be appended.
     * @return string The resulting callback URL including the serialized state query (or the original URL if no state added).
     */
    private function addCallbackState(string $url): string
    {
        $state = Utils::create_state(['camp', 'cat', 'code', 'test']);

        $return_to = Utils::create_return_to($_SERVER['HTTP_REFERER'] ?? '');
        if (! empty($return_to)) {
            $state['return_to'] = $return_to;
        }

        if (! empty($state)) {
            $separator  = (strpos($url, '?') !== false) ? '&' : '?';
            $url       .= $separator . http_build_query($state);
        }

        return $url;
    }

    /**
     * Sets the OAuth callback URL on the client.
     */
    private function setClientCallback(Client $client, string $callbackWithState): void
    {
        try {
            $client->setCallback($callbackWithState);
        } catch (\Exception $e) {
            // Log the detailed error.
            error_log("OAuth Error: Failed to set OAuth callback URL: " . $e->getMessage());
            // Show a generic error.
            $this->showErrorAndExit("An internal error occurred while configuring the authentication callback. Please try again.");
            exit;
        }
    }

    /**
     * Sends an HTTP request to the wiki to get the authorization URL and a Request Token.
     *
     * @return array{0: string, 1: object} The authorization URL and the request token.
     */
    private function initiateAuth(Client $client): array
    {
        try {
            [$authUrl, $token] = $client->initiate();

            if (! $authUrl || ! $token) {
                // Log this specific failure case.
                error_log("OAuth Error: client->initiate() returned empty authUrl or token.");
                $this->showErrorAndExit("Failed to initiate the authentication process with the wiki. Please try again.");
                exit;
            }

            return [$authUrl, $token];
        } catch (\Exception $e) {
            // Log the detailed error.
            error_log("OAuth Error: Exception during OAuth initiation: " . $e->getMessage());
            // Show a generic error.
            $this->showErrorAndExit("An error occurred while starting the authentication process. Please try again.");
            exit;
        }
    }

    /**
     * Stores the Request Token in the session.
     */
    private function storeRequestToken(object $token): void
    {
        CurrentUser::ensureSessionStarted();

        $_SESSION['request_key']    = $token->key;
        $_SESSION['request_secret'] = $token->secret;

        $host   = php_uname('n');
        $sessId = session_id();
        error_log("OAuth debug (storeRequestToken): host={$host} | session_id={$sessId}");
    }

    /**
     * Redirects the user to the authorization URL, or shows the link
     * instead of auto-redirecting when running locally.
     */
    private function redirectToAuthUrl(string $authUrl): void
    {
        if ($this->settings->domain !== 'localhost') {
            header("Location: $authUrl");
            exit(0);
        }

        // For local development, show the link instead of auto-redirecting.
        echo "Go to this URL to authorize:<br /><a href='$authUrl'>$authUrl</a>";
    }

    /**
     * Displays a styled error block to the user and terminates execution.
     *
     * Also writes a log entry confirming that a user-facing error was shown.
     *
     * @param string $message The message to display to the user; HTML will be escaped.
     * @param string|null $linkUrl Optional URL to include as a link; only used if `$linkText` is provided.
     * @param string|null $linkText Optional text for the link; the link is rendered only when both `$linkUrl` and `$linkText` are non-null.
     */
    private function showErrorAndExit(string $message, ?string $linkUrl = null, ?string $linkText = null): void
    {
        // The detailed error should be logged before calling this function.
        // This log entry confirms that a user-facing error was displayed.
        error_log("[OAuth Error] User was shown the following message: " . $message);

        echo "<div style='border:1px solid red; padding:10px; background:#ffe6e6; color:#900;'>";
        echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        if ($linkUrl && $linkText) {
            echo "<br><a href='" . htmlspecialchars($linkUrl, ENT_QUOTES, 'UTF-8') . "'>" . htmlspecialchars($linkText, ENT_QUOTES, 'UTF-8') . "</a>";
        }
        echo "</div>";
    }
}
