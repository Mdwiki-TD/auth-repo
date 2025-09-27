<?php
require_once __DIR__ . '/access_helps.php';
require_once __DIR__ . '/access_helps_new.php';
require_once __DIR__ . '/jwt_config.php';

use function OAuth\JWT\create_jwt;
use MediaWiki\OAuthClient\Client;
use MediaWiki\OAuthClient\ClientConfig;
use MediaWiki\OAuthClient\Consumer;
use MediaWiki\OAuthClient\Token;
use function OAuth\Helps\add_to_cookies;
use function OAuth\AccessHelps\add_access_to_dbs;
use function OAuth\AccessHelpsNew\add_access_to_dbs_new;
use function OAuth\AccessHelps\sql_add_user;

/**
 * دالة لإظهار رسالة خطأ والخروج مع رابط اختياري
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

// --------- بدء الجلسة ----------
if (session_status() === PHP_SESSION_NONE) session_start();

// --------- تحقق من وجود oauth_verifier ----------
if (!isset($_GET['oauth_verifier'])) {
    showErrorAndExit(
        "This page should only be accessed after redirection back from the wiki.",
        "index.php?a=login",
        "Login"
    );
}

// --------- تحقق من بيانات Request Token ----------
if (!isset($_SESSION['request_key'], $_SESSION['request_secret'])) {
    showErrorAndExit(
        "OAuth session expired or invalid. Please start login again.",
        "index.php?a=login",
        "Login"
    );
}

// --------- تهيئة عميل OAuth ----------
try {
    $conf = new ClientConfig($oauthUrl);
    $conf->setConsumer(new Consumer($consumerKey, $consumerSecret));
    $conf->setUserAgent($gUserAgent);
    $client = new Client($conf);
} catch (\Exception $e) {
    showErrorAndExit("Failed to initialize OAuth client: " . $e->getMessage());
}

// --------- إنشاء Request Token ----------
try {
    $requestToken = new Token($_SESSION['request_key'], $_SESSION['request_secret']);
} catch (\Exception $e) {
    showErrorAndExit("Invalid request token: " . $e->getMessage());
}

// --------- إكمال عملية OAuth للحصول على Access Token ----------
try {
    $accessToken1 = $client->complete($requestToken, $_GET['oauth_verifier']);
    unset($_SESSION['request_key'], $_SESSION['request_secret']);
} catch (\MediaWiki\OAuthClient\Exception $e) {
    showErrorAndExit(
        "OAuth authentication failed: " . $e->getMessage(),
        "index.php?a=login",
        "Try again"
    );
}

// --------- إنشاء Access Token وتحديد المستخدم ----------
try {
    $accessToken = new Token($accessToken1->key, $accessToken1->secret);
    $ident = $client->identify($accessToken);
} catch (\Exception $e) {
    showErrorAndExit("Failed to identify user: " . $e->getMessage());
}

// --------- تخزين معلومات الجلسة و JWT ----------
try {
    $_SESSION['username'] = $ident->username;
    $jwt = create_jwt($ident->username);
    add_to_cookies('jwt_token', $jwt);
    add_to_cookies('username', $ident->username);

    if (!isset($_SESSION['csrf_tokens']) || !is_array($_SESSION['csrf_tokens'])) {
        $_SESSION['csrf_tokens'] = [];
    }

    add_access_to_dbs_new($ident->username, $accessToken1->key, $accessToken1->secret);
    add_access_to_dbs($ident->username, $accessToken1->key, $accessToken1->secret);
    sql_add_user($ident->username);
} catch (\Exception $e) {
    showErrorAndExit("Failed to store user data: " . $e->getMessage());
}

// --------- تحديد الرابط للعودة بعد تسجيل الدخول ----------
$test = $_GET['test'] ?? '';
$return_to = $_GET['return_to'] ?? '';
$newurl = "/Translation_Dashboard/index.php"; // القيمة الافتراضية

if (!empty($return_to)) {
    $parsed = parse_url($return_to);
    if (isset($parsed['path']) && str_contains($parsed['path'], '/auth/')) {
        // إذا كان المسار يحتوي على /auth/، نعيد التوجيه إلى Translation_Dashboard
        $newurl = "/Translation_Dashboard/index.php";
    } else {
        // خلاف ذلك، نستخدم return_to إذا كان URL صالح
        $newurl = filter_var($return_to, FILTER_VALIDATE_URL) ? $return_to : "/Translation_Dashboard/index.php";
    }
} else {
    // معالجة الحالة العادية مع state من GET
    $state = [];
    foreach (['cat', 'code', 'type', 'doit'] as $key) {
        $da1 = filter_input(INPUT_GET, $key, FILTER_SANITIZE_STRING);
        if (!empty($da1)) $state[$key] = $da1;
    }
    if (!empty($state)) {
        $newurl = "/Translation_Dashboard/index.php?" . http_build_query($state);
    }
}
// --------- إعادة التوجيه أو عرض الرابط ----------
if (empty($test)) {
    header("Location: $newurl");
    exit;
} else {
    echo "You are authenticated as " . htmlspecialchars($ident->username, ENT_QUOTES, 'UTF-8') . ".<br>";
    echo "<a href='$newurl'>Continue</a>";
}
