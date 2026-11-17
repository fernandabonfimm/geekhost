<?php

declare(strict_types=1);

/**
 * Router for PHP built-in server:
 *   php -c php.ini -S 127.0.0.1:8890 -t public_html public_html/router.php
 */
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = is_string($uri) ? $uri : '/';

$map = [
    '/checkout' => '/checkout.php',
    '/checkout/' => '/checkout.php',
    '/api/checkout/create' => '/api/checkout/create.php',
    '/api/checkout/create/' => '/api/checkout/create.php',
    '/api/checkout/status' => '/api/checkout/status.php',
    '/api/checkout/status/' => '/api/checkout/status.php',
    '/api/webhooks/asaas' => '/api/webhooks/asaas.php',
    '/api/webhooks/asaas/' => '/api/webhooks/asaas.php',
];

if (isset($map[$uri])) {
    require __DIR__ . $map[$uri];
    return true;
}

$file = __DIR__ . $uri;
if ($uri !== '/' && is_file($file)) {
    return false;
}

return false;
