<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';
require_once dirname(__DIR__, 2) . '/includes/TicketService.php';
require_once dirname(__DIR__, 2) . '/includes/ProvisioningService.php';
require_once dirname(__DIR__, 2) . '/includes/DirectAdminClient.php';
require_once dirname(__DIR__, 2) . '/includes/logger.php';

$auth = new Auth();
$user = $auth->requireApiUser();
if (!TicketService::isAdminEmail((string) ($user['email'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Acesso restrito à equipe GeekHost.']);
    exit;
}

$svc = new ProvisioningService();
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = (string) ($_GET['action'] ?? 'overview');

try {
    if ($method === 'GET' && $action === 'overview') {
        $da = new DirectAdminClient();
        echo json_encode([
            'ok' => true,
            'provisioningEnabled' => ProvisioningService::provisioningEnabled(),
            'dryRun' => $da->isDryRun(),
            'configured' => $da->isConfigured(),
            'probe' => $da->isConfigured() ? $da->probe() : ['ok' => false, 'message' => 'Não configurado'],
            'accounts' => $svc->listAccountsForAdmin(100),
            'jobs' => $svc->listJobsForAdmin(80),
            'audit' => $svc->listAuditForAdmin(80),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($method !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
        exit;
    }

    $payload = [];
    $contentType = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($decoded)) {
            $payload = $decoded;
        }
    } else {
        $payload = $_POST;
    }

    if (!csrf_validate((string) ($payload['csrfToken'] ?? $payload['csrf'] ?? ''))) {
        http_response_code(419);
        echo json_encode(['ok' => false, 'error' => 'CSRF inválido']);
        exit;
    }

    $action = (string) ($payload['action'] ?? $action);

    if ($action === 'reprocess') {
        $id = (string) ($payload['id'] ?? '');
        if ($id === '' || !$svc->reprocessAccount($id, 'admin:' . (string) ($user['email'] ?? ''))) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Conta não encontrada']);
            exit;
        }
        $run = $svc->processPendingJobs(10);
        echo json_encode(['ok' => true, 'run' => $run], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($action === 'run_cron') {
        $jobs = $svc->processPendingJobs(30);
        $dns = $svc->pollDnsAndSsl(50);
        $jobs2 = $svc->processPendingJobs(30);
        echo json_encode(['ok' => true, 'jobs' => $jobs, 'dns' => $dns, 'jobsFollowUp' => $jobs2], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Ação inválida']);
} catch (Throwable $e) {
    geekhost_log('admin_hosting_error', ['error' => $e->getMessage()]);
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
