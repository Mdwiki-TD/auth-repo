# Project Audit Report

**Project:** MediaWiki OAuth Authentication System (`auth_repo`)
**Date:** 2026-05-27
**Auditor:** Senior Technical Audit
**Scope:** Full codebase review of `src/` directory, tests, configuration, and deployment infrastructure

---

## Executive Summary

This system is a standalone PHP OAuth 1.0a authentication service that delegates user login to Wikimedia's OAuth endpoint (meta.wikimedia.org). It manages the complete authentication lifecycle: initiating authorization, exchanging tokens on callback, encrypting and persisting access tokens in MySQL, issuing JWT session tokens, and managing encrypted browser cookies. It is designed to serve as a shared authentication layer for multiple tools under the `mdwiki.toolforge.org` domain.

**Technologies:** PHP 8.0+, MySQL (PDO), `mediawiki/oauthclient` ^1.2, `firebase/php-jwt` 7.0.0, `defuse/php-encryption` ^2.4, PHPUnit 10, PHPStan level 5, GitHub Actions CI/CD.

**Architecture:** Procedural PHP with namespaced function modules. Entry points route to action handlers via `include_once` chains. Configuration is centralized in a singleton (`Settings`). No MVC framework — the system is purpose-built for a single OAuth use case.

**Size:** ~21 PHP source files across 4 directories (`src/`, `src/actions/`, `src/oauth/`, `src/dev/`), 3 test files, 3 CI workflow files.

---

## Project Health Assessment

| Dimension | Rating | Summary |
|---|---|---|
| **Overall Code Quality** | 6/10 | Functional, readable, but inconsistent formatting and sparse documentation |
| **Maintainability** | 5.5/10 | Include-chain architecture, global state, no autoloading for app code |
| **Scalability** | 5/10 | New DB connection per query, no connection pooling, 2-year cookie expiry |
| **Security Posture** | 6/10 | Good encryption choices and parameterized SQL, but XSS vector and error exposure |
| **Production Readiness** | 6/10 | Works in production; critical issues must be fixed before wider deployment |

---

## Cross-Project Analysis

This is a single-project repository with three functional layers. The analysis covers patterns across those layers.

### Shared Architectural Patterns

1. **Namespaced function modules** — All core utilities (`OAuth\Helps\*`, `OAuth\JWT\*`, `OAuth\AccessHelps\*`, `OAuth\MdwikiSql\*`) follow the same pattern: a namespace declaration, `use` imports, and file-scoped functions. No classes except `Settings` (singleton) and `Database` (PDO wrapper).

2. **Entry point → action delegation** — `src/login.php`, `src/callback.php`, and `src/logout.php` each include `include_all.php` then delegate to `src/actions/*.php`. This two-tier entry pattern is consistent but adds indirection without clear benefit.

3. **Environment-driven configuration** — All secrets and environment-specific behavior are controlled via environment variables, read through either `Settings::envVar()` or `Database::envVar()`. The `envVar()` method is duplicated between these two classes.

### Repeated Weaknesses

| Weakness | Occurrences |
|---|---|
| `envVar()` method duplicated | `Settings` class, `Database` class |
| `showErrorAndExit()` function duplicated | `actions/login.php`, `actions/callback.php` |
| Error messages echoed to user (not logged) | `mdwiki_sql.php` (2 places), `user_infos.php` (1 place) |
| New DB connection per function call | `execute_query()`, `fetch_query()` |
| `display_errors` enabled via user input | `index.php`, `mdwiki_sql.php` |

### Common Technical Debt

- No Composer autoloading for application code (PSR-4 not configured in `composer.json`)
- Global state via `define('global_username', ...)` in `user_infos.php`
- Unused `$table_name` parameter in `execute_query()` and `fetch_query()`
- Mixed semicolon-after-brace style (`};` vs `}`)
- PHPDoc coverage only on `Settings` class and two functions in `actions/login.php`

### Dependency Issues

- `firebase/php-jwt` is pinned to exact version `7.0.0` — should use `^7.0` for patch updates
- `FILTER_SANITIZE_STRING` in `utils.php` is deprecated in PHP 8.1, removed in PHP 8.2
- No dependency injection — `Settings::getInstance()` is called directly from all modules

### Integration Concerns

- Hardcoded redirect to `/Translation_Dashboard/index.php` couples this auth service to a specific application
- Cookie domain is derived from `$_SERVER['SERVER_NAME']` — may break behind reverse proxies if not configured
- The deploy workflow references an external script (`shs/update_auth.sh`) not contained in this repository

---

## Critical Findings

### HIGH: XSS in `callback.php`

**File:** `src/actions/callback.php:39-44`

```php
echo "<div style='border:1px solid red; padding:10px; background:#ffe6e6; color:#900;'>";
echo $message;  // NOT ESCAPED
```

