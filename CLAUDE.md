# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a PHP-based OAuth 1.0 authentication system for MediaWiki. It provides user authentication via Wikimedia's OAuth service, storing access tokens and managing user sessions with JWT and encrypted cookies.

## Key Commands

```bash
# Install PHP dependencies
composer install

# Run local development server (requires PHP built-in server)
php -S localhost:8000

# Production deployment (via GitHub Actions)
# Push to 'main' branch triggers SSH deployment to Toolforge
```

## Architecture

### Request Flow
1. `index.php` - Entry point; routes to `view.php` (UI) or `oauth/index.php` (API actions)
2. `oauth/index.php` - Router that dispatches actions based on `?a=` parameter
3. Action files (`login.php`, `callback.php`, `logout.php`, etc.) handle specific operations

### OAuth Module (`oauth/`)

| File | Purpose |
|------|---------|
| `config.php` | Loads OAuth credentials from `~/confs/OAuthConfig.ini` |
| `login.php` | Initiates OAuth flow, redirects to Wikimedia |
| `callback.php` | Handles OAuth callback, exchanges tokens, stores access tokens |
| `logout.php` | Clears session and cookies |
| `user_infos.php` | Retrieves and exposes authenticated user info |
| `api.php` | API endpoint for external tool authentication |
| `helps.php` | Cookie encryption/decryption utilities (uses `defuse/php-encryption`) |
| `jwt_config.php` | JWT token creation/validation (uses `firebase/php-jwt`) |
| `mdwiki_sql.php` | PDO database wrapper for MySQL (Toolforge or localhost) |
| `access_helps.php` / `access_helps_new.php` | Token storage/retrieval from database (dual implementations) |

### Dependencies (via Composer)

- `mediawiki/oauthclient` - OAuth 1.0 client for MediaWiki
- `firebase/php-jwt` - JWT token generation/validation
- `defuse/php-encryption` - Symmetric encryption for cookies and stored tokens
- `phpmailer/phpmailer` - Email (currently unused)

### Configuration

Configuration is loaded from an INI file located at `$HOME/confs/OAuthConfig.ini`. Required keys:
- `agent`, `$CONSUMER_KEY`, `$CONSUMER_SECRET` - OAuth credentials
- `cookie_key`, `decrypt_key` - Defuse encryption keys (ASCII-safe format)
- `jwt_key` - Secret for JWT signing

### Database Tables

- `access_keys` - Legacy token storage (via `access_helps.php`)
- `keys_new` - Current token storage (via `access_helps_new.php`)
- `users` - User data

### Test Files

Manual test scripts in `auths_tests/` are for development use (not PHPUnit tests).

## Development Notes

- **Local vs Production**: Code checks `$_SERVER['SERVER_NAME']` to detect localhost for development-specific behavior
- **Test Mode**: Adding `?test=1` to any URL enables error reporting (should be removed in production)
- **Namespaces**: Helper classes use `OAuth\*` namespaces; entry points do not use namespaces
- **State Parameter**: OAuth flow preserves `cat`, `code`, `type`, `doit`, and `return_to` parameters through the callback

## Known Issues (from refactor.md)

See `refactor.md` for detailed technical debt analysis. Key concerns:
- Duplicate database abstraction layers (`access_helps.php` and `access_helps_new.php`)
- Global variable usage for configuration
- `u.php` contains development bypass code that should not be used in production
