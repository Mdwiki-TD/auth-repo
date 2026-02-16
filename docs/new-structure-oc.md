# Modern PHP Application Directory Structure Proposal

## Executive Summary

This proposal outlines a modern, scalable directory structure for the MediaWiki OAuth authentication system. The current flat architecture presents significant technical debt, security vulnerabilities, and maintainability challenges. The proposed structure follows industry best practices, implementing layered architecture principles that ensure separation of concerns, testability, and deployment efficiency.

---

## Current State Analysis

### Existing Structure

```
auth_repo/
├── index.php              # Entry point with routing logic
├── view.php               # UI rendering mixed with business logic
├── header.php             # Shared header with hardcoded paths
├── vendor_load.php        # Dependency loader
├── oauth/                 # OAuth module (flat structure)
│   ├── index.php          # Action router
│   ├── config.php         # Global configuration with hardcoded values
│   ├── login.php          # OAuth initiation (HTML + logic mixed)
│   ├── callback.php       # OAuth callback (149 LOC, complexity 28)
│   ├── logout.php         # Session cleanup
│   ├── user_infos.php     # User info retrieval
│   ├── api.php            # API endpoint
│   ├── edit.php           # Edit functionality
│   ├── send_edit.php      # Edit sender
│   ├── get_user.php       # User retrieval
│   ├── helps.php          # Cookie/encryption utilities
│   ├── jwt_config.php     # JWT functions with globals
│   ├── mdwiki_sql.php     # Database wrapper (220 LOC)
│   ├── access_helps.php   # OLD token storage (deprecated)
│   ├── access_helps_new.php # NEW token storage (duplicate)
│   └── u.php              # DEVELOPMENT BACKDOOR (CRITICAL)
├── auths_tests/           # Manual test scripts (not PHPUnit)
│   ├── _test_.php
│   ├── _test_2.php
│   └── jwt.php
├── vendor/                # Composer dependencies
└── composer.json
```

### Critical Issues Identified

1. **Security Risks**
   - `oauth/u.php` contains a hardcoded user bypass for localhost
   - `?test=1` URL parameter enables full error reporting in production
   - Hardcoded database credentials (`root`/`root11`)
   - SQL queries echoed to users on errors

2. **Architecture Anti-Patterns**
   - All concerns mixed in single files (UI, business logic, database)
   - Global variable pollution (10+ globals)
   - Duplicate database abstraction layers (access_helps.php + access_helps_new.php)
   - Manual dependency management (no PSR-4 autoloading)

3. **Maintainability Problems**
   - No separation of public assets from application code
   - Windows-specific paths hardcoded (`I:/mdwiki/mdwiki`)
   - Inconsistent error handling (4 different patterns)
   - Zero test coverage with PHPUnit

4. **Deployment Challenges**
   - Configuration baked into code
   - No environment-specific config management
   - Entire codebase exposed in web root

---

## Proposed Directory Structure