The `showErrorAndExit()` function outputs `$message` directly without `htmlspecialchars()`. The identical function in `actions/login.php` correctly escapes its output. If any error message incorporates user-controlled data (e.g., from query parameters), this is a reflected XSS vulnerability.

**Remediation:** Apply `htmlspecialchars($message, ENT_QUOTES, 'UTF-8')` to match `login.php`'s implementation.

---

### HIGH: `?test=1` Enables Error Display in Production

**File:** `src/index.php:2-6`, `src/oauth/mdwiki_sql.php:10-14`

```php
if (isset($_REQUEST['test'])) {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}
```

Any user can append `?test=1` to any URL and enable full PHP error display. This leaks stack traces, file paths, database credentials in connection strings, and internal error messages. The same pattern exists in `mdwiki_sql.php`.

**Remediation:** Gate behind `APP_ENV === 'development'` or remove entirely.

---

### MEDIUM: SQL Errors Echoed to Users

**File:** `src/oauth/mdwiki_sql.php:111, 136`

```php
echo "sql error:" . $e->getMessage() . "<br>" . $sql_query;
echo "SQL Error:" . $e->getMessage() . "<br>" . $sql_query;
```

Raw PDO exception messages and SQL query text are output to the browser. This reveals table names, column names, and query structure to potential attackers.

**Remediation:** Replace with `error_log()` and return a generic error to the user.

---

### MEDIUM: Hardcoded Development Credentials in Repository

**File:** `src/dev/load_env.php:10-11`

```php
putenv('TOOL_TOOLSDB_USER=root');
putenv('TOOL_TOOLSDB_PASSWORD=root11');
```

Real database credentials and Defuse encryption keys are committed to the repository. While `.gitignore` excludes `/src/dev`, the directory currently exists in the repo history.

**Remediation:** Replace with placeholder values. Use `.env` file for local development.

---

### MEDIUM: Open Redirect Edge Case

**File:** `src/actions/callback.php:147-148`

```php
if ((strpos($return_to, '/auth/') !== false) || (strpos($return_to, $server_url) === false)) {
    $return_to = "";
}
```

`strpos()` checks if the server URL *exists anywhere* in the return URL, not if the URL *starts with* it. A crafted URL like `https://evil.com/?next=https://mdwiki.toolforge.org/` would pass this check.

**Remediation:** Use `str_starts_with()` (PHP 8.0+) or `strncmp()` for prefix validation.

---

### LOW: Deprecated `FILTER_SANITIZE_STRING`

**File:** `src/oauth/utils.php:14`

```php
$da = filter_input(INPUT_GET, $key, FILTER_SANITIZE_STRING);
```

This filter was deprecated in PHP 8.1 and removed in PHP 8.2. The code will fatal error on PHP 8.2+.

**Remediation:** Replace with `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')` or `FILTER_SANITIZE_FULL_SPECIAL_CHARS`.

---

## Strengths

### 1. Sound Encryption Architecture

The system uses `defuse/php-encryption` — a well-audited, high-level symmetric encryption library — for both cookie values and stored database tokens. Two separate keys are used (`cookieKey` for browser cookies, `decryptKey` for database tokens), providing key isolation. Keys are loaded from environment variables and never hardcoded in production paths.

### 2. Well-Implemented Settings Singleton

`OAuth\Settings\Settings` is a properly designed singleton: immutable from outside (read-only via `__set()` override), protected against cloning and unserialization, and validates required keys at startup in production mode. The `__get()` magic method provides clean property access without exposing mutation.

### 3. Exemplary OAuth Error Handling

Both `actions/login.php` and `actions/callback.php` wrap every step of the OAuth flow in try/catch blocks with specific, user-friendly error messages and detailed server-side logging. This is the correct pattern for OAuth integrations where failures at any step need clear communication.

### 4. Parameterized SQL Queries

All database operations use PDO prepared statements with parameter binding. There is no string concatenation of user input into SQL queries. This eliminates SQL injection risk across the entire codebase.

### 5. Environment-Aware Behavior

The system cleanly separates development, production, and testing modes. The localhost OAuth bypass (`dev_login.php`) eliminates the OAuth round-trip during local development, significantly improving developer experience.

### 6. Return-To URL Validation

`create_return_to()` validates the HTTP referer against an allowlist of trusted domains and rejects any URL containing `/auth/`, preventing open redirect attacks through the referer header.

### 7. CI Pipeline

GitHub Actions workflows for PHPUnit, PHPStan, and automated deployment provide continuous quality assurance. PHPStan at level 5 catches type errors and undefined behaviors before they reach production.

---

## Improvement Roadmap

### Immediate Fixes (This Sprint)

