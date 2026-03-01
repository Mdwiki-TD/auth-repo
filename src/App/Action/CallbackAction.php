<?php

declare(strict_types=1);

namespace App\Action;

use App\Config;
use App\Database\TokenRepository;
use App\Database\UserRepository;
use App\Http\CookieManager;
use App\Http\Helpers;
use App\Security\EncryptionService;
use App\Security\JwtService;
use MediaWiki\OAuthClient\Client;
use MediaWiki\OAuthClient\ClientConfig;
use MediaWiki\OAuthClient\Consumer;
use MediaWiki\OAuthClient\Token;

/**
 * Handles the OAuth callback from Wikimedia.
 *
 * Exchanges the request token for an access token, stores the token in both
 * database tables, sets session & cookies, and redirects the user.
 */
final class CallbackAction
{
    public function execute(): void
    {
        $config = Config::getInstance();

        if (
            $config->oauthUrl === '' || $config->consumerKey === '' ||
            $config->consumerSecret === '' || $config->userAgent === ''
        ) {
            throw new \RuntimeException('Required OAuth configuration variables are not defined');
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Guard: must arrive from wiki redirect with oauth_verifier --------
        if (!isset($_GET['oauth_verifier'])) {
            self::showErrorAndExit(
                'This page should only be accessed after redirection back from the wiki.',
                'index.php?a=login',
                'Login',
            );
            return; // @codeCoverageIgnore
        }

        if (!isset($_SESSION['request_key'], $_SESSION['request_secret'])) {
            self::showErrorAndExit(
                'OAuth session expired or invalid. Please start login again.',
                'index.php?a=login',
                'Login',
            );
            return; // @codeCoverageIgnore
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
                'An internal error occurred while setting up authentication. Please try again later.'
            );
            return; // @codeCoverageIgnore
        }

        // Exchange tokens ---------------------------------------------------
        try {
            $requestToken = new Token(
                $_SESSION['request_key'],
                $_SESSION['request_secret']
            );
        } catch (\Exception $e) {
            error_log('OAuth Error: Invalid request token from session: ' . $e->getMessage());
            self::showErrorAndExit(
                'Your session contains an invalid token. Please try logging in again.'
            );
            return; // @codeCoverageIgnore
        }

        try {
            $accessToken1 = $client->complete($requestToken, $_GET['oauth_verifier']);
            unset($_SESSION['request_key'], $_SESSION['request_secret']);
        } catch (\MediaWiki\OAuthClient\Exception $e) {
            error_log('OAuth Error: Authentication failed during client->complete(): ' . $e->getMessage());
            self::showErrorAndExit(
                'Authentication with the wiki failed. Please try again.',
                'index.php?a=login',
                'Try again',
            );
            return; // @codeCoverageIgnore
        }

        // Identify user -----------------------------------------------------
        try {
            $accessToken = new Token($accessToken1->key, $accessToken1->secret);
            $ident = $client->identify($accessToken);
        } catch (\Exception $e) {
            error_log('OAuth Error: Failed during OAuth process: ' . $e->getMessage());
            self::showErrorAndExit(
                'Could not verify your identity after authentication. Please try again.'
            );
            return; // @codeCoverageIgnore
        }

        if ($accessToken1 === null || $ident === null) {
            self::showErrorAndExit(
                'Authentication failed. Please try logging in again.',
                'index.php?a=login',
                'Try again',
            );
            return; // @codeCoverageIgnore
        }

        // Persist session, cookies, and database rows -----------------------
        try {
            $_SESSION['username'] = $ident->username;

            $jwt = (new JwtService($config))->create($ident->username);
            $cookies = new CookieManager($config);
            $cookies->set('jwt_token', $jwt);
            $cookies->set('username', $ident->username);

            if (!isset($_SESSION['csrf_tokens']) || !is_array($_SESSION['csrf_tokens'])) {
                $_SESSION['csrf_tokens'] = [];
            }

            $tokens = new TokenRepository();
            $tokens->addNew($ident->username, $accessToken1->key, $accessToken1->secret);
            $tokens->addLegacy($ident->username, $accessToken1->key, $accessToken1->secret);

            (new UserRepository())->ensureExists($ident->username);
        } catch (\Exception $e) {
            error_log('OAuth Error: Failed to store user session data or update database: ' . $e->getMessage());
            self::showErrorAndExit(
                'An error occurred while saving your session. Please try logging in again.'
            );
            return; // @codeCoverageIgnore
        }

        // Redirect ----------------------------------------------------------
        $test     = $_GET['test'] ?? '';
        $returnTo = $_GET['return_to'] ?? '';
        $newUrl   = '/Translation_Dashboard/index.php';

        if ($returnTo !== '' && str_contains($returnTo, '/auth/')) {
            $returnTo = '';
        }

        if ($returnTo !== '' && !str_contains($returnTo, '/Translation_Dashboard/index.php')) {
            $newUrl = filter_var($returnTo, FILTER_VALIDATE_URL) !== false
                ? $returnTo
                : '/Translation_Dashboard/index.php';
        } else {
            $state  = Helpers::createState(['cat', 'code']);
            $query  = http_build_query($state);
            $newUrl = "/Translation_Dashboard/index.php?{$query}";
        }

        if ($test === '') {
            header("Location: {$newUrl}");
            exit;
        }

        echo 'You are authenticated as '
            . htmlspecialchars($ident->username, ENT_QUOTES, 'UTF-8') . '.<br>';
        echo "<a href='{$newUrl}'>Continue</a>";
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
