<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';
require_once dirname(__DIR__, 2) . '/includes/TicketService.php';

$auth = new Auth();
$user = $auth->requireApiUser();
$tickets = new TicketService();
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    echo json_encode([
        'ok' => true,
        'unread' => $tickets->unreadNotificationCount((int) $user['id']),
        'items' => $tickets->listNotifications((int) $user['id']),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$payload = is_string($raw) && $raw !== '' ? (json_decode($raw, true) ?: []) : $_POST;
if (!csrf_validate(isset($payload['csrfToken']) ? (string) $payload['csrfToken'] : null)) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'error' => 'CSRF inválido.']);
    exit;
}

$tickets->markNotificationsRead(
    (int) $user['id'],
    isset($payload['id']) ? (string) $payload['id'] : null
);
echo json_encode(['ok' => true, 'unread' => 0], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
