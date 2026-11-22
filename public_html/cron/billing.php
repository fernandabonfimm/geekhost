<?php

declare(strict_types=1);

/**
 * Daily collection cron:
 *   php public_html/cron/billing.php
 *   or GET /cron/billing.php?token=CRON_HOSTING_TOKEN
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/BillingDunningService.php';
require_once dirname(__DIR__) . '/includes/logger.php';

geekhost_load_env();

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    $token = (string) ($_GET['token'] ?? '');
    $expected = (string) env('CRON_HOSTING_TOKEN', '');
    if ($expected === '' || !hash_equals($expected, $token)) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
        exit;
    }
}

try {
    $result = (new BillingDunningService())->run();
    geekhost_log('cron_billing', $result);
    echo json_encode(['ok' => true] + $result, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    geekhost_log('cron_billing_error', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Cron failed']);
}
