<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/config.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/TicketService.php';

$auth = new Auth();
$user = $auth->requireApiUser();
$tickets = new TicketService();
$id = (string) ($_GET['id'] ?? '');
$isAdmin = TicketService::isAdminEmail((string) ($user['email'] ?? ''));
$file = $tickets->resolveAttachmentDownload($id, (int) $user['id'], $isAdmin);
if (!$file) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => 'Anexo não encontrado.']);
    exit;
}

header('Content-Type: ' . $file['mime']);
header('Content-Length: ' . (string) filesize($file['path']));
header('Content-Disposition: attachment; filename="' . rawurlencode($file['name']) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($file['path']);
exit;