```
auth_repo/
│
├── bin/                           # Executable scripts
│   └── generate-defuse-key        # Key generation utility
│
├── config/                        # Configuration layer
│   ├── config.php                 # Main configuration container
│   ├── config.dev.php             # Development overrides
│   ├── config.prod.php            # Production overrides
│   ├── routes.php                 # Route definitions
│   ├── dependencies.php           # DI container configuration
│   └── middleware.php             # Middleware stack config
│
├── docs/                          # Documentation
│   ├── architecture/              # Architecture decision records
│   ├── api/                       # API documentation
│   └── deployment/                # Deployment guides
│
├── public/                        # Web-accessible directory (document root)
│   ├── index.php                  # Single entry point (front controller)
│   ├── .htaccess                  # Apache URL rewriting rules
│   ├── robots.txt                 # Search engine directives
│   ├── favicon.ico
│   └── assets/                    # Static assets
│       ├── css/
│       ├── js/
│       └── images/
│
├── src/                           # Application source code (PSR-4: App\)
│   ├── Domain/                    # Domain layer (business logic)
│   │   ├── Entity/                # Domain entities
│   │   │   ├── User.php
│   │   │   ├── AccessToken.php
│   │   │   └── Session.php
│   │   ├── ValueObject/           # Immutable value objects
│   │   │   ├── Username.php
│   │   │   ├── TokenKey.php
│   │   │   └── EncryptedValue.php
│   │   ├── Repository/            # Repository interfaces
│   │   │   ├── AccessTokenRepositoryInterface.php
│   │   │   └── UserRepositoryInterface.php
│   │   └── Exception/             # Domain exceptions
│   │       ├── InvalidTokenException.php
│   │       ├── AuthenticationException.php
│   │       └── EncryptionException.php
│   │
│   ├── Application/               # Application layer (use cases)
│   │   ├── Service/               # Application services
│   │   │   ├── AuthenticationService.php
│   │   │   ├── TokenManagementService.php
│   │   │   ├── CookieService.php
│   │   │   ├── JwtService.php
│   │   │   └── CryptoService.php
│   │   ├── Dto/                   # Data transfer objects
│   │   │   ├── AuthRequest.php
│   │   │   ├── AuthResult.php
│   │   │   └── TokenPayload.php
│   │   ├── Command/               # Command handlers (CQRS)
│   │   │   ├── InitiateOAuthCommand.php
│   │   │   ├── HandleCallbackCommand.php
│   │   │   └── LogoutCommand.php
│   │   └── Event/                 # Domain events
│   │       ├── UserAuthenticated.php
│   │       └── TokenRefreshed.php
│   │
│   ├── Infrastructure/            # Infrastructure layer
│   │   ├── Persistence/           # Database implementations
│   │   │   ├── DatabaseConnection.php
│   │   │   ├── PdoAccessTokenRepository.php
│   │   │   └── PdoUserRepository.php
│   │   ├── Security/              # Security implementations
│   │   │   ├── DefuseEncryption.php
│   │   │   └── FirebaseJwtProvider.php
│   │   ├── Http/                  # HTTP clients
│   │   │   └── MediaWikiOAuthClient.php
│   │   └── Logging/               # Logging infrastructure
│   │       └── PsrLogger.php
│   │
│   └── Presentation/              # Presentation layer
│       ├── Controller/            # HTTP controllers
│       │   ├── LoginController.php
│       │   ├── CallbackController.php
│       │   ├── LogoutController.php
│       │   ├── UserInfoController.php
│       │   └── ApiController.php
│       ├── Middleware/            # HTTP middleware
│       │   ├── AuthenticationMiddleware.php
│       │   ├── CsrfProtectionMiddleware.php
│       │   ├── ErrorHandlingMiddleware.php
│       │   ├── LoggingMiddleware.php
│       │   └── SecurityHeadersMiddleware.php
│       ├── Router/                # Routing components
│       │   ├── Router.php
│       │   └── Route.php
│       └── View/                  # View layer (if templates needed)
│           └── JsonResponse.php
│
├── templates/                     # Twig templates (if server-side rendering)
│   ├── base.html.twig
│   ├── login.html.twig
│   ├── error.html.twig
│   └── partials/
│       └── _flash_messages.html.twig
│
├── tests/                         # Test suites
│   ├── Unit/                      # Unit tests (isolated, fast)
│   │   ├── Domain/
│   │   │   ├── Entity/
│   │   │   └── ValueObject/
│   │   └── Application/
│   │       └── Service/
│   ├── Integration/               # Integration tests (with DB, external APIs)
│   │   ├── Persistence/
│   │   └── Infrastructure/
│   ├── Functional/                # HTTP-level tests (end-to-end)
│   │   └── Controller/
│   ├── Fixture/                   # Test data fixtures
│   │   ├── users.yml
│   │   └── tokens.yml
│   └── bootstrap.php              # Test bootstrap
│
├── var/                           # Variable/runtime data (gitignored)
│   ├── cache/                     # Application cache
│   ├── logs/                      # Application logs
│   └── sessions/                  # File-based sessions (if used)
│
├── .env                           # Environment variables (gitignored)
├── .env.example                   # Environment template
├── .env.test                      # Test environment
├── .gitignore
├── composer.json                  # PSR-4 autoloading: "App\\": "src/"
├── composer.lock
├── phpunit.xml                    # PHPUnit configuration
├── phpcs.xml                      # PHP_CodeSniffer rules
├── phpstan.neon                   # PHPStan static analysis
├── Makefile                       # Common development tasks
└── README.md
```