| # | Issue | Effort | Risk |
|---|---|---|---|
| 1 | Fix XSS in `callback.php` `showErrorAndExit()` | 5 min | High |
| 2 | Gate `?test=1` behind `APP_ENV === 'development'` | 10 min | High |
| 3 | Replace SQL error `echo` with `error_log()` in `mdwiki_sql.php` | 15 min | Medium |
| 4 | Add `SameSite=Lax` to `add_to_cookies()` in `helps.php` | 5 min | Medium |
| 5 | Replace `FILTER_SANITIZE_STRING` in `utils.php` | 5 min | Medium |
| 6 | Fix `return_to` validation to use `str_starts_with()` | 5 min | Medium |

### Short-Term Improvements (Next 2 Weeks)

| # | Improvement | Effort |
|---|---|---|
| 1 | Extract `showErrorAndExit()` to `oauth/utils.php` (eliminate duplication) | 30 min |
| 2 | Extract `envVar()` to a shared trait or utility (eliminate duplication) | 30 min |
| 3 | Add Composer PSR-4 autoloading for `OAuth\*` namespaces | 1 hour |
| 4 | Replace `define('global_username')` with a function return value | 1 hour |
| 5 | Implement database connection reuse (static instance or dependency injection) | 2 hours |
| 6 | Replace hardcoded `/Translation_Dashboard/index.php` with configurable default | 30 min |
| 7 | Add tests for `utils.php` (state building, return-to validation) | 1 hour |
| 8 | Add tests for `access_helps.php` (mock database layer) | 2 hours |
| 9 | Replace placeholder credentials in `dev/load_env.php` | 15 min |

### Long-Term Strategic Refactoring (1-3 Months)

| # | Initiative | Effort | Impact |
|---|---|---|---|
| 1 | Introduce PSR-7/PSR-15 HTTP abstraction | 2-3 days | High |
| 2 | Implement dependency injection (PSR-11 container) | 2-3 days | High |
| 3 | Add JWT refresh token mechanism (replace 2-year cookies) | 1-2 days | High |
| 4 | Add structured logging (PSR-3 / Monolog) | 1 day | Medium |
| 5 | Add CSRF token validation to login initiation | 0.5 day | Medium |
| 6 | Implement rate limiting on OAuth endpoints | 1 day | Medium |
| 7 | Add Content-Security-Policy headers to HTML responses | 0.5 day | Medium |
| 8 | Create integration test suite for full OAuth flow | 2 days | Medium |
| 9 | Validate JWT `iss` claim during verification | 0.5 day | Low |

### Security Hardening Priorities

1. **Fix XSS** — Immediate. No deployment without this fix.
2. **Remove `?test=1`** — Immediate. Information disclosure in production.
3. **Sanitize SQL error output** — Immediate. Schema leakage.
4. **Add `SameSite` cookie attribute** — This sprint. CSRF mitigation.
5. **Fix open redirect edge case** — This sprint. Phishing vector.
6. **Validate JWT `iss` claim** — Short-term. Token audience binding.
7. **Add CSRF protection** — Short-term. Login state machine protection.
8. **Add rate limiting** — Long-term. Brute force mitigation.

### DevOps and Testing Recommendations

1. **Add pre-deployment smoke tests** — Verify `.env` completeness and database connectivity before deploying.
2. **Include deployment script in repo** — `shs/update_auth.sh` is referenced but not version-controlled.
3. **Add PHP 8.2 to CI matrix** — Catch deprecated/removed features before they break production.
4. **Add code coverage reporting** — Identify untested paths in the OAuth flow.
5. **Separate test and production encryption keys** — Already done, but document the key generation process.
6. **Add health check endpoint** — Enable monitoring of database connectivity and configuration validity.

---

## Final Evaluation

| Metric | Value |
|---|---|
| **Overall Project Score** | **6.5 / 10** |
| **Risk Level** | **Medium-High** (XSS and error display issues are exploitable) |
| **Technical Debt Level** | **Moderate** (duplicated code, global state, include-chain architecture) |
| **Production Readiness** | **Conditional** — functional but requires immediate security fixes |
| **Estimated Effort to Production-Ready** | **1-2 days** for critical fixes; **2-3 weeks** for short-term improvements |

### Recommended Next Steps

1. **Today:** Fix the 3 HIGH/MEDIUM security issues (XSS, `?test=1`, SQL error echo).
2. **This week:** Complete all 6 immediate fixes from the roadmap.
3. **Next 2 weeks:** Implement short-term improvements, focusing on test coverage and code deduplication.
4. **Next quarter:** Begin long-term refactoring with HTTP abstraction and dependency injection.

The codebase demonstrates competent engineering — the encryption architecture, OAuth error handling, and SQL parameterization are all well-done. The issues are primarily in the "last mile" of security hardening and code organization, not in fundamental design flaws. With targeted fixes, this system can reach production-grade quality.
