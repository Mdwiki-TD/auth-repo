<?php

namespace OAuth\User;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use OAuth\Settings\Settings;
use function OAuth\MdwikiSql\fetch_query;
use function OAuth\MdwikiSql\execute_query;
/**
 * Represents the current user: handles session initialization, reading
 * identity from cookies/session, validating access in the database,
 * and determining coordinator status.
 */
class CurrentUser
{
    private static ?self $instance = null;

    private Settings $settings;

    private string $username = "";
    private ?string $alertMessage = null;

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
        $this->ensureSessionStarted();
        $this->resolveUsername();
        self::$instance = $this;
    }

    public static function getInstance(?Settings $settings = null): self
    {
        if (self::$instance === null) {
            $settings = $settings ?? Settings::getInstance();
            self::$instance = new self($settings);
        }
        return self::$instance;
    }

    // ------------------------------------------------------------------
    // Public API
    // ------------------------------------------------------------------

    public function getUsername(): string
    {
        return $this->username;
    }

    public function isLoggedIn(): bool
    {
        return $this->username !== "";
    }

    /**
     * The alert message resulting from a failed validation (if any),
     * instead of echoing it directly inside the class. Leave the actual
     * rendering to the view layer.
     */
    public function getAlertMessage(): ?string
    {
        return $this->alertMessage;
    }

    // ------------------------------------------------------------------
    // Internal helpers
    // ------------------------------------------------------------------

    private function ensureSessionStarted(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            return;
        }

        $sessionOptions = [
            "use_strict_mode"   => true,
            "use_cookies"       => true,
            "use_only_cookies"  => true,
            "cookie_httponly"   => true,
            "cookie_samesite"   => "Strict",
        ];

        // Enable secure flag in production (HTTPS)
        if (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off") {
            $sessionOptions["cookie_secure"] = true;
        }
        // Start the PHP session
        if (!headers_sent()) {
            session_start($sessionOptions);
        }
    }

    private function getKey(string $keyType = "cookie"): ?Key
    {
        return $keyType === "decrypt"
            ? $this->settings->decryptKey
            : $this->settings->cookieKey;
    }

    private function decodeValue(string $value, ?Key $useKey): string
    {
        if ($useKey === null || trim($value) === "") {
            return "";
        }

        try {
            return Crypto::decrypt($value, $useKey);
        } catch (\Throwable $e) {
            return "";
        }
    }
    private function encodeValue(string $value, ?Key $useKey): string
    {
        if ($useKey === null || trim($value) === "") {
            return "";
        }

        try {
            return Crypto::encrypt($value, $useKey);
        } catch (\Throwable $e) {
            return "";
        }
    }
    private function getFromCookies(string $key, ?Key $cookieKey): string
    {
        if (!isset($_COOKIE[$key])) {
            return "";
        }

        $value = $this->decodeValue($_COOKIE[$key], $cookieKey);

        if ($key === "username") {
            $value = str_replace("+", " ", $value);
        }

        return $value;
    }

    private function getAccessFromDb(string $user, ?Key $decryptKey): array
    {
        $user = trim($user);

        $query = <<<SQL
            SELECT access_key, access_secret
            FROM access_keys
            WHERE user_name = ? or user_name_hash = ?;
        SQL;

        $result = fetch_query($query, [$user, hash("sha256", $user)], true);

        if (!$result) {
            return [];
        }

        return [
            "access_key"    => $this->decodeValue($result[0]["access_key"], $decryptKey),
            "access_secret" => $this->decodeValue($result[0]["access_secret"], $decryptKey),
        ];
    }

    private function clearUserCookie(): void
    {
        setcookie("username", "", [
            "expires"  => time() - 3600,
            "path"     => "/",
            "domain"   => $this->settings->domain,
            "secure"   => true,
            "httponly" => true,
            "samesite" => "Lax",
        ]);
    }

    private function resolveUsername(): void
    {
        $cookieKey = $this->getKey("cookie");
        $username   = $this->getFromCookies("username", $cookieKey);

        if ($this->settings->isDevelopment()) {
            $username = $_SESSION["username"] ?? $username;
        }

        if ($this->settings->isProduction() && $username !== "") {
            $decryptKey = $this->getKey("decrypt");
            $access      = $this->getAccessFromDb($username, $decryptKey);

            if (empty($access)) {
                $this->alertMessage = "No access keys found. Login again.";
                $this->clearUserCookie();
                unset($_SESSION["username"]);
                $username = "";
            }
        }

        $this->username = $username;
    }
    public function addUsernameToCookies(string $username): void
    {
        $_SESSION["username"] = $username;

        $cookieKey = $this->getKey("cookie");
        $value      = $this->encodeValue($username, $cookieKey);

        if ($value === "") {
            return;
        }

        $twoYears = time() + 60 * 60 * 24 * 365 * 2;
        $secure   = isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off";

        setcookie(
            "username",
            $value,
            [
                "expires"  => $twoYears,
                "path"     => "/",
                "domain"   => $this->settings->domain,
                "secure"   => $secure,
                "httponly" => $secure,
                "samesite" => "Strict",
            ]
        );

        $this->username = $username;
    }
    private function sqlAddUser(string $userName): bool
    {
        $query = <<<SQL
            INSERT INTO users (username) SELECT ?
            WHERE NOT EXISTS (SELECT 1 FROM users WHERE username = ?)
        SQL;

        return execute_query($query, [$userName, $userName]);
    }

    private function addAccessToDb(string $user, string $accessKey, string $accessSecret): void
    {
        $decryptKey = $this->getKey("decrypt");

        $params = [
            $user,
            hash("sha256", $user),
            $this->encodeValue($accessKey, $decryptKey),
            $this->encodeValue($accessSecret, $decryptKey),
        ];

        // ---
        // user_name_hash = SHA2(user_name, 256)
        // ---
        $query = <<<SQL
            INSERT INTO access_keys (user_name, user_name_hash, access_key, access_secret)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                access_key = VALUES(access_key),
                access_secret = VALUES(access_secret),
                updated_at = NOW();
        SQL;

        execute_query($query, $params);
    }

    public function addUserData(string $user, string $accessKey, string $accessSecret): void
    {
        $user = trim($user);

        $this->sqlAddUser($user);
        $this->addAccessToDb($user, $accessKey, $accessSecret);
    }
}