---

## Layered Architecture Rationale

### 1. Domain Layer (`src/Domain/`)

**Purpose**: Contains the core business logic, independent of frameworks and infrastructure.

**Contents**:
- **Entities**: Objects with identity (User, AccessToken) that encapsulate business rules
- **Value Objects**: Immutable objects representing concepts (Username, EncryptedValue)
- **Repository Interfaces**: Define contracts for data access without implementation details
- **Domain Exceptions**: Business rule violations as explicit exception types

**Rationale**:
- **Framework Independence**: Domain logic can be tested without HTTP requests, databases, or external services
- **Business Focus**: Developers can reason about business rules without infrastructure distractions
- **Reusability**: Domain models can be shared across different interfaces (web, CLI, API)
- **Testability**: Pure domain logic is easily unit tested with no mocks required

**Example Transformation**:
```php
// BEFORE: Business logic scattered in callback.php
$user = $_SESSION['username'];
add_access_to_dbs($user, $key, $secret);

// AFTER: Clear domain entity with encapsulated logic
$user = User::fromSession($session);
$token = AccessToken::create($key, $secret);
$user->associateToken($token);
$repository->save($user);
```

### 2. Application Layer (`src/Application/`)

**Purpose**: Orchestrates use cases by coordinating domain objects and infrastructure services.

**Contents**:
- **Services**: Application services that coordinate workflows (AuthenticationService)
- **DTOs**: Data structures for crossing layer boundaries (AuthRequest, AuthResult)
- **Commands**: Encapsulated use cases following CQRS pattern
- **Events**: Domain events for decoupled, event-driven workflows

**Rationale**:
- **Use Case Clarity**: Each service represents a specific application feature
- **Transaction Boundaries**: Services define where transactions begin and end
- **Security Enforcement**: Authentication and authorization checked at layer boundaries
- **Cross-Cutting Concerns**: Logging, validation, and caching applied consistently

**Example**:
```php
class AuthenticationService {
    public function initiateOAuth(array $state): string {
        // Coordinate OAuth client, state storage, URL generation
    }
    
    public function handleCallback(string $verifier, string $token): AuthResult {
        // 1. Exchange verifier for access token
        // 2. Retrieve user info from OAuth provider
        // 3. Store token in repository
        // 4. Generate JWT and set cookies
        // 5. Return structured result
    }
}
```

### 3. Infrastructure Layer (`src/Infrastructure/`)

**Purpose**: Implements technical capabilities and external integrations.

**Contents**:
- **Persistence**: Database implementations of repository interfaces (PDO, Redis)
- **Security**: Concrete encryption and JWT implementations
- **Http**: External API clients (MediaWiki OAuth)
- **Logging**: PSR-3 logger implementations

**Rationale**:
- **Dependency Inversion**: Infrastructure depends on domain, not vice versa
- **Swapability**: Replace MySQL with PostgreSQL without touching business logic
- **Framework Isolation**: External libraries wrapped in domain-friendly interfaces
- **Testing**: Infrastructure can be mocked or replaced with fakes for testing

**Example**:
```php
// Domain defines the contract
interface AccessTokenRepositoryInterface {
    public function save(AccessToken $token): void;
    public function findByUsername(Username $username): ?AccessToken;
}

// Infrastructure provides implementation
class PdoAccessTokenRepository implements AccessTokenRepositoryInterface {
    public function __construct(private PDO $connection) {}
    // PDO-specific implementation
}
```

### 4. Presentation Layer (`src/Presentation/`)

**Purpose**: Handles HTTP concerns and translates between HTTP and application layers.

