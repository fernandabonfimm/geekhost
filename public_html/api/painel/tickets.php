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
$tickets = new TicketService();
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$action = (string) ($_GET['action'] ?? ($_POST['action'] ?? 'list'));

try {
    if ($method === 'GET' && $action === 'meta') {
        echo json_encode([
            'ok' => true,
            'categories' => TicketService::CATEGORIES,
            'statuses' => TicketService::statusLabels(),
            'priorities' => TicketService::priorityLabels(),
            'services' => $tickets->listUserServices((int) $user['id']),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($method === 'GET' && $action === 'list') {
        $result = $tickets->listForUser((int) $user['id'], [
            'q' => (string) ($_GET['q'] ?? ''),
            'status' => (string) ($_GET['status'] ?? ''),
            'category' => (string) ($_GET['category'] ?? ''),
            'page' => (int) ($_GET['page'] ?? 1),
            'perPage' => (int) ($_GET['perPage'] ?? 20),
        ]);
        echo json_encode(['ok' => true] + $result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($method === 'GET' && $action === 'get') {
        $id = (string) ($_GET['id'] ?? '');
        $ticket = $tickets->getTicketForUser((int) $user['id'], $id);
        if (!$ticket) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Ticket não encontrado.']);
            exit;
        }
        $tickets->markRead((int) $user['id'], $id, 'client');
        echo json_encode(['ok' => true, 'ticket' => $ticket], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($method === 'GET' && $action === 'poll') {
        $id = (string) ($_GET['id'] ?? '');
        $ticket = $tickets->getTicketForUser((int) $user['id'], $id);
        if (!$ticket) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Ticket não encontrado.']);
            exit;
        }
        $tickets->markRead((int) $user['id'], $id, 'client');
        echo json_encode([
            'ok' => true,
            'status' => $ticket['status'],
            'statusLabel' => $ticket['statusLabel'],
            'updatedAt' => $ticket['updatedAt'],
            'messages' => $ticket['messages'],
            'unread' => $ticket['unread'],
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
    $action = (string) ($payload['action'] ?? $action);
    $csrf = (string) ($payload['csrfToken'] ?? $payload['csrf'] ?? '');
    if (!csrf_validate($csrf)) {
        http_response_code(419);
        echo json_encode(['ok' => false, 'error' => 'CSRF inválido. Recarregue a página.']);
        exit;
    }

    if ($action === 'create') {
        $files = TicketService::collectUploadedFiles('attachments');
        $ticket = $tickets->createTicket((int) $user['id'], [
            'subject' => (string) ($payload['subject'] ?? ''),
            'category' => (string) ($payload['category'] ?? ''),
            'priority' => (string) ($payload['priority'] ?? 'normal'),
            'serviceId' => (string) ($payload['serviceId'] ?? ''),
            'body' => (string) ($payload['body'] ?? ''),
        ], $files);
        echo json_encode(['ok' => true, 'ticket' => $ticket], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'reply') {
        $files = TicketService::collectUploadedFiles('attachments');
        $ticket = $tickets->addClientReply(
            (int) $user['id'],
            (string) ($payload['id'] ?? ''),
            (string) ($payload['body'] ?? ''),
            $files
        );
        echo json_encode(['ok' => true, 'ticket' => $ticket], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($action === 'status') {
        $ticket = $tickets->setStatus(
            (int) $user['id'],
            'client',
            (string) ($payload['id'] ?? ''),
            (string) ($payload['status'] ?? ''),
            (int) $user['id']
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
    geekhost_log('api_tickets_error', ['error' => $e->getMessage()]);
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Não foi possível processar a solicitação.']);
}
