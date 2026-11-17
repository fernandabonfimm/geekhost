<?php

declare(strict_types=1);

function geekhost_boot_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string
{
    geekhost_boot_session();
    if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_validate(?string $token): bool
{
    geekhost_boot_session();
    $session = $_SESSION['_csrf'] ?? '';
    return is_string($token) && is_string($session) && $session !== '' && hash_equals($session, $token);
}
