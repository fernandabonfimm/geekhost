<?php

declare(strict_types=1);

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
    '/painel' => '/painel/index.php',
    '/painel/' => '/painel/index.php',
    '/painel/login' => '/painel/login.php',
    '/painel/login/' => '/painel/login.php',
    '/painel/logout' => '/painel/logout.php',
    '/painel/logout/' => '/painel/logout.php',
    '/painel/faturas' => '/painel/faturas.php',
    '/painel/faturas/' => '/painel/faturas.php',
    '/painel/perfil' => '/painel/perfil.php',
    '/painel/perfil/' => '/painel/perfil.php',
    '/painel/carteira' => '/painel/carteira.php',
    '/painel/carteira/' => '/painel/carteira.php',
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
