<?php
// src/app/controllers/CallbackController.php

namespace OAuth\Controllers;

use OAuth\Settings\Settings;
use OAuth\User\CurrentUser;
use MediaWiki\OAuthClient\Token;
use MediaWiki\OAuthClient\Client;
use MediaWiki\OAuthClient\ClientConfig;
use MediaWiki\OAuthClient\Consumer;
use function OAuth\Utils\create_state;

class CallbackController
{
    private Settings $settings;

    public function __construct()
    {
        $this->settings = Settings::getInstance();
    }

    /**
     * Runs the full OAuth callback flow: validates configuration and session
     * state, completes the OAuth handshake, identifies the user, persists
     * their session, and renders the redirect/success page.
     */
    public function handle(): void
    {
        $this->validateConfig();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->validateVerifierPresent();
        $this->validateSessionTokens();

        $client = $this->createClient();
        $requestToken = $this->createRequestToken();
        $accessToken1 = $this->completeAuth($client, $requestToken);
        $ident = $this->identifyUser($client, $accessToken1);

        $this->persistUserSession($ident, $accessToken1);

        $this->renderResult($ident);
    }
    /**
     * Ensures required OAuth configuration variables are available.
     */
    private function validateConfig(): void
    {
        if (
            empty($this->settings->oauthUrl)
            || empty($this->settings->consumerKey)
            || empty($this->settings->consumerSecret)
            || empty($this->settings->userAgent)
        ) {
            throw new \RuntimeException('Required OAuth configuration variables are not defined');
        }
    }

    /**
     * Ensures the request was reached via a redirect from the wiki (i.e. contains oauth_verifier).
     */
    private function validateVerifierPresent(): void
    {
        if (!isset($_GET['oauth_verifier'])) {
            $this->showErrorAndExit(
                "This page should only be accessed after redirection back from the wiki.",
                "login.php",
                "Login"
            );
            exit;
        }
    }

    /**
     * Ensures the request token was previously stored in the session.
     */
    private function validateSessionTokens(): void
    {
        if (!isset($_SESSION['request_key'], $_SESSION['request_secret'])) {
            $this->showErrorAndExit(
                "OAuth session expired or invalid. Please start login again.",
                "login.php",
                "Login"
            );
            exit;
        }
    }

    /**
     * Configures and returns the OAuth client with the URL and consumer details.
     */
    private function createClient(): Client
    {
        try {
            $conf = new ClientConfig($this->settings->oauthUrl);
            $conf->setConsumer(new Consumer($this->settings->consumerKey, $this->settings->consumerSecret));
            $conf->setUserAgent($this->settings->userAgent);

            return new Client($conf);
        } catch (\Exception $e) {
            // Log the detailed, internal error message for debugging.
            error_log("OAuth Error: Failed to initialize OAuth client: " . $e->getMessage());
            // Show a generic, user-friendly error message.
            $this->showErrorAndExit("An internal error occurred while setting up authentication. Please try again later.");
            exit;
        }
    }

    /**
     * Builds the request token from the session-stored key/secret.
     */
    private function createRequestToken(): Token
    {
        try {
            return new Token($_SESSION['request_key'], $_SESSION['request_secret']);
        } catch (\Exception $e) {
            // Log the detailed error.
            error_log("OAuth Error: Invalid request token from session: " . $e->getMessage());
            // Show a generic error.
            $this->showErrorAndExit("Your session contains an invalid token. Please try logging in again.");
            exit;
        }
    }

    /**
     * Completes the OAuth handshake using the request token and verifier,
     * then clears the request token from the session.
     */
    private function completeAuth(Client $client, Token $requestToken): object
    {
        try {
            $accessToken1 = $client->complete($requestToken, $_GET['oauth_verifier']);
            unset($_SESSION['request_key'], $_SESSION['request_secret']);

            return $accessToken1;
        } catch (\MediaWiki\OAuthClient\Exception $e) {
            // Log the detailed error from the OAuth client.
            error_log("OAuth Error: Authentication failed during client->complete(): " . $e->getMessage());
            // Show a generic error with a link to retry.
            $this->showErrorAndExit(
                "Authentication with the wiki failed. Please try again.",
                "login.php",
                "Try again"
            );
            exit;
        }
    }

