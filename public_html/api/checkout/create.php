<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';
require_once dirname(__DIR__, 2) . '/includes/OrderService.php';
require_once dirname(__DIR__, 2) . '/includes/logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = [];
if (is_string($raw) && $raw !== '') {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $payload = $decoded;
    }
}
if ($payload === [] && !empty($_POST)) {
    $payload = $_POST;
}

try {
    if (!csrf_validate(isset($payload['csrfToken']) ? (string) $payload['csrfToken'] : null)) {
        http_response_code(419);
        echo json_encode(['ok' => false, 'error' => 'CSRF inválido. Recarregue a página.']);
        exit;
    }

    $service = new OrderService();
    $order = $service->createCheckout($payload);
    echo json_encode(['ok' => true, 'order' => $order], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $e->getMessage(), 'asaas' => false]);
} catch (Throwable $e) {
    geekhost_log('api_checkout_error', ['error' => $e->getMessage()]);
    http_response_code(400);
    $message = $e->getMessage();
    $asaas = (bool) preg_match('/gateway|asaas|cobran[cç]a|pix|boleto|cart[aã]o|assinatura/i', $message);
    echo json_encode(['ok' => false, 'error' => $message, 'asaas' => $asaas], JSON_UNESCAPED_UNICODE);
}
