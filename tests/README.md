# Tests

## Overview

PHPUnit-based test suite for the OAuth authentication system. Tests are run via `composer test` or `vendor/bin/phpunit`.

## Files

### `bootstrap.php` — Test Environment Setup

Configures the test environment before any tests run:

- Sets `APP_ENV=testing` to enable test-specific behavior
- Loads test-specific Defuse encryption keys (different from dev/production)
- Sets `$_SERVER['SERVER_NAME'] = 'localhost'` to simulate local environment
- Loads all application source files via `include_all.php`

**Important:** The encryption keys in this file are separate from those in `src/dev/load_env.php`. Encrypted values from one environment cannot be decrypted in another.

### `SettingsTest.php` — Settings & Encryption Tests

Tests for `src/app/Settings.php` (`OAuth\Settings` namespace):

| Test | Coverage |
|---|---|
| `testGetInstanceReturnsSameInstance` | Singleton pattern verification |
| `testEnvironmentChecks` | `isTesting()`, `isDevelopment()`, `isProduction()` |
| `testGenerateCallbackUrl` | Absolute callback URL generation |
| `testGetKey` | Key getter for cookie and decrypt keys |
| `testEncodeAndDecodeValue` | Symmetric encryption & decryption roundtrip |
| `testEncodeAndDecodeWithNullKeyOrEmptyString` | Edge cases for encryption |
| `testSpecialCharactersEncryption` | Special characters and Unicode support |
| `testPropertyGetAndSetters` | Read-only setting enforcement |
| `testUndefinedPropertyAccessThrowsException` | Unknown setting exception handling |

### `UtilsTest.php` — Utility Function Tests

Tests for `src/app/Utils.php` (`OAuth\Utils` namespace):

| Test | Coverage |
|---|---|
| `testCreateStateReturnsEmptyArrayWhenNoGetParams` | State parameter filtering |
| `testCreateReturnToEmptyReferer` | Empty referer handling |
| `testCreateReturnToValidDomains` | Allowed host validation |
| `testCreateReturnToDisallowedDomains` | Untrusted host rejection |
| `testCreateReturnToRejectsAuthPaths` | Rejection of internal auth paths in referer |

### `CurrentUserTest.php` — User Session Tests

Tests for `src/app/CurrentUser.php` (`OAuth\User` namespace):

| Test | Coverage |
|---|---|
| `testGetInstanceReturnsSameInstance` | Singleton instance verification |
| `testInitialLoginStatus` | Login status and username resolution |
| `testAddUsernameToCookies` | Cookie setting and session update |
| `testLogout` | Session clearing and cookie removal |

### `DatabaseTest.php` — Database Abstraction Tests

Tests for `src/app/Database.php` (`OAuth\MdwikiSql` namespace):

| Test | Coverage |
|---|---|
| `testFetchQueryWhenDbNullReturnsEmptyArray` | Error handling when DB is unreachable |
| `testExecuteQueryWhenDbNullReturnsFalse` | Execution handling when DB is unreachable |
| `testDisableFullGroupByModeDoesNotThrow` | `ONLY_FULL_GROUP_BY` session mode handling |

### `ControllersTest.php` — Controller Instantiation Tests

Tests for controller classes in `src/app/controllers/` (`OAuth\Controllers` namespace):

| Test | Coverage |
|---|---|
| `testLoginControllerCanBeInstantiated` | `LoginController` instantiation |
| `testLogoutControllerCanBeInstantiated` | `LogoutController` instantiation |
| `testCallbackControllerCanBeInstantiated` | `CallbackController` instantiation |

## Running Tests

```bash
# Full test suite
composer test

# PHPUnit only
vendor/bin/phpunit tests --testdox --colors=always

# Static analysis only
vendor/bin/phpstan analyse
```
