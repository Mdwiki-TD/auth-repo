# OAuth Module

## Overview

The `oauth/` directory contains the core reusable modules for the authentication system. Each file defines a namespaced set of functions (or a class) that handles a specific concern: configuration, database access, encryption, JWT management, or user session resolution.

These modules are loaded by `src/include_all.php` and consumed by the action handlers in `src/actions/`.

## Files

### `settings.php` — Configuration Singleton

**Namespace:** `OAuth\Settings`

**Class:** `Settings` (final, singleton)

Loads all configuration from environment variables and provides read-only access via `__get()` magic method. Validates required keys in production mode.

**Properties:**
| Property | Source | Description |
|---|---|---|
| `$domain` | `$_SERVER['SERVER_NAME']` | Current hostname |
| `$ServerUrl` | Generated | Full protocol + host URL |
| `$userAgent` | Hardcoded | User-Agent string for OAuth requests |
| `$oauthUrl` | Hardcoded | Wikimedia OAuth endpoint |
| `$apiUrl` | Generated | Wikimedia API endpoint |
| `$consumerKey` | `CONSUMER_KEY` env | OAuth consumer key |
| `$consumerSecret` | `CONSUMER_SECRET` env | OAuth consumer secret |
| `$appEnv` | `APP_ENV` env | Environment mode |
| `$cookieKey` | `COOKIE_KEY` env | Defuse key for cookie encryption |
| `$decryptKey` | `DECRYPT_KEY` env | Defuse key for token encryption |
| `$jwtKey` | `JWT_KEY` env | JWT signing secret |

**Key methods:**
- `getInstance()` — Returns the singleton instance
- `is_development()` / `is_production()` / `is_testing()` — Environment checks
- `generateCallbackUrl($path)` — Builds absolute callback URL

---

### `mdwiki_sql.php` — Database Layer

**Namespace:** `OAuth\MdwikiSql`

**Class:** `Database`

A PDO wrapper for MySQL connections targeting Wikimedia Toolforge (or localhost).

**Key features:**
- Connects using `DB_HOST_TOOLS`, `DB_NAME`, `TOOL_TOOLSDB_USER`, `TOOL_TOOLSDB_PASSWORD` environment variables
- Automatically disables `ONLY_FULL_GROUP_BY` SQL mode when queries contain `GROUP BY`
- Re-throws exceptions in testing mode for proper test isolation

**Wrapper functions:**
- `execute_query($sql, $params)` — Execute a query (INSERT/UPDATE/DELETE/SELECT)
- `fetch_query($sql, $params)` — Fetch results from a SELECT query

**Known issues:**
- Creates a new `Database` (and thus PDO connection) per function call
- Echoes raw SQL errors to the user in non-testing mode
- `$table_name` parameter is accepted but unused

---

### `access_helps.php` — Token Storage

**Namespace:** `OAuth\AccessHelps`

Manages encrypted storage and retrieval of OAuth access tokens in the `access_keys` database table.

**Functions:**
| Function | Description |
|---|---|
| `add_access_to_db($user, $key, $secret)` | Upserts encrypted access token for a user |
| `get_access_from_db($user)` | Retrieves and decrypts access token (looks up by name or hash) |
| `del_access_from_db($user)` | Deletes access token for a user |
| `sql_add_user($user_name)` | Inserts user into `users` table if not exists |

**Encryption:** Access keys/secrets are encrypted with the `decrypt` key type (separate from cookie encryption key) using `Defuse\Crypto\Crypto`.

---

### `helps.php` — Encryption & Cookie Helpers

**Namespace:** `OAuth\Helps`

Provides symmetric encryption/decryption and cookie management utilities.

**Functions:**
| Function | Description |
|---|---|
| `encode_value($value, $key_type)` | Encrypts a value using Defuse (`"cookie"` or `"decrypt"` key) |
| `decode_value($value, $key_type)` | Decrypts a value using Defuse |
| `add_to_cookies($key, $value, $age)` | Sets an encrypted cookie (2-year default expiry) |
| `get_from_cookies($key)` | Reads and decrypts a cookie value |

**Key details:**
- Two encryption key types: `"cookie"` (for browser cookies) and `"decrypt"` (for database-stored tokens)
- Cookie security: `secure` and `httponly` flags are set based on whether the domain is localhost
- Username cookies have `+` replaced with space on read

---

### `jwt_config.php` — JWT Token Management

**Namespace:** `OAuth\JWT`

Handles JWT creation and verification using `firebase/php-jwt` with HS256 algorithm.

**Functions:**
| Function | Description |
|---|---|
| `create_jwt($username)` | Creates a JWT with 1-hour expiry, issuer set to domain |
| `verify_jwt($token)` | Verifies a JWT and returns `[$username, $error]` |

**JWT payload:**
```json
{
  "iss": "mdwiki.toolforge.org",
  "iat": 1234567890,
  "exp": 1234571490,
  "username": "ExampleUser"
}
```

---

### `user_infos.php` — User Session Resolution

Resolves the current authenticated user from cookies and database. This file is included as a side-effect (it defines the `global_username` constant).

**Resolution logic:**
1. Starts the PHP session (with domain-specific cookie params on non-localhost)
2. Reads `username` from encrypted cookie
3. On localhost: reads from `$_SESSION['username']` instead
4. On production: validates that the user has stored access keys in the database
5. If no access keys found, clears the cookie and shows an error
6. Defines `global_username` constant with the resolved username

**Known issues:**
- Uses `define()` to export username — pollutes global namespace
- Mixes session setup, cookie validation, DB lookup, and constant definition in one file

---

### `utils.php` — Utility Functions

**Namespace:** `OAuth\Utils`

General-purpose helpers for state management and HTML output.

**Functions:**
| Function | Description |
|---|---|
| `create_state($keys)` | Builds an associative array from GET parameters (sanitized) |
| `ba_alert($text)` | Renders a Bootstrap alert-danger HTML block |
| `create_return_to($referer)` | Validates and returns a safe redirect URL |

**Validation in `create_return_to()`:**
- Only allows referers from `mdwiki.toolforge.org` or `localhost`
- Rejects referers whose path contains `/auth/`

**Known issues:**
- Uses deprecated `FILTER_SANITIZE_STRING` (removed in PHP 8.2)

---

### `index.php` — Placeholder

Empty file. No functionality.

---

## Dependency Graph

```
settings.php  (standalone — depends on env vars)
     │
     ├── helps.php          (uses Settings for encryption keys)
     ├── jwt_config.php     (uses Settings for JWT key)
     ├── mdwiki_sql.php     (uses env vars directly)
     │       │
     │       └── access_helps.php  (uses mdwiki_sql + helps)
     │
     └── user_infos.php     (uses helps + access_helps + settings + utils)

utils.php     (standalone — no internal dependencies)
```
