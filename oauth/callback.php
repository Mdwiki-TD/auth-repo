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

if (session_status() === PHP_SESSION_NONE) session_start();

// --------- تحقق من وجود oauth_verifier ----------
if (!isset($_GET['oauth_verifier'])) {
    echo "This page should only be accessed after redirection back from the wiki.<br>";
    echo "<a href='index.php?a=login'>Login</a>";
    exit;
}

// --------- تحقق من بيانات Request Token ----------
if (!isset($_SESSION['request_key'], $_SESSION['request_secret'])) {
    echo "OAuth session expired or invalid. Please start login again.<br>";
    echo "<a href='index.php?a=login'>Login</a>";
    exit;
}

// --------- تهيئة عميل OAuth ----------
$client = null;
try {
    $conf = new ClientConfig($oauthUrl);
    $conf->setConsumer(new Consumer($consumerKey, $consumerSecret));
    $conf->setUserAgent($gUserAgent);
    $client = new Client($conf);
} catch (\Exception $e) {
    echo "Failed to initialize OAuth client: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}

// --------- إنشاء Request Token ----------
$requestToken = null;
try {
    $requestToken = new Token($_SESSION['request_key'], $_SESSION['request_secret']);
} catch (\Exception $e) {
    echo "Invalid request token: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}

// --------- إكمال عملية OAuth للحصول على Access Token ----------
$accessToken1 = null;
try {
    $accessToken1 = $client->complete($requestToken, $_GET['oauth_verifier']);
    unset($_SESSION['request_key'], $_SESSION['request_secret']);
} catch (\MediaWiki\OAuthClient\Exception $e) {
    echo "OAuth authentication failed: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    echo "<br><a href='index.php?a=login'>Try again</a>";
    exit;
}

// --------- إنشاء Access Token و تحديد المستخدم ----------
$accessToken = null;
$ident = null;
try {
    $accessToken = new Token($accessToken1->key, $accessToken1->secret);
    $ident = $client->identify($accessToken);
} catch (\Exception $e) {
    echo "Failed to identify user: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
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
    echo "Failed to store user data: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}

// --------- تحديد الرابط للعودة بعد تسجيل الدخول ----------
$test = $_GET['test'] ?? '';
$return_to = $_GET['return_to'] ?? '';
$newurl = "/Translation_Dashboard/index.php";
if (!empty($return_to) && (strpos($return_to, '/Translation_Dashboard/index.php') === false)) {
    $newurl = filter_var($return_to, FILTER_VALIDATE_URL) ? $return_to : '/Translation_Dashboard/index.php';
} else {
    $state = [];
    foreach (['cat', 'code', 'type', 'doit'] as $key) {
        $da1 = filter_input(INPUT_GET, $key, FILTER_SANITIZE_STRING);
        if (!empty($da1)) $state[$key] = $da1;
    }
    $state = http_build_query($state);
    $newurl = "/Translation_Dashboard/index.php?$state";
}

// --------- إعادة التوجيه أو عرض الرابط ----------
if (empty($test)) {
    header("Location: $newurl");
    exit;
} else {
    echo "You are authenticated as " . htmlspecialchars($ident->username, ENT_QUOTES, 'UTF-8') . ".<br>";
    echo "<a href='$newurl'>Continue</a>";
}
