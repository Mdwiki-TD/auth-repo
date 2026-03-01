<?php

declare(strict_types=1);

namespace OAuth\Actions;

use MediaWiki\OAuthClient\Client;
use MediaWiki\OAuthClient\ClientConfig;
use MediaWiki\OAuthClient\Consumer;

/**
 * Handles the OAuth login initiation process.
 * 
 * This action:
 * 1. Configures the OAuth client
 * 2. Initiates the OAuth flow with Wikimedia
 * 3. Stores request tokens in the session
 * 4. Redirects the user to the authorization URL
 */
final class LoginAction extends BaseAction
{
    private const CALLBACK_URL = 'https://mdwiki.toolforge.org/auth/index.php?a=callback';

    private ?Client $client = null;

    public function execute(): void
    {
        $this->validateConfiguration();
        $this->initializeOAuthClient();
        
        $callbackUrl = $this->buildCallbackUrl();
        $this->setCallback($callbackUrl);
        
        [$authUrl, $token] = $this->initiateOAuth();
        $this->storeRequestToken($token);
        
        $this->redirectToAuth($authUrl);
    }

    /**
     * Validate that required OAuth configuration is available.
     */
    private function validateConfiguration(): void
    {
        if (empty($this->settings->consumerKey)) {
            throw new \RuntimeException(
                'Required OAuth configuration variables are not defined: consumerKey is missing'
            );
        }
        if (empty($this->settings->consumerSecret)) {
            throw new \RuntimeException(
                'Required OAuth configuration variables are not defined: consumerSecret is missing'
            );
        }
    }

    /**
     * Initialize the OAuth client.
     */
    private function initializeOAuthClient(): void
    {
        try {
            $conf = new ClientConfig($this->settings->oauthUrl);
            $conf->setConsumer(new Consumer(
                $this->settings->consumerKey,
                $this->settings->consumerSecret
            ));
            $conf->setUserAgent($this->settings->userAgent);
            $this->client = new Client($conf);
        } catch (\Exception $e) {
            error_log("OAuth Error: Failed to initialize OAuth client: " . $e->getMessage());
            $this->showErrorAndExit(
                "An internal error occurred while preparing the authentication service. Please try again later."
            );
        }
    }

    /**
     * Build the callback URL with state parameters.
     */
    private function buildCallbackUrl(): string
    {
        $state = $this->createState(['cat', 'code', 'test']);
        
        $returnTo = $this->createReturnTo($_SERVER['HTTP_REFERER'] ?? '');
        if (!empty($returnTo)) {
            $state['return_to'] = $returnTo;
        }

        $url = self::CALLBACK_URL;
        if (!empty($state)) {
            $url .= '&' . http_build_query($state);
        }

        return $url;
    }

    /**
     * Set the callback URL on the OAuth client.
     */
    private function setCallback(string $callbackUrl): void
    {
        try {
            $this->client->setCallback($callbackUrl);
        } catch (\Exception $e) {
            error_log("OAuth Error: Failed to set OAuth callback URL: " . $e->getMessage());
            $this->showErrorAndExit(
                "An internal error occurred while configuring the authentication callback. Please try again."
            );
        }
    }

    /**
     * Initiate the OAuth flow.
     * 
     * @return array{0: string, 1: \MediaWiki\OAuthClient\Token} [authUrl, token]
     */
    private function initiateOAuth(): array
    {
        try {
            [$authUrl, $token] = $this->client->initiate();
            
            if (!$authUrl || !$token) {
                error_log("OAuth Error: client->initiate() returned empty authUrl or token.");
                $this->showErrorAndExit(
                    "Failed to initiate the authentication process with the wiki. Please try again."
                );
            }
            
            return [$authUrl, $token];
        } catch (\Exception $e) {
            error_log("OAuth Error: Exception during OAuth initiation: " . $e->getMessage());
            $this->showErrorAndExit(
                "An error occurred while starting the authentication process. Please try again."
            );
        }
    }

    /**
     * Store the request token in the session.
     */
    private function storeRequestToken($token): void
    {
        $this->ensureSession();
        $_SESSION['request_key'] = $token->key;
        $_SESSION['request_secret'] = $token->secret;
    }

    /**
     * Redirect to the authorization URL.
     */
    private function redirectToAuth(string $authUrl): void
    {
        if (!$this->isDevelopment()) {
            $this->redirect($authUrl);
        } else {
            // For local development, show the link instead of auto-redirecting
            echo "Go to this URL to authorize:<br /><a href='$authUrl'>$authUrl</a>";
        }
    }
}
