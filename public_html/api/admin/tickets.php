<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/csrf.php';
require_once dirname(__DIR__, 2) . '/includes/TicketService.php';
require_once dirname(__DIR__, 2) . '/includes/logger.php';

$auth = new Auth();
$user = $auth->requireApiUser();
if (!TicketService::isAdminEmail((string) ($user['email'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Acesso restrito à equipe GeekHost.']);
    exit;
}

$tickets = new TicketService();
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = (string) ($_GET['action'] ?? 'list');

try {
    if ($method === 'GET' && $action === 'dashboard') {
        echo json_encode([
            'ok' => true,
            'counts' => $tickets->adminCounts(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($method === 'GET' && $action === 'list') {
        $result = $tickets->listForAdmin([
            'q' => (string) ($_GET['q'] ?? ''),
            'status' => (string) ($_GET['status'] ?? ''),
            'category' => (string) ($_GET['category'] ?? ''),
            'priority' => (string) ($_GET['priority'] ?? ''),
            'page' => (int) ($_GET['page'] ?? 1),
            'perPage' => (int) ($_GET['perPage'] ?? 30),
        ]);
        echo json_encode(['ok' => true, 'counts' => $tickets->adminCounts()] + $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($method === 'GET' && ($action === 'get' || $action === 'poll')) {
        $id = (string) ($_GET['id'] ?? '');
        $ticket = $tickets->getTicketForAdmin($id);
        if (!$ticket) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Ticket não encontrado.']);
            exit;
        }
        $tickets->markRead((int) $user['id'], $id, 'staff');
        echo json_encode(['ok' => true, 'ticket' => $ticket], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
    $action = (string) ($payload['action'] ?? $action);
    if (!csrf_validate((string) ($payload['csrfToken'] ?? $payload['csrf'] ?? ''))) {
        http_response_code(419);
        echo json_encode(['ok' => false, 'error' => 'CSRF inválido.']);
        exit;
    }

    if ($action === 'reply') {
        $files = TicketService::collectUploadedFiles('attachments');
        $ticket = $tickets->addStaffReply(
            (int) $user['id'],
            (string) ($payload['id'] ?? ''),
            (string) ($payload['body'] ?? ''),
            !empty($payload['internal']),
            $files
        );
        echo json_encode(['ok' => true, 'ticket' => $ticket], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'status') {
        $ticket = $tickets->setStatus(
            (int) $user['id'],
            'staff',
            (string) ($payload['id'] ?? ''),
            (string) ($payload['status'] ?? '')
        );
        echo json_encode(['ok' => true, 'ticket' => $ticket], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'assign') {
        $assignee = $payload['assigneeId'] ?? null;
        $ticket = $tickets->assignTicket(
            (int) $user['id'],
            (string) ($payload['id'] ?? ''),
            $assignee === null || $assignee === '' ? (int) $user['id'] : (int) $assignee
        );
        echo json_encode(['ok' => true, 'ticket' => $ticket], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Ação inválida.']);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    geekhost_log('api_admin_tickets_error', ['error' => $e->getMessage()]);
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Não foi possível processar a solicitação.']);
}
