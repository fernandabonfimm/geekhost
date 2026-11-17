<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/OrderService.php';
require_once dirname(__DIR__, 2) . '/includes/logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = json_decode(is_string($raw) ? $raw : '', true);
if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

$token = $_SERVER['HTTP_ASAAS_ACCESS_TOKEN'] ?? '';
if (!is_string($token) || $token === '') {
    // Some proxies normalize headers differently
    foreach ($_SERVER as $key => $value) {
        if (strtoupper((string) $key) === 'HTTP_ASAAS_ACCESS_TOKEN') {
            $token = (string) $value;
            break;
        }
    }
}

try {
    $service = new OrderService();
    $service->handleWebhook($payload, (string) $token);
    http_response_code(200);
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    $code = (int) $e->getCode();
    if ($code === 401) {
        http_response_code(401);
        geekhost_log('webhook_unauthorized', []);
        echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
        exit;
    }
    geekhost_log('webhook_error', ['error' => $e->getMessage()]);
    // Return 200 only for valid token + processed/duplicate. Other failures: 400 so Asaas can retry.
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Processing failed']);
}