    /**
     * Identifies the authenticated user using the final access token.
     */
    private function identifyUser(Client $client, object $accessToken1): object
    {
        try {
            $accessToken = new Token($accessToken1->key, $accessToken1->secret);

            return $client->identify($accessToken);
        } catch (\Exception $e) {
            // Log the detailed error.
            error_log("OAuth Error: Failed during OAuth process: " . $e->getMessage());
            // Show a generic error.
            $this->showErrorAndExit("Could not verify your identity after authentication. Please try again.");
            exit;
        }
    }

    /**
     * Persists the authenticated user's data to cookies and the database.
     */
    private function persistUserSession(object $ident, object $accessToken1): void
    {
        $currentUser = CurrentUser::getInstance();

        try {
            $currentUser->addUsernameToCookies($ident->username);

            if (!isset($_SESSION['csrf_tokens']) || !is_array($_SESSION['csrf_tokens'])) {
                $_SESSION['csrf_tokens'] = [];
            }

            $currentUser->addUserData($ident->username, $accessToken1->key, $accessToken1->secret);
        } catch (\Exception $e) {
            // Log the detailed error.
            error_log("OAuth Error: Failed to store user session data or update database: " . $e->getMessage());
            // Show a generic error.
            $this->showErrorAndExit("An error occurred while saving your session. Please try logging in again.");
            exit;
        }
    }

    /**
     * Determines the URL to redirect the user to after a successful login.
     */
    private function resolveRedirectUrl(): string
    {
        $return_to = $_GET['return_to'] ?? '';
        $newurl = "/Translation_Dashboard/index.php";

        if (!empty($return_to)) {
            $parsedReturn = parse_url($return_to);
            $parsedServer = parse_url($this->settings->ServerUrl);

            $returnScheme = isset($parsedReturn['scheme']) ? strtolower($parsedReturn['scheme']) : '';
            $returnHost = isset($parsedReturn['host']) ? strtolower($parsedReturn['host']) : '';
            $serverScheme = isset($parsedServer['scheme']) ? strtolower($parsedServer['scheme']) : '';
            $serverHost = isset($parsedServer['host']) ? strtolower($parsedServer['host']) : '';

            $returnPort = $parsedReturn['port'] ?? null;
            $serverPort = $parsedServer['port'] ?? null;

            if (
                strpos($return_to, '/auth/') !== false ||
                $parsedReturn === false ||
                $returnScheme === '' ||
                $returnHost === '' ||
                $returnScheme !== $serverScheme ||
                $returnHost !== $serverHost ||
                $returnPort !== $serverPort
            ) {
                $return_to = "";
            }
        }

        if (!empty($return_to) && (strpos($return_to, '/Translation_Dashboard/index.php') === false)) {
            $newurl = filter_var($return_to, FILTER_VALIDATE_URL) ? $return_to : '/Translation_Dashboard/index.php';
        } else {
            $state = create_state(['camp', 'cat', 'code']);
            $state = http_build_query($state);
            $newurl = "/Translation_Dashboard/index.php?$state";
        }

        return $newurl;
    }

    /**
     * Renders either the auto-redirecting success page or, in test mode,
     * a plain confirmation page with a manual continue link.
     */
    private function renderResult(object $ident): void
    {
        $test = $_GET['test'] ?? '';
        $newurl = $this->resolveRedirectUrl();
        $newurlAttr = htmlspecialchars($newurl, ENT_QUOTES, 'UTF-8');
        $newurlJs = json_encode($newurl);

        if (empty($test)) {
            echo <<<HTML
                <meta http-equiv='refresh' content='0; url=$newurlAttr'>
                <br>
                <h1> Login Successful </h1>
                <h2>
                    <a target="_blank" href='$newurlAttr'>Continue</a>
                </h2>
                <script type='text/javascript'>
                window.open($newurlJs, '_self');
                </script>
                <noscript>
                    <meta http-equiv='refresh' content='0; url=$newurlAttr'>
                </noscript>
            HTML;
            // header("Location: $newurl");
            exit;
        }

        echo "You are authenticated as " . htmlspecialchars($ident->username, ENT_QUOTES, 'UTF-8') . ".<br>";
        echo "<a href='$newurlAttr'>Continue</a>";
    }

    /**
     * Displays a user-facing error message in a red-bordered box, optionally
     * with a link, then terminates execution.
     *
     * Also records the shown message to the server error log for diagnostic purposes.
     *
     * @param string $message The message to display to the user.
     * @param string|null $linkUrl Optional URL to include as a link beneath the message.
     * @param string|null $linkText Optional text label for the link; ignored if $linkUrl is null.
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
