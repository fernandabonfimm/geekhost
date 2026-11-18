<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';
geekhost_load_env();

$siteName = 'GeekHost';
$companyEmail = filter_var(env('GEEKHOST_CONTACT_EMAIL', 'contato@geekhost.com.br') ?: '', FILTER_VALIDATE_EMAIL) ?: '';
$companyWhatsapp = preg_replace('/\D+/', '', env('GEEKHOST_WHATSAPP', '5516997399174') ?: '') ?? '';
$publicUrl = rtrim((string) env('GEEKHOST_PUBLIC_URL', ''), '/');

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
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
            $prefix = rtrim((string) $m[1], '/');
            $base = ($prefix === '' ? '' : $prefix) . '/public_html';
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
 * Site-absolute path (includes optional base path). Use for redirects and internal links.
 */
function url(string $path = '/'): string
{
    $base = geekhost_base_path();
    $path = str_replace('\\', '/', $path);
    if ($path === '' || $path === '/') {
        return $base === '' ? '/' : $base . '/';
    }
    if (!str_starts_with($path, '/')) {
        $path = '/' . $path;
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

function geekhost_public_url(string $path = ''): string
{
    global $publicUrl;
    $base = $publicUrl !== '' ? $publicUrl : '';
    if ($base === '') {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = ($https ? 'https://' : 'http://') . $host . geekhost_base_path();
    }
    if ($path === '') {
        return $base;
    }
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}
