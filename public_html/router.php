<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/routes.php';

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = is_string($uri) ? rawurldecode($uri) : '/';

if (preg_match('#^/admin(/.*)?$#', $uri) === 1) {
    $tail = substr($uri, strlen('/admin'));
    header('Location: /painel' . ($tail !== '' ? $tail : '/'), true, 301);
    return true;
}

if ($uri === '/' || $uri === '/index.php') {
    return false;
}

$file = __DIR__ . str_replace('/', DIRECTORY_SEPARATOR, $uri);
if (is_file($file)) {
    return false;
}

define('GEEKHOST_ROUTED', true);
$script = geekhost_public_script($uri);
if ($script !== null) {
    require __DIR__ . $script;
    return true;
}

http_response_code(404);
header('Content-Type: text/plain; charset=UTF-8');
echo 'Not Found';
return true;
