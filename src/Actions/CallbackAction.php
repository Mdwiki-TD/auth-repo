<?php

declare(strict_types=1);

namespace OAuth\Actions;

use MediaWiki\OAuthClient\Client;
use MediaWiki\OAuthClient\ClientConfig;
use MediaWiki\OAuthClient\Consumer;
use MediaWiki\OAuthClient\Token;
use OAuth\Services\JwtService;
use OAuth\Services\CookieService;
use OAuth\Repository\TokenRepository;

/**
 * Handles the OAuth callback after user authorization.
 * 
 * This action:
 * 1. Validates the OAuth callback parameters
 * 2. Exchanges the request token for an access token
 * 3. Identifies the user
 * 4. Stores tokens and session data
 * 5. Redirects to the appropriate destination
 */
final class CallbackAction extends BaseAction
{
    private const DEFAULT_REDIRECT = '/Translation_Dashboard/index.php';

    private JwtService $jwtService;
    private CookieService $cookieService;
    private TokenRepository $tokenRepository;
    private ?Client $client = null;

    public function __construct(
        ?\Settings $settings = null,
        ?JwtService $jwtService = null,
        ?CookieService $cookieService = null,
        ?TokenRepository $tokenRepository = null
    ) {
        parent::__construct($settings);
        $this->jwtService = $jwtService ?? JwtService::fromSettings();
        $this->cookieService = $cookieService ?? CookieService::fromSettings();
        $this->tokenRepository = $tokenRepository ?? TokenRepository::fromSettings();
    }

    public function execute(): void
    {
        $this->validateConfiguration();
        $this->ensureSession();
        $this->validateCallbackParameters();
        
        $this->initializeOAuthClient();
        $requestToken = $this->getRequestToken();
        $accessToken = $this->exchangeTokens($requestToken);
        $identity = $this->identifyUser($accessToken);
        
        $this->storeUserData($identity, $accessToken);
        $this->redirectToDestination();
    }

    /**
     * Validate that required OAuth configuration is available.
     */
    private function validateConfiguration(): void
    {
        if (empty($this->settings->oauthUrl) || 
            empty($this->settings->consumerKey) || 
            empty($this->settings->consumerSecret) || 
            empty($this->settings->userAgent)) {
            throw new \RuntimeException('Required OAuth configuration variables are not defined');
        }
    }

    /**
     * Validate that this is a proper OAuth callback.
     */
    private function validateCallbackParameters(): void
    {
        if (!isset($_GET['oauth_verifier'])) {
            $this->showErrorAndExit(
                "This page should only be accessed after redirection back from the wiki.",
                "index.php?a=login",
                "Login"
            );
        }

        if (!isset($_SESSION['request_key'], $_SESSION['request_secret'])) {
            $this->showErrorAndExit(
                "OAuth session expired or invalid. Please start login again.",
                "index.php?a=login",
                "Login"
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
                "An internal error occurred while setting up authentication. Please try again later."
            );
        }
    }

    /**
     * Get the request token from session.
     */
    private function getRequestToken(): Token
    {
        try {
            return new Token($_SESSION['request_key'], $_SESSION['request_secret']);
        } catch (\Exception $e) {
            error_log("OAuth Error: Invalid request token from session: " . $e->getMessage());
            $this->showErrorAndExit(
                "Your session contains an invalid token. Please try logging in again."
            );
        }
    }

    /**
     * Exchange request token for access token.
     */
    private function exchangeTokens(Token $requestToken): Token
    {
        try {
            $accessToken = $this->client->complete($requestToken, $_GET['oauth_verifier']);
            unset($_SESSION['request_key'], $_SESSION['request_secret']);
            return new Token($accessToken->key, $accessToken->secret);
        } catch (\MediaWiki\OAuthClient\Exception $e) {
            error_log("OAuth Error: Authentication failed during client->complete(): " . $e->getMessage());
            $this->showErrorAndExit(
                "Authentication with the wiki failed. Please try again.",
                "index.php?a=login",
                "Try again"
            );
        }
    }

    /**
     * Identify the user using the access token.
     */
    private function identifyUser(Token $accessToken): object
    {
        try {
            return $this->client->identify($accessToken);
        } catch (\Exception $e) {
            error_log("OAuth Error: Failed during OAuth process: " . $e->getMessage());
            $this->showErrorAndExit(
                "Could not verify your identity after authentication. Please try again."
            );
        }
    }

    /**
     * Store user data in session, cookies, and database.
     */
    private function storeUserData(object $identity, Token $accessToken): void
    {
        try {
            // Store in session
            $_SESSION['username'] = $identity->username;
            
            if (!isset($_SESSION['csrf_tokens']) || !is_array($_SESSION['csrf_tokens'])) {
                $_SESSION['csrf_tokens'] = [];
            }

            // Create and store JWT
            $jwt = $this->jwtService->createToken($identity->username);
            $this->cookieService->set('jwt_token', $jwt);
            $this->cookieService->set('username', $identity->username);

            // Store access tokens in database
            $this->tokenRepository->saveTokens(
                $identity->username,
                $accessToken->key,
                $accessToken->secret
            );

            // Add user to legacy users table
            $this->tokenRepository->addUser($identity->username);
        } catch (\Exception $e) {
            error_log("OAuth Error: Failed to store user session data or update database: " . $e->getMessage());
            $this->showErrorAndExit(
                "An error occurred while saving your session. Please try logging in again."
            );
        }
    }

    /**
     * Redirect to the appropriate destination.
     */
    private function redirectToDestination(): void
    {
        $test = $_GET['test'] ?? '';
        $returnTo = $_GET['return_to'] ?? '';

        // Don't redirect back to auth pages
        if (!empty($returnTo) && strpos($returnTo, '/auth/') !== false) {
            $returnTo = '';
        }

        $newUrl = self::DEFAULT_REDIRECT;
        
        if (!empty($returnTo) && strpos($returnTo, self::DEFAULT_REDIRECT) === false) {
            $newUrl = filter_var($returnTo, FILTER_VALIDATE_URL) 
                ? $returnTo 
                : self::DEFAULT_REDIRECT;
        } else {
            $state = $this->createState(['cat', 'code']);
            if (!empty($state)) {
                $newUrl .= '?' . http_build_query($state);
            }
        }

        if (empty($test)) {
            $this->redirect($newUrl);
        } else {
            $username = htmlspecialchars($_SESSION['username'], ENT_QUOTES, 'UTF-8');
            echo "You are authenticated as {$username}.<br>";
            echo "<a href='$newUrl'>Continue</a>";
        }
    }
}
