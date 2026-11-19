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
    // Project root (sibling of public_html) — e.g. domains/geekhost.com.br/.env
    $candidates[] = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';
    // Same folder as public_html's parent via relative climb from includes/
    $candidates[] = dirname(__DIR__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '.env';
    // One level above project (extra-safe hosting layouts)
    $candidates[] = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . '.env';
    // Last resort: .env inside public_html (not ideal, but works on locked hosts)
    $candidates[] = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';

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

/**
 * Asaas environment: production only on HTTPS + geekhost.com.br / www.
 * Everywhere else (XAMPP, php -S, tunnels) uses sandbox.
 */
function geekhost_resolve_asaas_env(?string $forceEnv = null): string
{
    if ($forceEnv !== null) {
        $forced = strtolower($forceEnv);
        if (!in_array($forced, ['sandbox', 'production'], true)) {
            throw new InvalidArgumentException('ASAAS_ENV must be sandbox or production.');
        }
        return $forced;
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\\d+$/', '', $host) ?? $host;

    if ($https && ($host === 'geekhost.com.br' || $host === 'www.geekhost.com.br')) {
        return 'production';
    }

    $explicit = strtolower((string) env('ASAAS_ENV', 'sandbox'));
    if ($explicit === 'production' && env('ASAAS_ALLOW_PROD_LOCAL') === '1') {
        return 'production';
    }

    return 'sandbox';
}
