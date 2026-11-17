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
 * Root-absolute URL for a file under public_html (e.g. assets/images/logo.png).
 * Survives pretty URLs and DirectAdmin document-root deploys; local php -S -t public_html too.
 */
function asset(string $path): string
{
    return '/' . ltrim(str_replace('\\', '/', $path), '/');
}

function geekhost_public_url(string $path = ''): string
{
    global $publicUrl;
    $base = $publicUrl !== '' ? $publicUrl : '';
    if ($base === '') {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = ($https ? 'https://' : 'http://') . $host;
    }
    if ($path === '') {
        return $base;
    }
    return $base . '/' . ltrim($path, '/');
}
