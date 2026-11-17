<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/OrderService.php';

$id = isset($_GET['id']) ? (string) $_GET['id'] : '';
if ($id === '' || !preg_match('/^[a-f0-9-]{36}$/i', $id)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'ID inválido']);
    exit;
}

try {
    $service = new OrderService();
    $order = $service->findOrderByPublicId($id);
    if (!$order) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Pedido não encontrado']);
        exit;
    }
    echo json_encode(['ok' => true, 'order' => $service->presentOrder($order)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Erro ao consultar pedido']);
}
