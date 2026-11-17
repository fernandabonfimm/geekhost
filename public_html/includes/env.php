<?php

declare(strict_types=1);

/**
 * Load key=value pairs from a .env file outside the web root into putenv/$_ENV.
 * Does not override variables already present in the process environment.
 */
function geekhost_load_env(?string $path = null): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $candidates = [];
    if ($path !== null) {
        $candidates[] = $path;
    }
    // Project root (sibling of public_html)
    $candidates[] = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
    // One level above project (extra-safe hosting layouts)
    $candidates[] = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . '.env';

    $file = null;
    foreach ($candidates as $candidate) {
        if (is_readable($candidate)) {
            $file = $candidate;
            break;
        }
    }
    if ($file === null) {
        return;
    }

    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if ($name === '') {
            continue;
        }
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }
        if (getenv($name) !== false) {
            continue;
        }
        putenv($name . '=' . $value);
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
    }
}

function env(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return (string) $value;
}