**Contents**:
- **Controllers**: Handle HTTP requests, delegate to services, return responses
- **Middleware**: Cross-cutting HTTP concerns (auth, CSRF, logging, security headers)
- **Router**: Maps URLs to controllers
- **View**: Response formatting (JSON, HTML templates)

**Rationale**:
- **Single Responsibility**: Controllers only handle HTTP, not business logic
- **Reusable Middleware**: Authentication, logging applied consistently across routes
- **Testable Controllers**: Can be unit tested with PSR-7 request/response objects
- **API-First**: Controllers return structured data; formatting handled separately

**Example**:
```php
class CallbackController {
    public function __construct(
        private AuthenticationService $authService,
        private LoggerInterface $logger
    ) {}
    
    public function handle(ServerRequestInterface $request): ResponseInterface {
        try {
            $result = $this->authService->handleCallback(
                $request->getQueryParams()['oauth_verifier'],
                $request->getQueryParams()['oauth_token']
            );
            return new JsonResponse(['success' => true, 'redirect' => $result->redirectUrl]);
        } catch (AuthenticationException $e) {
            $this->logger->error('OAuth callback failed', ['error' => $e->getMessage()]);
            return new JsonResponse(['error' => 'Authentication failed'], 401);
        }
    }
}
```

---

## Key Organizational Decisions

### 1. Public Directory Separation

**Decision**: All web-accessible files contained in `public/`, with `index.php` as the only entry point.

**Current Problem**: All PHP files are web-accessible, exposing implementation details and creating security risks.

**Benefits**:
- **Security**: Application code outside document root cannot be accessed directly
- **Deployment**: Web server configuration simplified (point to `public/`)
- **Clean URLs**: URL rewriting in one place (`.htaccess` in `public/`)
- **Asset Management**: Static assets organized and cacheable separately

**Web Server Configuration**:
```apache
# Apache
DocumentRoot /var/www/auth_repo/public
<Directory /var/www/auth_repo/public>
    AllowOverride All
</Directory>

# Nginx
root /var/www/auth_repo/public;
location / {
    try_files $uri $uri/ /index.php?$query_string;
}
```

### 2. PSR-4 Autoloading

**Decision**: Use PSR-4 autoloading with `App\` namespace mapped to `src/`.

**Current Problem**: Manual `require_once` statements throughout codebase.

**Configuration**:
```json
{
    "autoload": {
        "psr-4": {
            "App\\": "src/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "App\\Tests\\": "tests/"
        }
    }
}
```

**Benefits**:
- **No Manual Includes**: Classes loaded automatically by Composer
- **Namespace Consistency**: Class names match directory structure
- **IDE Support**: Better autocompletion and navigation
- **Refactoring Safety**: Renaming classes updates all references

### 3. Environment-Based Configuration

**Decision**: Configuration loaded from environment variables using `vlucas/phpdotenv`.

**Current Problem**: Hardcoded paths, credentials, and environment-specific logic in source code.

**Implementation**:
```php
// config/config.php
class Config {
    public function __construct(array $env) {
        $this->oauthUrl = $env['OAUTH_URL'];
        $this->CONSUMER_KEY = $env['OAUTH_CONSUMER_KEY'];
        $this->CONSUMER_SECRET = $env['OAUTH_CONSUMER_SECRET'];
        $this->jwtSecret = $env['JWT_SECRET'];
        $this->cookieKey = Key::loadFromAsciiSafeString($env['COOKIE_KEY']);
        $this->database = [
            'host' => $env['DB_HOST'],
            'name' => $env['DB_NAME'],
            'user' => $env['DB_USER'],
            'pass' => $env['DB_PASS'],
        ];
    }
}
```

**Benefits**:
- **12-Factor Compliance**: Configuration in environment, not code
- **Security**: Secrets not committed to version control
- **Portability**: Same code runs in dev, staging, and production
- **Cloud Native**: Compatible with Docker, Kubernetes, and cloud platforms

### 4. Dependency Injection Container

**Decision**: Use a DI container (PHP-DI or Symfony DI) configured in `config/dependencies.php`.

**Current Problem**: Manual object creation and global state.

**Example Configuration**:
```php
// config/dependencies.php
use DI\ContainerBuilder;

