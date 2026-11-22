<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

/**
 * Append a structured log line. Never writes API keys or full card data.
 *
 * @param array<string, mixed> $context
 */
function geekhost_log(string $event, array $context = []): void
{
    $root = dirname(__DIR__, 2);
    $dir = $root . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $file = $dir . DIRECTORY_SEPARATOR . 'checkout.log';

    $safe = $context;
    foreach ([
        'apiKey', 'access_token', 'creditCard', 'number', 'ccv', 'cvv',
        'ASAAS_SANDBOX_API_KEY', 'ASAAS_PRODUCTION_API_KEY',
        'DIRECTADMIN_LOGIN_KEY', 'DIRECTADMIN_PASSWORD', 'password', 'passwd', 'adminPass',
        'da_password_enc', 'userpwd', 'authorization', 'bearer', 'token',
        'asaas_credit_card_token', 'password_hash', 'card_last4',
    ] as $secretKey) {
        if (array_key_exists($secretKey, $safe)) {
            $safe[$secretKey] = '[redacted]';
        }
    }

    $line = json_encode([
        'ts' => gmdate('c'),
        'event' => $event,
        'context' => $safe,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($line !== false) {
        @file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
}
