<?php

/**
 * Load .env file securely with whitelist and permission checks.
 *
 * @param string $filePath
 * @param array $allowedKeys
 * @return void
 * @throws RuntimeException
 */
function loadEnvFile(string $filePath, array $allowedKeys = []): void
{
    if (!file_exists($filePath) || !is_readable($filePath)) {
        throw new RuntimeException("Env file not found or not readable: $filePath");
    }

    // Ensure file is owner-readable only (optional)
    $perms = fileperms($filePath) & 0777;
    if ($perms & 0x38) { // group/world readable/writeable/exec
        throw new RuntimeException("Env file permissions are too permissive: $filePath");
    }

    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        // Skip empty lines and comments
        if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
            continue;
        }

        // Must contain '='
        if (!str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);

        // Only allow keys in whitelist if defined
        if (!empty($allowedKeys) && !in_array($key, $allowedKeys, true)) {
            continue;
        }

        // Remove surrounding quotes
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        // Prevent overwriting existing environment variables
        if (array_key_exists($key, $_ENV) || getenv($key) !== false) {
            continue;
        }

        $_ENV[$key] = $value;

        // Expose to getenv()
        if (!@putenv("$key=$value")) {
            throw new RuntimeException("Failed to set environment variable: $key");
        }
    }
}

// Example usage:
try {
    $envFile = __DIR__ . '/.env';
    loadEnvFile($envFile);
} catch (RuntimeException $e) {
    error_log('ENV Loader Error: ' . $e->getMessage());
    exit(1); // Fail-fast
}