$builder = new ContainerBuilder();
$builder->addDefinitions([
    // Configuration
    Config::class => function() {
        return new Config($_ENV);
    },
    
    // Database
    PDO::class => function(Config $config) {
        return new PDO(
            "mysql:host={$config->database['host']};dbname={$config->database['name']}",
            $config->database['user'],
            $config->database['pass']
        );
    },
    
    // Repositories
    AccessTokenRepositoryInterface::class => \DI\get(PdoAccessTokenRepository::class),
    
    // Services
    AuthenticationService::class => \DI\autowire(),
]);

return $builder->build();
```

**Benefits**:
- **Loose Coupling**: Components depend on interfaces, not concrete classes
- **Testability**: Easy to inject mocks in tests
- **Lifecycle Management**: Container manages object creation and destruction
- **Configuration Centralization**: Wiring defined in one place

### 5. Test Organization

**Decision**: Tests organized by type (Unit, Integration, Functional) mirroring source structure.

**Current Problem**: Manual test scripts without framework or structure.

**Structure**:
```
tests/
├── Unit/                    # Fast, isolated tests (no DB, no I/O)
│   ├── Domain/Entity/
│   │   └── UserTest.php
│   └── Application/Service/
│       └── AuthenticationServiceTest.php
├── Integration/             # Tests with real database
│   └── Persistence/
│       └── PdoAccessTokenRepositoryTest.php
└── Functional/              # HTTP-level tests
    └── Controller/
        └── LoginControllerTest.php
