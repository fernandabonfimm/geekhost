<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/OrderService.php';

$auth = new Auth();
$user = $auth->requireApiUser();

$orders = new OrderService();
$list = $orders->listInvoicesForUser((int) $user['id'], null, [
    'status' => isset($_GET['status']) ? (string) $_GET['status'] : null,
    'q' => isset($_GET['q']) ? (string) $_GET['q'] : null,
    'from' => isset($_GET['from']) ? (string) $_GET['from'] : null,
    'to' => isset($_GET['to']) ? (string) $_GET['to'] : null,
]);

echo json_encode([
    'ok' => true,
    'counts' => $orders->invoiceCounts((int) $user['id']),
    'invoices' => $list,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
