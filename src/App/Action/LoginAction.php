<?php

declare(strict_types=1);

namespace App\Action;

use App\Config;
use App\Http\Helpers;
use MediaWiki\OAuthClient\Client;
use MediaWiki\OAuthClient\ClientConfig;
use MediaWiki\OAuthClient\Consumer;
use RuntimeException;

/**
 * Initiates the OAuth 1.0 authorization flow.
 *
 * Redirects the user to the Wikimedia OAuth authorize page.
 * On localhost the link is displayed instead of auto-redirecting.
 */
final class LoginAction
{
    public function execute(): void
    {
        $config = Config::getInstance();

        if ($config->consumerKey === '') {
            throw new RuntimeException(
                'Required OAuth configuration variables are not defined: consumerKey is missing'
            );
        }
        if ($config->consumerSecret === '') {
            throw new RuntimeException(
                'Required OAuth configuration variables are not defined: consumerSecret is missing'
            );
        }

        // Build OAuth client ------------------------------------------------
        try {
            $conf = new ClientConfig($config->oauthUrl);
            $conf->setConsumer(new Consumer($config->consumerKey, $config->consumerSecret));
            $conf->setUserAgent($config->userAgent);
            $client = new Client($conf);
        } catch (\Exception $e) {
            error_log('OAuth Error: Failed to initialize OAuth client: ' . $e->getMessage());
            self::showErrorAndExit(
                'An internal error occurred while preparing the authentication service. Please try again later.'
            );
            return; // @codeCoverageIgnore
        }

        // Build callback URL with state parameters --------------------------
        $callbackUrl = $this->buildCallbackUrl(
            'https://mdwiki.toolforge.org/auth/index.php?a=callback'
        );

        try {
            $client->setCallback($callbackUrl);
        } catch (\Exception $e) {
            error_log('OAuth Error: Failed to set OAuth callback URL: ' . $e->getMessage());
            self::showErrorAndExit(
                'An internal error occurred while configuring the authentication callback. Please try again.'
            );
            return; // @codeCoverageIgnore
        }

        // Initiate OAuth flow -----------------------------------------------
        try {
            /** @var array{0: string|null, 1: object|null} $result */
            $result = $client->initiate();
            [$authUrl, $token] = $result;

            if (!$authUrl || !$token) {
                error_log('OAuth Error: client->initiate() returned empty authUrl or token.');
                self::showErrorAndExit(
                    'Failed to initiate the authentication process with the wiki. Please try again.'
                );
                return; // @codeCoverageIgnore
            }

            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['request_key']    = $token->key;
            $_SESSION['request_secret'] = $token->secret;
        } catch (\Exception $e) {
            error_log('OAuth Error: Exception during OAuth initiation: ' . $e->getMessage());
            self::showErrorAndExit(
                'An error occurred while starting the authentication process. Please try again.'
            );
            return; // @codeCoverageIgnore
        }

        // Redirect (or display link on localhost) ---------------------------
        if ($config->domain !== 'localhost') {
            header("Location: {$authUrl}");
            exit(0);
        }

        $safeUrl = htmlspecialchars($authUrl, ENT_QUOTES, 'UTF-8');
        echo "Go to this URL to authorize:<br /><a href='{$safeUrl}'>{$safeUrl}</a>";
    }

    // ── helpers ──────────────────────────────────────────

    private function buildCallbackUrl(string $baseUrl): string
    {
        $state = Helpers::createState(['cat', 'code', 'test']);

        $returnTo = Helpers::createReturnTo($_SERVER['HTTP_REFERER'] ?? '');
        if ($returnTo !== '') {
            $state['return_to'] = $returnTo;
        }

        if ($state !== []) {
            $baseUrl .= '&' . http_build_query($state);
        }

        return $baseUrl;
    }

    /**
     * Render a red error box and terminate.
     */
    public static function showErrorAndExit(
        string $message,
        ?string $linkUrl = null,
        ?string $linkText = null,
    ): void {
        error_log('[OAuth Error] User was shown the following message: ' . $message);

        echo "<div style='border:1px solid red; padding:10px; background:#ffe6e6; color:#900;'>";
        echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        if ($linkUrl !== null && $linkText !== null) {
            echo "<br><a href='" . htmlspecialchars($linkUrl, ENT_QUOTES, 'UTF-8')
                . "'>" . htmlspecialchars($linkText, ENT_QUOTES, 'UTF-8') . '</a>';
        }
        echo '</div>';
        exit;
    }
}