```

**Benefits**:
- **Test Pyramid**: More unit tests, fewer slow integration tests
- **Clear Intent**: Test type indicates scope and dependencies
- **Parallel Execution**: Unit tests can run in parallel for speed
- **CI/CD Integration**: PHPUnit configuration supports CI pipelines

### 6. Middleware Stack

**Decision**: Implement PSR-15 middleware for cross-cutting concerns.

**Current Problem**: Authentication, logging, and error handling scattered in individual files.

**Middleware Pipeline**:
```php
// config/middleware.php
return [
    SecurityHeadersMiddleware::class,    // Add security headers to all responses
    ErrorHandlingMiddleware::class,      // Convert exceptions to JSON responses
    LoggingMiddleware::class,            // Log all requests
    AuthenticationMiddleware::class,     // Validate JWT on protected routes
    CsrfProtectionMiddleware::class,     // Validate CSRF tokens on state-changing requests
];
```

**Benefits**:
- **Reusability**: Middleware applied consistently across routes
- **Composability**: Add/remove middleware without changing controllers
- **Testability**: Middleware tested independently
- **Standards Compliance**: PSR-15 compatible with many frameworks

---

## Migration Strategy

### Phase 1: Foundation (Week 1)

**Goal**: Establish new structure and configuration management

**Tasks**:
1. Create new directory structure
2. Setup PSR-4 autoloading in composer.json
3. Install and configure `vlucas/phpdotenv`
4. Create environment configuration files (.env.example)
5. Move entry point to `public/index.php`
6. Configure web server to use `public/` as document root

**Files to Create**:
- `public/index.php` (new entry point)
- `config/config.php` (configuration container)
- `config/dependencies.php` (DI container)
- `.env.example`
- Updated `composer.json` with PSR-4 autoloading

### Phase 2: Domain Layer (Week 2)

**Goal**: Extract domain entities and value objects

**Tasks**:
1. Create `src/Domain/Entity/` with User and AccessToken
2. Create `src/Domain/ValueObject/` with Username, TokenKey
3. Define repository interfaces
4. Create domain exceptions
5. Write unit tests for domain objects

**Files to Create**:
- `src/Domain/Entity/User.php`
- `src/Domain/Entity/AccessToken.php`
- `src/Domain/ValueObject/Username.php`
- `src/Domain/Repository/AccessTokenRepositoryInterface.php`
- `tests/Unit/Domain/Entity/UserTest.php`

### Phase 3: Infrastructure Layer (Week 3)

**Goal**: Implement repository and security infrastructure

**Tasks**:
1. Create `PdoAccessTokenRepository` implementing domain interface
2. Unify database access (remove access_helps.php duplication)
3. Extract encryption logic to `DefuseEncryption` service
4. Extract JWT logic to `FirebaseJwtProvider`
5. Create database connection factory
6. Write integration tests

**Files to Create**:
- `src/Infrastructure/Persistence/PdoAccessTokenRepository.php`
- `src/Infrastructure/Security/DefuseEncryption.php`
- `src/Infrastructure/Security/FirebaseJwtProvider.php`
- `src/Infrastructure/Persistence/DatabaseConnection.php`
- `tests/Integration/Persistence/PdoAccessTokenRepositoryTest.php`

### Phase 4: Application Layer (Week 4)

**Goal**: Create application services

**Tasks**:
1. Extract `AuthenticationService` from login.php/callback.php
2. Create `TokenManagementService`
3. Create `CookieService` with proper encryption
4. Create `JwtService` abstraction
5. Define DTOs for request/response
6. Write service tests

**Files to Create**:
- `src/Application/Service/AuthenticationService.php`
- `src/Application/Service/TokenManagementService.php`
- `src/Application/Service/CookieService.php`
- `src/Application/Dto/AuthRequest.php`
- `src/Application/Dto/AuthResult.php`

### Phase 5: Presentation Layer (Week 5)

**Goal**: Create controllers and middleware

**Tasks**:
1. Create controllers for each action (LoginController, CallbackController, etc.)
2. Implement PSR-15 middleware stack
3. Create router component
4. Move HTML to Twig templates (if needed)
5. Implement JSON responses for API
6. Write functional tests

**Files to Create**:
- `src/Presentation/Controller/LoginController.php`
- `src/Presentation/Controller/CallbackController.php`
- `src/Presentation/Middleware/AuthenticationMiddleware.php`
- `src/Presentation/Router/Router.php`

### Phase 6: Cleanup (Week 6)

**Goal**: Remove legacy code and validate

**Tasks**:
1. **DELETE**: `oauth/u.php` (security backdoor)
2. **DELETE**: `oauth/access_helps.php` (deprecated)
3. **DELETE**: `auths_tests/` directory
4. **REMOVE**: All `?test=1` debug toggles
5. **REMOVE**: Hardcoded credentials
6. **MIGRATE**: `oauth/` files to new structure
7. Run full test suite
8. Update documentation

**Validation Checklist**:
- [ ] All tests passing (PHPUnit)
- [ ] Static analysis passing (PHPStan level 8)
- [ ] Code style compliant (PHP_CodeSniffer PSR-12)
- [ ] Security scan clean
- [ ] Deployment tested in staging

---

## Deployment Efficiency Improvements

### 1. Containerization

**Docker Support**:
```dockerfile
# Dockerfile
FROM php:8.2-fpm-alpine

# Install dependencies
RUN apk add --no-cache mysql-client
RUN docker-php-ext-install pdo_mysql

# Copy application
COPY . /var/www/html
WORKDIR /var/www/html

# Install composer dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data /var/www/html/var

# Health check
HEALTHCHECK --interval=30s --timeout=3s \
  CMD php-fpm -t || exit 1
```

**Benefits**:
- **Consistency**: Same environment in dev, CI, and production
- **Isolation**: Application dependencies isolated from host
- **Scalability**: Easy horizontal scaling with container orchestration
- **Version Control**: Infrastructure as code

### 2. Build Automation

**Makefile**:
```makefile
# Development commands
install:
	composer install
	cp .env.example .env

test:
	vendor/bin/phpunit

lint:
	vendor/bin/phpcs src/ tests/

analyse:
	vendor/bin/phpstan analyse src/ tests/ --level=8

