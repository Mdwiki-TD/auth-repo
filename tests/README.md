# Tests

## Overview

PHPUnit-based test suite for the OAuth authentication system. Tests are run via `composer test` or `vendor/bin/phpunit`.

## Files

### `bootstrap.php` — Test Environment Setup

Configures the test environment before any tests run:

- Sets `APP_ENV=testing` to enable test-specific behavior (e.g., re-throwing database exceptions)
- Loads test-specific Defuse encryption keys (different from dev/production)
- Sets `$_SERVER['SERVER_NAME'] = 'localhost'` to simulate local environment
- Loads all application source files via `include_all.php`

**Important:** The encryption keys in this file are separate from those in `src/dev/load_env.php`. Encrypted values from one environment cannot be decrypted in another.

### `HelpsTest.php` — Encryption & Cookie Helper Tests

Tests for `src/oauth/helps.php` (`OAuth\Helps` namespace):

| Test | Coverage |
|---|---|
| `testEnCodeValueReturnsEmptyStringForEmptyInput` | Empty string handling |
| `testDeCodeValueReturnsEmptyStringForEmptyInput` | Empty string handling |
| `testDeCodeValueReturnsEmptyStringForInvalidData` | Invalid ciphertext handling |
| `testEncryptionDecryptionRoundTrip` | Full encrypt → decrypt cycle |
| `testEncryptionWithDecryptKeyType` | Using `"decrypt"` key type |
| `testGetFromCookiesReturnsEmptyForNonExistentCookie` | Missing cookie handling |
| `testGetFromCookiesReplacesPlusInUsername` | Username `+` → space replacement |
| `testSpecialCharactersEncryption` | Unicode, symbols, email addresses |

### `JwtConfigTest.php` — JWT Token Tests

Tests for `src/oauth/jwt_config.php` (`OAuth\JWT` namespace):

| Test | Coverage |
|---|---|
| `testCreateJwtReturnsNonEmptyString` | Basic token creation |
| `testVerifyJwtReturnsUsernameForValidToken` | Valid token verification |
| `testVerifyJwtReturnsErrorForEmptyToken` | Empty token handling |
| `testVerifyJwtReturnsErrorForInvalidToken` | Invalid token handling |
| `testVerifyJwtReturnsErrorForTamperedToken` | Tampered token detection |
| `testJwtTokenStructure` | 3-part JWT format |
| `testDifferentUsernamesProduceDifferentTokens` | Token uniqueness |
| `testSameUsernameCanProduceDifferentTokens` | Timestamp-based variation |
| `testVerifyJwtWithMalformedToken` | Various malformed inputs |
| `testMultipleRoundTrips` | Multiple users round-trip |

## Running Tests

```bash
# Full test suite
composer test

# PHPUnit only
vendor/bin/phpunit tests --testdox --colors=always

# Static analysis only
vendor/bin/phpstan analyse
```

## Test Coverage Gaps

The following modules have **no test coverage**:

- `access_helps.php` — Token storage/retrieval (requires database mocking)
- `mdwiki_sql.php` — Database operations (requires database mocking)
- `user_infos.php` — Session management (requires session/cookie mocking)
- `utils.php` — State building and return-to validation
- `actions/login.php` — OAuth initiation (requires HTTP mocking)
- `actions/callback.php` — OAuth callback (requires HTTP mocking)
- `actions/logout.php` — Session termination
