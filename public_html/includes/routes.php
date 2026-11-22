<?php

declare(strict_types=1);

/**
 * Pretty URLs for the PHP built-in server.
 * Admin is public /painel (files in admin/). Client is public /portal (files in painel/).
 * php -S without router.php serves a directory index.php for any extra path,
 * so the homepages call geekhost_cli_dispatch() before rendering.
 */

function geekhost_public_root(): string
{
    return dirname(__DIR__);
}

function geekhost_public_script(string $uri): ?string
{
    $path = parse_url($uri, PHP_URL_PATH);
    $path = is_string($path) ? $path : '/';
    $path = rawurldecode($path);
    if ($path !== '/' && str_ends_with($path, '/')) {
        $path = rtrim($path, '/');
    }

    $fixed = [
        '/checkout' => '/checkout.php',
        '/api/checkout/create' => '/api/checkout/create.php',
        '/api/checkout/status' => '/api/checkout/status.php',
        '/api/webhooks/asaas' => '/api/webhooks/asaas.php',
        '/cron/billing' => '/cron/billing.php',
    ];
    if (isset($fixed[$path]) && is_file(geekhost_public_root() . $fixed[$path])) {
        return $fixed[$path];
    }

    if (preg_match('#^/(portal|painel)(/.*)?$#', $path, $m) === 1) {
        $base = $m[1] === 'portal' ? '/painel' : '/admin';
        $rest = $m[2] ?? '';
        if ($rest === '' || $rest === '/') {
            $rel = $base . '/index.php';
        } elseif (str_ends_with($rest, '.php')) {
            $rel = $base . $rest;
        } else {
            $rel = $base . $rest . '.php';
        }
        return is_file(geekhost_public_root() . $rel) ? $rel : null;
    }

    if (preg_match('#^/api/(portal|painel)/([a-z0-9-]+)$#', $path, $m) === 1) {
        $folder = $m[1] === 'portal' ? '/api/painel/' : '/api/admin/';
        $rel = $folder . $m[2] . '.php';
        return is_file(geekhost_public_root() . $rel) ? $rel : null;
    }

    return null;
}

function geekhost_cli_dispatch(): void
{
    if (PHP_SAPI !== 'cli-server' || defined('GEEKHOST_ROUTED')) {
        return;
    }
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $uri = is_string($uri) ? rawurldecode($uri) : '/';

    if (preg_match('#^/admin(/.*)?$#', $uri) === 1) {
        $tail = substr($uri, strlen('/admin'));
        header('Location: /painel' . ($tail !== '' ? $tail : '/'), true, 301);
        exit;
    }

    $script = geekhost_public_script($uri);
    if ($script === null) {
        return;
    }
    $file = geekhost_public_root() . $script;
    $current = realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $target = realpath($file);
    if ($target === false) {
        return;
    }
    if ($current !== false && $current === $target) {
        return;
    }
    define('GEEKHOST_ROUTED', true);
    require $target;
    exit;
}
