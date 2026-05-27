# Actions Module

## Overview

The `actions/` directory contains the business logic handlers for each step of the OAuth authentication flow. Each file corresponds to a single user-facing action and is included by the top-level entry point files (`src/login.php`, `src/callback.php`, `src/logout.php`).

These files are **not standalone endpoints** — they are included via `include_once` from the parent entry points after the bootstrap (`include_all.php`) has loaded all dependencies.

## Files

### `login.php` — OAuth Login Initiation

Initiates the OAuth 1.0a authorization flow with Wikimedia.

**Flow:**
1. Validates that `consumerKey` and `consumerSecret` are configured
2. Creates an OAuth `Client` with the configured consumer credentials
3. Builds a callback URL with state parameters (`camp`, `cat`, `code`, `test`, `return_to`)
4. Calls `$client->initiate()` to get an authorization URL and request token
5. Stores request token in `$_SESSION`
6. Redirects user to Wikimedia's authorization page (or shows link on localhost)

**Key functions:**
- `showErrorAndExit()` — Displays a user-facing error and logs it
- `add_callback_state()` — Appends preserved state parameters to the callback URL

**Dependencies:** `OAuth\Settings\Settings`, `OAuth\Utils\create_state`, `OAuth\Utils\create_return_to`, `MediaWiki\OAuthClient\*`

---

### `callback.php` — OAuth Callback Handler

Handles the redirect back from Wikimedia after the user authorizes the application.

**Flow:**
1. Validates that `oauth_verifier` is present in the query string
2. Validates that request token exists in the session
3. Exchanges the request token + verifier for an access token via `$client->complete()`
4. Identifies the user via `$client->identify()`
5. Creates a JWT token and stores it in an encrypted cookie
6. Stores the encrypted access token/key in the database
7. Registers the user in the `users` table if not present
8. Redirects to the application with preserved state parameters

**Key functions:**
- `showErrorAndExit()` — User-facing error display (duplicated from `login.php`)

**Dependencies:** `OAuth\Settings\Settings`, `OAuth\JWT\create_jwt`, `OAuth\Helps\add_to_cookies`, `OAuth\AccessHelps\add_access_to_db`, `OAuth\AccessHelps\sql_add_user`, `OAuth\Utils\create_state`, `MediaWiki\OAuthClient\*`

**Known issues:**
- `showErrorAndExit()` does not escape HTML in the `$message` parameter (potential XSS)
- Hardcodes `/Translation_Dashboard/index.php` as the default redirect target

---

### `logout.php` — Session Termination

Clears all authentication state.

**Flow:**
1. Destroys the PHP session
2. Clears `jwt_token` and `username` cookies (sets expiry to past)
3. Redirects to the HTTP referer (if valid) or a default page

**Dependencies:** `OAuth\Settings\Settings`, `OAuth\Utils\create_return_to`

---

## Architecture Notes

- All action files depend on `include_all.php` being loaded first by their parent entry points
- Error handling follows a consistent pattern: try/catch with `error_log()` + `showErrorAndExit()`
- The `showErrorAndExit()` function is duplicated between `login.php` and `callback.php` — this should be extracted to a shared utility
- State parameters (`cat`, `code`, `camp`, `return_to`) are preserved across the OAuth flow via a combination of URL parameters and session storage
