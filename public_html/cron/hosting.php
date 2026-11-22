<?php

declare(strict_types=1);

/**
 * Cron every ~5 minutes:
 *   php public_html/cron/hosting.php
 *   or GET /cron/hosting.php?token=CRON_HOSTING_TOKEN
 *
 * Processes DA provision jobs, DNS polls, and SSL/WordPress queues.
 */

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/env.php';
require_once dirname(__DIR__) . '/includes/ProvisioningService.php';
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
    $svc = new ProvisioningService();
    $jobs = $svc->processPendingJobs(30);
    $dns = $svc->pollDnsAndSsl(50);
    // Second pass picks up SSL/wordpress jobs enqueued by DNS poll
    $jobs2 = $svc->processPendingJobs(30);
    $billing = null;
    try {
        require_once dirname(__DIR__) . '/includes/BillingDunningService.php';
        $billing = (new BillingDunningService())->run();
    } catch (Throwable $billingError) {
        geekhost_log('cron_billing_error', ['error' => $billingError->getMessage()]);
        $billing = ['ok' => false];
    }

    $result = [
        'ok' => true,
        'provisioningEnabled' => ProvisioningService::provisioningEnabled(),
        'jobs' => $jobs,
        'jobsFollowUp' => $jobs2,
        'dns' => $dns,
        'billing' => $billing,
        'ts' => gmdate('c'),
    ];
    geekhost_log('cron_hosting', $result);
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    geekhost_log('cron_hosting_error', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Cron failed']);
}