fix:
	vendor/bin/phpcbf src/ tests/

# Deployment commands
depLOY-staging:
	rsync -avz --exclude='.git' --exclude='var/' . user@staging:/var/www/auth_repo/
	docker-compose -f docker-compose.staging.yml up -d

dEPLOY-production:
	composer install --no-dev --optimize-autoloader
	rsync -avz --exclude='.git' --exclude='tests/' --exclude='var/' . user@prod:/var/www/auth_repo/
```

### 3. CI/CD Pipeline

**GitHub Actions**:
```yaml
# .github/workflows/ci.yml
name: CI/CD Pipeline

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      - run: composer install
      - run: vendor/bin/phpunit
      - run: vendor/bin/phpstan analyse --level=8
      - run: vendor/bin/phpcs

  deploy:
    needs: test
    if: github.ref == 'refs/heads/main'
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - run: composer install --no-dev --optimize-autoloader
      - name: Deploy to production
        run: |
          rsync -avz --exclude='.git' --exclude='tests/' . ${{ secrets.SSH_USER }}@${{ secrets.SSH_HOST }}:/var/www/auth_repo/
```

### 4. Environment-Specific Configuration

**Configuration Loading**:
```php
// config/config.php
class Config {
    public static function fromEnvironment(string $env): self {
        $base = require __DIR__ . '/config.php';
        $override = require __DIR__ . "/config.{$env}.php";
        return new self(array_merge($base, $override));
    }
}

// Usage
$config = Config::fromEnvironment($_ENV['APP_ENV'] ?? 'dev');
```

**Benefits**:
- **Environment Parity**: Same code, different configurations
- **Type Safety**: Configuration validated at runtime
- **No Conditionals**: No `if (localhost)` checks in production code
- **Feature Flags**: Environment-based feature toggles

---

## Security Enhancements

### 1. Attack Surface Reduction

**Current Issues**:
- All PHP files web-accessible
- `u.php` backdoor present in repository
- `?test=1` exposes debug information

**Mitigations**:
- **Document Root**: Only `public/index.php` accessible
- **Remove Backdoors**: Delete `oauth/u.php`
- **Debug Mode**: Controlled via environment variable, not URL parameter
- **Error Handling**: Generic error messages in production, detailed logs internally

### 2. Input Validation

**Current Issues**:
- Raw `$_GET`/`$_POST` used throughout
- No centralized validation
- XSS vulnerabilities in error output

**Mitigations**:
- **Request Objects**: PSR-7 ServerRequestInterface for all input
- **Validation Layer**: Symfony Validator or custom validation
- **Output Escaping**: Automatic escaping in templates
- **Type Safety**: Value objects enforce valid states

### 3. CSRF Protection

**Current Issues**:
- No CSRF tokens
- State-changing operations via GET requests

**Mitigations**:
- **CSRF Middleware**: Automatic token validation
- **State Changing via POST**: All mutations use POST/PUT/DELETE
- **Double Submit Cookie**: Defense in depth

### 4. Security Headers

**Current Issues**:
- No security headers set
- Session cookies without secure flags

**Mitigations**:
```php
// SecurityHeadersMiddleware
public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
    $response = $handler->handle($request);
    
    return $response
        ->withHeader('X-Frame-Options', 'DENY')
        ->withHeader('X-Content-Type-Options', 'nosniff')
        ->withHeader('X-XSS-Protection', '1; mode=block')
        ->withHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains')
        ->withHeader('Content-Security-Policy', "default-src 'self'")
        ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
}
```

---

## Maintainability Improvements

### 1. Code Quality Tools

**Static Analysis**:
```bash
# PHPStan - detects type errors and undefined methods
vendor/bin/phpstan analyse src/ tests/ --level=8

# Psalm - alternative static analyzer
vendor/bin/psalm

# PHP_CodeSniffer - enforces coding standards
vendor/bin/phpcs --standard=PSR12 src/ tests/
```

**Automated Fixes**:
```bash
# PHP-CS-Fixer - automatic code style fixes
vendor/bin/php-cs-fixer fix src/ --rules=@PSR12

