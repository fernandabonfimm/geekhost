<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

function geekhost_app_data_key(): string
{
    geekhost_load_env();
    $key = (string) env('APP_DATA_KEY', '');
    if (strlen($key) < 32) {
        // Deterministic fallback for local/dev only — set APP_DATA_KEY in production.
        $key = hash('sha256', (string) env('ASAAS_SANDBOX_API_KEY', 'geekhost-dev-key'));
    }
    return hash('sha256', $key, true);
}

function geekhost_encrypt(?string $plain): ?string
{
    if ($plain === null || $plain === '') {
        return $plain;
    }
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($plain, 'AES-256-CBC', geekhost_app_data_key(), OPENSSL_RAW_DATA, $iv);
    if ($cipher === false) {
        throw new RuntimeException('Falha ao criptografar dado.');
    }
    return base64_encode($iv . $cipher);
}

function geekhost_decrypt(?string $payload): ?string
{
    if ($payload === null || $payload === '') {
        return $payload;
    }
    $raw = base64_decode($payload, true);
    if ($raw === false || strlen($raw) < 17) {
        return $payload; // legacy plaintext fallback
    }
    $iv = substr($raw, 0, 16);
    $cipher = substr($raw, 16);
    $plain = openssl_decrypt($cipher, 'AES-256-CBC', geekhost_app_data_key(), OPENSSL_RAW_DATA, $iv);
    return $plain === false ? null : $plain;
}

function geekhost_is_sealed(?string $stored): bool
{
    return is_string($stored) && str_starts_with($stored, 'gh1:');
}

/** Encrypt a value for storage. Empty stays empty. Already sealed values are kept. */
function geekhost_seal(?string $plain): ?string
{
    if ($plain === null || $plain === '') {
        return $plain;
    }
    if (geekhost_is_sealed($plain)) {
        return $plain;
    }
    $cipher = geekhost_encrypt($plain);
    return $cipher === null ? null : 'gh1:' . $cipher;
}

/** Decrypt a stored value for display or a server-side charge. Legacy plaintext is returned as-is. */
function geekhost_open(?string $stored): ?string
{
    if ($stored === null || $stored === '') {
        return $stored;
    }
    if (geekhost_is_sealed($stored)) {
        $plain = geekhost_decrypt(substr($stored, 4));
        return $plain === null ? '' : $plain;
    }
    return geekhost_decrypt($stored);
}
