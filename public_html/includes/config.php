<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';
geekhost_load_env();

$siteName = 'GeekHost';
$companyEmail = filter_var(env('GEEKHOST_CONTACT_EMAIL', 'contato@geekhost.com.br') ?: '', FILTER_VALIDATE_EMAIL) ?: '';
$companyWhatsapp = preg_replace('/\D+/', '', env('GEEKHOST_WHATSAPP', '5516997399174') ?: '') ?? '';
$publicUrl = rtrim((string) env('GEEKHOST_PUBLIC_URL', ''), '/');

function e(string|int|float|null $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * App base path when public_html is not the Apache DocumentRoot
 * (e.g. XAMPP junction → /geekhost/public_html). Empty when DocumentRoot is public_html
 * (php -S -t public_html, or geekhost.test vhost).
 *
 * Live HTTP requests always win over GEEKHOST_BASE_PATH so the same .env works for
 * both XAMPP subfolder and php -S on :8890.
 */
function geekhost_base_path(): string
{
    static $resolved = false;
    static $base = '';
    if ($resolved) {
        return $base;
    }
    $resolved = true;

    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($script !== '') {
        if (preg_match('#^(.*?)/public_html(?:/|$)#', $script, $m) === 1) {
            // public_html is the internal folder. Links stay at /geekhost/... .
            $base = rtrim((string) $m[1], '/');
            return $base;
        }
        // DocumentRoot === public_html
        $base = '';
        return $base;
    }

    $configured = trim((string) env('GEEKHOST_BASE_PATH', ''), "/ \t");
    if ($configured !== '') {
        $base = '/' . str_replace('\\', '/', $configured);
        return $base;
    }

    $base = '';
    return $base;
}

/**
 * Identity helper kept for call sites. Invariant: admin = /admin, client = /painel.
 * Do not remap /admin → /painel or /painel → /portal.
 */
function geekhost_canonical_route(string $path): string
{
    return $path;
}

function geekhost_is_app_route(string $path): bool
{
    return preg_match('#^/(admin|painel|api/admin|api/painel)(/|$|\?)#', $path) === 1;
}

/**
 * Local XAMPP serves the marketing site under /geekhost, but admin and client
 * links must be root-absolute: /admin/... and /painel/...
 */
function geekhost_request_is_loopback(): bool
{
    if (in_array(PHP_SAPI, ['cli', 'phpdbg'], true)) {
        return false;
    }
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host) ?? $host;
    $named = $host === '127.0.0.1' || $host === 'localhost' || $host === '::1' || str_starts_with($host, '127.');
    if (!$named) {
        return false;
    }
    $loop = static function (string $ip): bool {
        return $ip === '127.0.0.1' || $ip === '::1' || str_starts_with($ip, '127.');
    };
    return $loop((string) ($_SERVER['SERVER_ADDR'] ?? '')) || $loop((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
}

/**
 * Site-absolute path (includes optional base path). Use for redirects and internal links.
 */
function url(string $path = '/'): string
{
    $path = str_replace('\\', '/', $path);
    if ($path !== '' && $path !== '/') {
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }
        $path = geekhost_canonical_route($path);
    }
    $base = geekhost_base_path();
    if (geekhost_request_is_loopback() && geekhost_is_app_route($path)) {
        $base = '';
    }
    if ($path === '' || $path === '/') {
        return $base === '' ? '/' : $base . '/';
    }
    return $base . $path;
}

/**
 * Root-absolute URL for a file under public_html (e.g. assets/images/logo.png).
 * Survives pretty URLs, XAMPP subfolders, and DirectAdmin document-root deploys.
 */
function asset(string $path): string
{
    return url(ltrim(str_replace('\\', '/', $path), '/'));
}

/**
 * Safe post-login target inside the client panel only (/painel/*).
 */
function geekhost_safe_painel_next(?string $raw, string $fallback = '/painel/'): string
{
    $raw = trim((string) $raw);
    if ($raw === '') {
        return $fallback;
    }
    if (str_contains($raw, '://') || str_starts_with($raw, '//') || str_contains($raw, '..')) {
        return $fallback;
    }
    $path = $raw;
    $query = '';
    $qPos = strpos($raw, '?');
    if ($qPos !== false) {
        $query = substr($raw, $qPos);
        $path = substr($raw, 0, $qPos);
    }
    if (!preg_match('#^/painel(/[a-zA-Z0-9\-/_]*)?/?$#', $path)) {
        return $fallback;
    }
    if (preg_match('#^/painel/login/?$#', $path) === 1) {
        return $fallback;
    }
    return $path . $query;
}

function geekhost_send_security_headers(): void
{
    static $sent = false;
    if ($sent || PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }
    $sent = true;
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('X-Permitted-Cross-Domain-Policies: none');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        header('Strict-Transport-Security: max-age=15552000');
    }
}

/**
 * Same-origin config for panel/admin scripts. The API token is the 7-hour session token.
 */
function geekhost_client_config(?string $apiToken = null): void
{
    $payload = [
        'base' => geekhost_base_path(),
        'apiToken' => $apiToken ?? '',
    ];
    echo '<script>window.GH=' . json_encode(
        $payload,
        JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES
    ) . ';</script>';
}

function geekhost_public_url(string $path = ''): string
{
    global $publicUrl;
    $route = $path === '' ? '' : geekhost_canonical_route('/' . ltrim($path, '/'));
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if ($route !== '' && geekhost_request_is_loopback() && geekhost_is_app_route($route)) {
        $base = ($https ? 'https://' : 'http://') . $host;
    } elseif ($publicUrl !== '') {
        $base = $publicUrl;
    } else {
        $base = ($https ? 'https://' : 'http://') . $host . geekhost_base_path();
    }
    if ($route === '') {
        return $base;
    }
    return rtrim($base, '/') . $route;
}

geekhost_send_security_headers();