# Rector - automated refactoring
vendor/bin/rector process src/ --dry-run
```

### 2. Documentation Standards

**PHPDoc**:
```php
/**
 * Initiates OAuth authentication flow.
 *
 * Generates a request token and redirects user to OAuth provider.
 *
 * @param AuthRequest $request Contains callback URL and state parameters
 * @return AuthResult Contains authorization URL or error details
 * @throws OAuthException If token generation fails
 */
public function initiate(AuthRequest $request): AuthResult;
```

**Architecture Decision Records (ADRs)**:
```
docs/architecture/
├── 001-use-layered-architecture.md
├── 002-use-psr-4-autoloading.md
├── 003-use-environment-configuration.md
└── 004-use-dependency-injection.md
```

### 3. Logging Strategy

**PSR-3 Logger**:
```php
// Structured logging with context
$this->logger->info('OAuth callback received', [
    'user' => $username,
    'ip' => $request->getServerParams()['REMOTE_ADDR'],
    'user_agent' => $request->getHeaderLine('User-Agent'),
]);

$this->logger->error('Database connection failed', [
    'exception' => $e->getMessage(),
    'host' => $config->database['host'],
]);
```

**Benefits**:
- **Structured Data**: Log aggregation and analysis
- **Context**: Debug information without exposing to users
- **Security**: Sensitive data redacted in production logs

---

## Performance Considerations

### 1. Autoloading Optimization

**Composer Optimizations**:
```bash
# Generate optimized autoloader
composer dump-autoload --optimize

# Classmap authorization (for production)
composer dump-autoload --classmap-authoritative
```

**Benefits**:
- **Faster Loading**: Classmap eliminates file existence checks
- **APC Compatibility**: Works with opcode caches
- **Production Ready**: Recommended for deployment

### 2. Dependency Injection Container

**Compiled Container** (PHP-DI):
```php
$builder = new ContainerBuilder();
$builder->enableCompilation(__DIR__ . '/../var/cache');
$builder->writeProxiesToFile(true, __DIR__ . '/../var/cache/proxies');
```

**Benefits**:
- **Compilation**: Container definitions compiled to PHP
- **No Reflection**: Faster instantiation at runtime
- **Production Optimized**: Cache generated on deployment

### 3. Caching Strategy

**Application Cache**:
```php
// Redis or APCu for frequently accessed data
class CachedAccessTokenRepository implements AccessTokenRepositoryInterface {
    public function __construct(
        private AccessTokenRepositoryInterface $inner,
        private CacheInterface $cache
    ) {}
    
    public function findByUsername(Username $username): ?AccessToken {
        $key = "token:{$username->toString()}";
        
        if ($this->cache->has($key)) {
            return $this->cache->get($key);
        }
        
        $token = $this->inner->findByUsername($username);
        $this->cache->set($key, $token, 300); // 5 minutes
        
        return $token;
    }
}
```

---

## Conclusion

The proposed directory structure transforms a legacy, flat codebase into a modern, maintainable PHP application. Key achievements:

1. **Security**: Document root isolation removes attack vectors, environment configuration eliminates hardcoded secrets
2. **Maintainability**: Layered architecture separates concerns, PSR-4 autoloading enables modern tooling
3. **Testability**: Clear layer boundaries support comprehensive testing at unit, integration, and functional levels
4. **Deployment**: Containerization and CI/CD pipeline enable automated, reliable deployments
5. **Scalability**: Stateless architecture supports horizontal scaling, caching layer improves performance

The migration is incremental, allowing the system to remain functional throughout the refactoring process. Each phase delivers value independently while building toward the target architecture.

**Success Metrics**:
- 80%+ test coverage
- PHPStan level 8 compliance
- Zero critical security issues
- <100ms average response time
- <15% technical debt ratio

---

*Proposal Version: 1.0*
*Date: 2026-02-15*
*Based on analysis of codebase with 28 source files, ~3,500 LOC*
