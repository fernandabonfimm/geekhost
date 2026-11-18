<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/crypto.php';
require_once __DIR__ . '/logger.php';

final class TicketService
{
    public const STATUSES = ['open', 'in_progress', 'awaiting_client', 'resolved', 'closed'];
    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    public const CATEGORIES = [
        'hosting' => 'Hospedagem',
        'domains' => 'Domínios e DNS',
        'wordpress' => 'WordPress',
        'email' => 'E-mail profissional',
        'ssl' => 'SSL',
        'billing' => 'Faturamento e pagamentos',
        'migration' => 'Migração de site',
        'technical' => 'Problemas técnicos',
        'other' => 'Outros',
    ];

    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
        'application/pdf' => 'pdf',
        'text/plain' => 'txt',
        'text/log' => 'log',
        'application/zip' => 'zip',
    ];

    private const MAX_BYTES = 5_242_880; // 5MB

    private PDO $db;

    public function __construct()
    {
        $this->db = geekhost_db();
        $this->ensureTables();
    }

    /** @return array<string, string> */
    public static function statusLabels(): array
    {
        return [
            'open' => 'Aberto',
            'in_progress' => 'Em atendimento',
            'awaiting_client' => 'Aguardando resposta',
            'resolved' => 'Resolvido',
            'closed' => 'Fechado',
        ];
    }

    /** @return array<string, string> */
    public static function priorityLabels(): array
    {
        return [
            'low' => 'Baixa',
            'normal' => 'Normal',
            'high' => 'Alta',
            'urgent' => 'Urgente',
        ];
    }

    public static function isAdminEmail(string $email): bool
    {
        $raw = (string) (function_exists('env') ? env('GEEKHOST_ADMIN_EMAILS', '') : '');
        if ($raw === '') {
            $raw = 'diretoria@geekhost.com.br';
        }
        $list = array_filter(array_map(
            static fn ($e) => strtolower(trim($e)),
            explode(',', $raw)
        ));
        return in_array(strtolower(trim($email)), $list, true);
    }

    /**
     * @param array{q?:string,status?:string,category?:string,page?:int,perPage?:int} $filters
     * @return array{items:list<array<string,mixed>>, total:int, page:int, perPage:int}
     */
    public function listForUser(int $userId, array $filters = []): array
    {
        return $this->listTickets($filters, $userId);
    }

    /**
     * @param array{q?:string,status?:string,category?:string,priority?:string,page?:int,perPage?:int} $filters
     * @return array{items:list<array<string,mixed>>, total:int, page:int, perPage:int}
     */
    public function listForAdmin(array $filters = []): array
    {
        return $this->listTickets($filters, null);
    }

    /** @return array<string, int> */
    public function adminCounts(): array
    {
        $counts = [
            'open' => 0,
            'in_progress' => 0,
            'awaiting_client' => 0,
            'resolved' => 0,
            'closed' => 0,
            'urgent' => 0,
            'total' => 0,
        ];
        $rows = $this->db->query('SELECT status, COUNT(*) AS c FROM support_tickets GROUP BY status')->fetchAll() ?: [];
        foreach ($rows as $row) {
            $st = (string) $row['status'];
            $c = (int) $row['c'];
            $counts['total'] += $c;
            if (isset($counts[$st])) {
                $counts[$st] += $c;
            }
        }
        $urgent = $this->db->query(
            "SELECT COUNT(*) FROM support_tickets WHERE priority = 'urgent' AND status NOT IN ('resolved','closed')"
        )->fetchColumn();
        $counts['urgent'] = (int) $urgent;
        return $counts;
    }

    /**
     * @param array{subject:string,category:string,priority?:string,serviceId?:?string,body:string} $data
     * @param list<array{name:string,tmp:string,size:int,type:string}> $files
     */
    public function createTicket(int $userId, array $data, array $files = []): array
    {
        $subject = trim((string) ($data['subject'] ?? ''));
        $category = (string) ($data['category'] ?? '');
        $priority = (string) ($data['priority'] ?? 'normal');
        $body = trim((string) ($data['body'] ?? ''));
        $servicePublicId = isset($data['serviceId']) ? trim((string) $data['serviceId']) : '';

        if ($subject === '' || mb_strlen($subject) > 160) {
            throw new InvalidArgumentException('Informe um assunto com até 160 caracteres.');
        }
        if (!isset(self::CATEGORIES[$category])) {
            throw new InvalidArgumentException('Categoria inválida.');
        }
        if (!in_array($priority, self::PRIORITIES, true)) {
            $priority = 'normal';
        }
        if ($body === '' || mb_strlen($body) < 10) {
            throw new InvalidArgumentException('Descreva o problema com pelo menos 10 caracteres.');
        }
        if (mb_strlen($body) > 8000) {
            throw new InvalidArgumentException('A descrição é muito longa.');
        }

        $serviceId = null;
        if ($servicePublicId !== '') {
            $stmt = $this->db->prepare('SELECT id FROM services WHERE public_id = :p AND user_id = :u LIMIT 1');
            $stmt->execute([':p' => $servicePublicId, ':u' => $userId]);
            $serviceId = $stmt->fetchColumn();
            if (!$serviceId) {
                throw new InvalidArgumentException('Serviço relacionado inválido.');
            }
            $serviceId = (int) $serviceId;
        }

        $now = gmdate('c');
        $publicId = $this->uuid();
        $number = $this->nextTicketNumber();

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO support_tickets (
                    public_id, number, user_id, service_id, subject, category, priority, status,
                    created_at, updated_at
                ) VALUES (
                    :p, :n, :u, :s, :sub, :cat, :pri, :st, :c, :up
                )'
            );
            $stmt->execute([
                ':p' => $publicId,
                ':n' => $number,
                ':u' => $userId,
                ':s' => $serviceId,
                ':sub' => $subject,
                ':cat' => $category,
                ':pri' => $priority,
                ':st' => 'open',
                ':c' => $now,
                ':up' => $now,
            ]);
            $ticketId = (int) $this->db->lastInsertId();

            $messageId = $this->insertMessage($ticketId, $userId, 'client', $body, false, $now);
            foreach ($files as $file) {
                $this->storeAttachment($messageId, $file);
            }
            $this->logEvent($ticketId, $userId, 'client', 'created', ['number' => $number], $now);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $this->notifyAdmins(
            'ticket_opened',
            'Novo ticket #' . $number,
            $subject,
            $publicId,
            $userId
        );
        geekhost_log('ticket_created', ['number' => $number, 'user_id' => $userId]);
        return $this->getTicketForUser($userId, $publicId) ?? [];
    }

    public function getTicketForUser(int $userId, string $publicId): ?array
    {
        $row = $this->findTicketRow($publicId);
        if (!$row || (int) $row['user_id'] !== $userId) {
            return null;
        }
        return $this->presentTicket($row, $userId);
    }

    public function getTicketForAdmin(string $publicId): ?array
    {
        $row = $this->findTicketRow($publicId);
        if (!$row) {
            return null;
        }
        return $this->presentTicket($row, null, true);
    }

    /** @return list<array<string, mixed>> */
    public function listMessages(int $ticketId, bool $includeInternal = false): array
    {
        $sql = 'SELECT m.*, u.name_enc, u.email, u.photo_path
                FROM support_messages m
                LEFT JOIN users u ON u.id = m.author_id
                WHERE m.ticket_id = :t';
        if (!$includeInternal) {
            $sql .= ' AND m.is_internal = 0';
        }
        $sql .= ' ORDER BY m.id ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':t' => $ticketId]);
        $rows = $stmt->fetchAll() ?: [];
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->presentMessage($row);
        }
        return $out;
    }

    /**
     * @param list<array{name:string,tmp:string,size:int,type:string}> $files
     */
    public function addClientReply(int $userId, string $ticketPublicId, string $body, array $files = []): array
    {
        $ticket = $this->findTicketRow($ticketPublicId);
        if (!$ticket || (int) $ticket['user_id'] !== $userId) {
            throw new InvalidArgumentException('Ticket não encontrado.');
        }
        if (in_array((string) $ticket['status'], ['closed'], true)) {
            throw new InvalidArgumentException('Este ticket está fechado.');
        }
        $body = trim($body);
        if ($body === '' && $files === []) {
            throw new InvalidArgumentException('Escreva uma mensagem ou anexe um arquivo.');
        }
        if (mb_strlen($body) > 8000) {
            throw new InvalidArgumentException('Mensagem muito longa.');
        }

        $now = gmdate('c');
        $this->db->beginTransaction();
        try {
            $messageId = $this->insertMessage((int) $ticket['id'], $userId, 'client', $body !== '' ? $body : '(anexo)', false, $now);
            foreach ($files as $file) {
                $this->storeAttachment($messageId, $file);
            }
            $newStatus = in_array((string) $ticket['status'], ['awaiting_client', 'resolved'], true)
                ? 'open'
                : (string) $ticket['status'];
            $this->touchTicket((int) $ticket['id'], $newStatus, $now);
            $this->logEvent((int) $ticket['id'], $userId, 'client', 'client_reply', [], $now);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        $this->notifyAdmins(
            'client_reply',
            'Resposta no ticket #' . $ticket['number'],
            'O cliente respondeu ao ticket.',
            $ticketPublicId,
            $userId
        );
        return $this->getTicketForUser($userId, $ticketPublicId) ?? [];
    }

    /**
     * @param list<array{name:string,tmp:string,size:int,type:string}> $files
     */
    public function addStaffReply(int $staffUserId, string $ticketPublicId, string $body, bool $internal, array $files = []): array
    {
        $ticket = $this->findTicketRow($ticketPublicId);
        if (!$ticket) {
            throw new InvalidArgumentException('Ticket não encontrado.');
        }
        $body = trim($body);
        if ($body === '' && $files === []) {
            throw new InvalidArgumentException('Escreva uma mensagem ou anexe um arquivo.');
        }
        $now = gmdate('c');
        $this->db->beginTransaction();
        try {
            $messageId = $this->insertMessage(
                (int) $ticket['id'],
                $staffUserId,
                'staff',
                $body !== '' ? $body : '(anexo)',
                $internal,
                $now
            );
            foreach ($files as $file) {
                $this->storeAttachment($messageId, $file);
            }
            if (!$internal) {
                $status = (string) $ticket['status'] === 'closed' ? 'closed' : 'awaiting_client';
                if ((string) $ticket['status'] === 'open') {
                    $status = 'in_progress';
                }
                if ((string) $ticket['status'] === 'in_progress' || (string) $ticket['status'] === 'awaiting_client') {
                    $status = 'awaiting_client';
                }
                $this->db->prepare(
                    'UPDATE support_tickets SET status = :st, assignee_id = COALESCE(assignee_id, :a), updated_at = :u WHERE id = :id'
                )->execute([':st' => $status, ':a' => $staffUserId, ':u' => $now, ':id' => $ticket['id']]);
            } else {
                $this->touchTicket((int) $ticket['id'], (string) $ticket['status'], $now);
            }
            $this->logEvent((int) $ticket['id'], $staffUserId, 'staff', $internal ? 'internal_note' : 'staff_reply', [], $now);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        if (!$internal) {
            $this->createNotification(
                (int) $ticket['user_id'],
                'staff_reply',
                'Seu ticket #' . $ticket['number'] . ' recebeu uma nova resposta.',
                'Abra o chamado para ver a mensagem da equipe GeekHost.',
                $ticketPublicId
            );
            $this->maybeEmailUser(
                (int) $ticket['user_id'],
                'Resposta no ticket #' . $ticket['number'],
                'Seu ticket #' . $ticket['number'] . ' recebeu uma nova resposta. Acesse a área do cliente para visualizar.'
            );
        }
        return $this->getTicketForAdmin($ticketPublicId) ?? [];
    }

    public function assignTicket(int $staffUserId, string $ticketPublicId, ?int $assigneeId): array
    {
        $ticket = $this->findTicketRow($ticketPublicId);
        if (!$ticket) {
            throw new InvalidArgumentException('Ticket não encontrado.');
        }
        $now = gmdate('c');
        $this->db->prepare('UPDATE support_tickets SET assignee_id = :a, updated_at = :u WHERE id = :id')
            ->execute([':a' => $assigneeId, ':u' => $now, ':id' => $ticket['id']]);
        $this->logEvent((int) $ticket['id'], $staffUserId, 'staff', 'assigned', ['assignee_id' => $assigneeId], $now);
        return $this->getTicketForAdmin($ticketPublicId) ?? [];
    }

    public function setStatus(int $actorId, string $actorRole, string $ticketPublicId, string $status, ?int $ownerUserId = null): array
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Status inválido.');
        }
        $ticket = $this->findTicketRow($ticketPublicId);
        if (!$ticket) {
            throw new InvalidArgumentException('Ticket não encontrado.');
        }
        if ($actorRole === 'client') {
            if ($ownerUserId === null || (int) $ticket['user_id'] !== $ownerUserId) {
                throw new InvalidArgumentException('Ticket não encontrado.');
            }
            if (!in_array($status, ['resolved', 'open'], true)) {
                throw new InvalidArgumentException('Você só pode marcar como resolvido ou solicitar reabertura.');
            }
            if ($status === 'open' && !in_array((string) $ticket['status'], ['resolved', 'closed'], true)) {
                throw new InvalidArgumentException('Este ticket não precisa de reabertura.');
            }
        }
        $now = gmdate('c');
        $closedAt = in_array($status, ['resolved', 'closed'], true) ? $now : null;
        $this->db->prepare(
            'UPDATE support_tickets SET status = :st, closed_at = :cl, updated_at = :u WHERE id = :id'
        )->execute([':st' => $status, ':cl' => $closedAt, ':u' => $now, ':id' => $ticket['id']]);
        $this->logEvent((int) $ticket['id'], $actorId, $actorRole, 'status_changed', ['status' => $status], $now);

        $labels = self::statusLabels();
        $statusLabel = $labels[$status] ?? $status;
        if ($actorRole === 'staff') {
            $this->createNotification(
                (int) $ticket['user_id'],
                'status_changed',
                'Ticket #' . $ticket['number'] . ': ' . $statusLabel,
                'O status do seu chamado foi atualizado.',
                $ticketPublicId
            );
        } else {
            $this->notifyAdmins(
                'status_changed',
                'Ticket #' . $ticket['number'] . ': ' . $statusLabel,
                'O cliente atualizou o status do chamado.',
                $ticketPublicId,
                $actorId
            );
        }

        return $actorRole === 'client'
            ? ($this->getTicketForUser($ownerUserId ?? 0, $ticketPublicId) ?? [])
            : ($this->getTicketForAdmin($ticketPublicId) ?? []);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listNotifications(int $userId, int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM support_notifications WHERE user_id = :u ORDER BY id DESC LIMIT ' . max(1, min(50, $limit))
        );
        $stmt->execute([':u' => $userId]);
        $out = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $out[] = [
                'id' => $row['public_id'],
                'type' => $row['type'],
                'title' => $row['title'],
                'body' => $row['body'],
                'ticketId' => $row['ticket_public_id'],
                'read' => (bool) ((int) $row['is_read']),
                'createdAt' => $row['created_at'],
            ];
        }
        return $out;
    }

    public function unreadNotificationCount(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM support_notifications WHERE user_id = :u AND is_read = 0');
        $stmt->execute([':u' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public function markNotificationsRead(int $userId, ?string $notificationPublicId = null): void
    {
        if ($notificationPublicId) {
            $this->db->prepare(
                'UPDATE support_notifications SET is_read = 1 WHERE user_id = :u AND public_id = :p'
            )->execute([':u' => $userId, ':p' => $notificationPublicId]);
            return;
        }
        $this->db->prepare('UPDATE support_notifications SET is_read = 1 WHERE user_id = :u AND is_read = 0')
            ->execute([':u' => $userId]);
    }

    /**
     * Normalize $_FILES['attachments'] (or single file) into service file list.
     *
     * @return list<array{name:string,tmp:string,size:int,type:string}>
     */
    public static function collectUploadedFiles(string $field = 'attachments'): array
    {
        if (empty($_FILES[$field])) {
            return [];
        }
        $bag = $_FILES[$field];
        $files = [];
        if (is_array($bag['name'])) {
            foreach ($bag['name'] as $i => $name) {
                if (($bag['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                if (($bag['error'][$i] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                    throw new InvalidArgumentException('Falha no upload de um anexo.');
                }
                $files[] = [
                    'name' => (string) $name,
                    'tmp' => (string) $bag['tmp_name'][$i],
                    'size' => (int) $bag['size'][$i],
                    'type' => (string) ($bag['type'][$i] ?? ''),
                ];
            }
        } else {
            if (($bag['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                return [];
            }
            if (($bag['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException('Falha no upload do anexo.');
            }
            $files[] = [
                'name' => (string) $bag['name'],
                'tmp' => (string) $bag['tmp_name'],
                'size' => (int) $bag['size'],
                'type' => (string) ($bag['type'] ?? ''),
            ];
        }
        if (count($files) > 5) {
            throw new InvalidArgumentException('No máximo 5 anexos por mensagem.');
        }
        return $files;
    }

    public function markRead(int $userId, string $ticketPublicId, string $role): void
    {
        $ticket = $this->findTicketRow($ticketPublicId);
        if (!$ticket) {
            return;
        }
        if ($role === 'client' && (int) $ticket['user_id'] !== $userId) {
            return;
        }
        $col = $role === 'client' ? 'client_last_read_at' : 'staff_last_read_at';
        $this->db->prepare("UPDATE support_tickets SET {$col} = :t WHERE id = :id")
            ->execute([':t' => gmdate('c'), ':id' => $ticket['id']]);
    }

    /** @return array{path:string,name:string,mime:string}|null */
    public function resolveAttachmentDownload(string $attachmentPublicId, int $userId, bool $isAdmin): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT a.*, m.ticket_id, m.is_internal, t.user_id
             FROM support_attachments a
             INNER JOIN support_messages m ON m.id = a.message_id
             INNER JOIN support_tickets t ON t.id = m.ticket_id
             WHERE a.public_id = :p LIMIT 1'
        );
        $stmt->execute([':p' => $attachmentPublicId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        if (!$isAdmin) {
            if ((int) $row['user_id'] !== $userId || (int) $row['is_internal'] === 1) {
                return null;
            }
        }
        $path = $this->storageRoot() . DIRECTORY_SEPARATOR . (string) $row['stored_name'];
        if (!is_file($path)) {
            return null;
        }
        return [
            'path' => $path,
            'name' => (string) $row['original_name'],
            'mime' => (string) $row['mime_type'],
        ];
    }

    /** @return list<array<string, mixed>> */
    public function listUserServices(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT public_id, plan_code, domain, status FROM services WHERE user_id = :u ORDER BY id DESC');
        $stmt->execute([':u' => $userId]);
        $rows = $stmt->fetchAll() ?: [];
        return array_map(static fn ($r) => [
            'id' => $r['public_id'],
            'label' => trim(($r['plan_code'] ?? '') . ' · ' . ($r['domain'] ?: 'sem domínio')),
            'status' => $r['status'] ?? '',
        ], $rows);
    }

    /**
     * @param array{q?:string,status?:string,category?:string,priority?:string,page?:int,perPage?:int} $filters
     * @return array{items:list<array<string,mixed>>, total:int, page:int, perPage:int}
     */
    private function listTickets(array $filters, ?int $userId): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(50, max(5, (int) ($filters['perPage'] ?? 20)));
        $where = ['1=1'];
        $params = [];
        if ($userId !== null) {
            $where[] = 't.user_id = :uid';
            $params[':uid'] = $userId;
        }
        $status = (string) ($filters['status'] ?? '');
        if ($status !== '' && in_array($status, self::STATUSES, true)) {
            $where[] = 't.status = :st';
            $params[':st'] = $status;
        }
        $category = (string) ($filters['category'] ?? '');
        if ($category !== '' && isset(self::CATEGORIES[$category])) {
            $where[] = 't.category = :cat';
            $params[':cat'] = $category;
        }
        $priority = (string) ($filters['priority'] ?? '');
        if ($priority !== '' && in_array($priority, self::PRIORITIES, true)) {
            $where[] = 't.priority = :pri';
            $params[':pri'] = $priority;
        }
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(CAST(t.number AS CHAR) LIKE :q OR t.subject LIKE :q OR u.email LIKE :q)';
            $params[':q'] = '%' . $q . '%';
        }

        $sqlWhere = implode(' AND ', $where);
        $countSql = "SELECT COUNT(*) FROM support_tickets t LEFT JOIN users u ON u.id = t.user_id WHERE {$sqlWhere}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $offset = ($page - 1) * $perPage;
        $sql = "SELECT t.*, u.email AS user_email, u.name_enc AS user_name_enc, u.photo_path AS user_photo
                FROM support_tickets t
                LEFT JOIN users u ON u.id = t.user_id
                WHERE {$sqlWhere}
                ORDER BY t.updated_at DESC
                LIMIT {$perPage} OFFSET {$offset}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $items = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $items[] = $this->presentTicket($row, $userId, $userId === null, false);
        }
        return ['items' => $items, 'total' => $total, 'page' => $page, 'perPage' => $perPage];
    }

    /** @return array<string, mixed>|null */
    private function findTicketRow(string $publicId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT t.*, u.email AS user_email, u.name_enc AS user_name_enc, u.photo_path AS user_photo
             FROM support_tickets t
             LEFT JOIN users u ON u.id = t.user_id
             WHERE t.public_id = :p LIMIT 1'
        );
        $stmt->execute([':p' => $publicId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function insertMessage(int $ticketId, int $authorId, string $role, string $body, bool $internal, string $now): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO support_messages (
                public_id, ticket_id, author_id, author_role, body, is_internal, created_at
            ) VALUES (:p, :t, :a, :r, :b, :i, :c)'
        );
        $stmt->execute([
            ':p' => $this->uuid(),
            ':t' => $ticketId,
            ':a' => $authorId,
            ':r' => $role,
            ':b' => $body,
            ':i' => $internal ? 1 : 0,
            ':c' => $now,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** @param array{name:string,tmp:string,size:int,type:string} $file */
    private function storeAttachment(int $messageId, array $file): void
    {
        $size = (int) ($file['size'] ?? 0);
        $tmp = (string) ($file['tmp'] ?? '');
        $name = basename((string) ($file['name'] ?? 'arquivo'));
        if ($tmp === '' || (!is_uploaded_file($tmp) && !is_file($tmp))) {
            throw new InvalidArgumentException('Arquivo inválido.');
        }
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new InvalidArgumentException('Cada anexo deve ter no máximo 5 MB.');
        }
        $blockedExt = ['php', 'phtml', 'phar', 'exe', 'bat', 'cmd', 'sh', 'js', 'html', 'htm', 'svg'];
        $origExt = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (in_array($origExt, $blockedExt, true)) {
            throw new InvalidArgumentException('Extensão de arquivo não permitida.');
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) ($finfo->file($tmp) ?: ($file['type'] ?? ''));
        if (!isset(self::ALLOWED_MIME[$mime])) {
            throw new InvalidArgumentException('Tipo de arquivo não permitido.');
        }
        $ext = self::ALLOWED_MIME[$mime];
        $stored = bin2hex(random_bytes(16)) . '.' . $ext;
        $root = $this->storageRoot();
        if (!is_dir($root) && !mkdir($root, 0750, true) && !is_dir($root)) {
            throw new RuntimeException('Não foi possível salvar o anexo.');
        }
        $dest = $root . DIRECTORY_SEPARATOR . $stored;
        $moved = is_uploaded_file($tmp) ? @move_uploaded_file($tmp, $dest) : (@rename($tmp, $dest) || @copy($tmp, $dest));
        if (!$moved || !is_file($dest)) {
            throw new RuntimeException('Falha ao armazenar anexo.');
        }
        @chmod($dest, 0640);
        $stmt = $this->db->prepare(
            'INSERT INTO support_attachments (
                public_id, message_id, original_name, stored_name, mime_type, size_bytes, created_at
            ) VALUES (:p, :m, :o, :s, :mime, :sz, :c)'
        );
        $stmt->execute([
            ':p' => $this->uuid(),
            ':m' => $messageId,
            ':o' => mb_substr($name, 0, 180),
            ':s' => $stored,
            ':mime' => $mime,
            ':sz' => $size,
            ':c' => gmdate('c'),
        ]);
    }

    private function touchTicket(int $ticketId, string $status, string $now): void
    {
        $this->db->prepare('UPDATE support_tickets SET status = :st, updated_at = :u WHERE id = :id')
            ->execute([':st' => $status, ':u' => $now, ':id' => $ticketId]);
    }

    /** @param array<string, mixed> $meta */
    private function logEvent(int $ticketId, int $actorId, string $role, string $type, array $meta, string $now): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO support_ticket_events (ticket_id, actor_id, actor_role, event_type, meta_json, created_at)
             VALUES (:t, :a, :r, :e, :m, :c)'
        );
        $stmt->execute([
            ':t' => $ticketId,
            ':a' => $actorId,
            ':r' => $role,
            ':e' => $type,
            ':m' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            ':c' => $now,
        ]);
    }

    private function nextTicketNumber(): int
    {
        $max = (int) $this->db->query('SELECT COALESCE(MAX(number), 100000) FROM support_tickets')->fetchColumn();
        return max(100001, $max + 1);
    }

    /** @param array<string, mixed> $row */
    private function presentTicket(array $row, ?int $viewerUserId, bool $admin = false, bool $includeMessages = true): array
    {
        $labels = self::statusLabels();
        $priorityLabels = self::priorityLabels();
        $status = (string) ($row['status'] ?? 'open');
        $unread = 0;
        if ($viewerUserId !== null) {
            $lastRead = (string) ($row['client_last_read_at'] ?? '');
            if ($lastRead === '') {
                $stmt = $this->db->prepare(
                    "SELECT COUNT(*) FROM support_messages
                     WHERE ticket_id = :t AND is_internal = 0 AND author_role = 'staff'"
                );
                $stmt->execute([':t' => $row['id']]);
            } else {
                $stmt = $this->db->prepare(
                    "SELECT COUNT(*) FROM support_messages
                     WHERE ticket_id = :t AND is_internal = 0 AND author_role = 'staff'
                     AND created_at > :r"
                );
                $stmt->execute([':t' => $row['id'], ':r' => $lastRead]);
            }
            $unread = (int) $stmt->fetchColumn();
        } elseif ($admin) {
            $lastRead = (string) ($row['staff_last_read_at'] ?? '');
            if ($lastRead === '') {
                $stmt = $this->db->prepare(
                    "SELECT COUNT(*) FROM support_messages
                     WHERE ticket_id = :t AND is_internal = 0 AND author_role = 'client'"
                );
                $stmt->execute([':t' => $row['id']]);
            } else {
                $stmt = $this->db->prepare(
                    "SELECT COUNT(*) FROM support_messages
                     WHERE ticket_id = :t AND is_internal = 0 AND author_role = 'client'
                     AND created_at > :r"
                );
                $stmt->execute([':t' => $row['id'], ':r' => $lastRead]);
            }
            $unread = (int) $stmt->fetchColumn();
        }

        $name = '';
        if (!empty($row['user_name_enc']) && function_exists('geekhost_decrypt')) {
            $name = (string) (geekhost_decrypt((string) $row['user_name_enc']) ?? '');
        }

        return [
            'id' => $row['public_id'],
            'number' => (int) $row['number'],
            'numberLabel' => '#' . $row['number'],
            'subject' => $row['subject'],
            'category' => $row['category'],
            'categoryLabel' => self::CATEGORIES[$row['category']] ?? $row['category'],
            'priority' => $row['priority'],
            'priorityLabel' => $priorityLabels[$row['priority']] ?? $row['priority'],
            'status' => $status,
            'statusLabel' => $labels[$status] ?? $status,
            'createdAt' => $row['created_at'],
            'updatedAt' => $row['updated_at'],
            'closedAt' => $row['closed_at'] ?? null,
            'unread' => $unread,
            'userEmail' => $row['user_email'] ?? null,
            'userName' => $name,
            'userPhoto' => $row['user_photo'] ?? null,
            'assigneeId' => isset($row['assignee_id']) ? (int) $row['assignee_id'] : null,
            'serviceId' => isset($row['service_id']) ? (int) $row['service_id'] : null,
            'messages' => $includeMessages && ($admin || $viewerUserId !== null)
                ? $this->listMessages((int) $row['id'], $admin)
                : [],
        ];
    }

    /** @param array<string, mixed> $row */
    private function presentMessage(array $row): array
    {
        $name = 'Cliente';
        if (($row['author_role'] ?? '') === 'staff') {
            $name = 'Suporte GeekHost';
        } elseif (!empty($row['name_enc']) && function_exists('geekhost_decrypt')) {
            $decoded = geekhost_decrypt((string) $row['name_enc']);
            if (is_string($decoded) && $decoded !== '') {
                $name = $decoded;
            }
        } elseif (!empty($row['email'])) {
            $name = (string) $row['email'];
        }

        $atts = $this->db->prepare('SELECT * FROM support_attachments WHERE message_id = :m ORDER BY id ASC');
        $atts->execute([':m' => $row['id']]);
        $attachments = [];
        foreach ($atts->fetchAll() ?: [] as $a) {
            $attachments[] = [
                'id' => $a['public_id'],
                'name' => $a['original_name'],
                'size' => (int) $a['size_bytes'],
                'mime' => $a['mime_type'],
            ];
        }

        return [
            'id' => $row['public_id'],
            'role' => $row['author_role'],
            'authorName' => $name,
            'authorPhoto' => $row['photo_path'] ?? null,
            'body' => $row['body'],
            'internal' => (bool) ((int) ($row['is_internal'] ?? 0)),
            'createdAt' => $row['created_at'],
            'attachments' => $attachments,
        ];
    }

    private function storageRoot(): string
    {
        $root = geekhost_project_root() . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'tickets';
        return $root;
    }

    private function ensureTables(): void
    {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $id = $driver === 'mysql'
            ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY'
            : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $int = $driver === 'mysql' ? 'BIGINT UNSIGNED' : 'INTEGER';

        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS support_tickets (
                id {$id},
                public_id VARCHAR(36) NOT NULL,
                number INTEGER NOT NULL,
                user_id {$int} NOT NULL,
                service_id {$int} DEFAULT NULL,
                subject VARCHAR(190) NOT NULL,
                category VARCHAR(40) NOT NULL,
                priority VARCHAR(20) NOT NULL DEFAULT 'normal',
                status VARCHAR(40) NOT NULL DEFAULT 'open',
                assignee_id {$int} DEFAULT NULL,
                client_last_read_at TEXT DEFAULT NULL,
                staff_last_read_at TEXT DEFAULT NULL,
                closed_at TEXT DEFAULT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )"
        );
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS support_messages (
                id {$id},
                public_id VARCHAR(36) NOT NULL,
                ticket_id {$int} NOT NULL,
                author_id {$int} NOT NULL,
                author_role VARCHAR(20) NOT NULL,
                body TEXT NOT NULL,
                is_internal INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL
            )"
        );
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS support_attachments (
                id {$id},
                public_id VARCHAR(36) NOT NULL,
                message_id {$int} NOT NULL,
                original_name VARCHAR(190) NOT NULL,
                stored_name VARCHAR(190) NOT NULL,
                mime_type VARCHAR(120) NOT NULL,
                size_bytes INTEGER NOT NULL,
                created_at TEXT NOT NULL
            )"
        );
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS support_ticket_events (
                id {$id},
                ticket_id {$int} NOT NULL,
                actor_id {$int} NOT NULL,
                actor_role VARCHAR(20) NOT NULL,
                event_type VARCHAR(60) NOT NULL,
                meta_json TEXT DEFAULT NULL,
                created_at TEXT NOT NULL
            )"
        );
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS support_notifications (
                id {$id},
                public_id VARCHAR(36) NOT NULL,
                user_id {$int} NOT NULL,
                type VARCHAR(60) NOT NULL,
                title VARCHAR(190) NOT NULL,
                body TEXT NOT NULL,
                ticket_public_id VARCHAR(36) DEFAULT NULL,
                is_read INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL
            )"
        );

        foreach ([
            'CREATE UNIQUE INDEX idx_support_tickets_public ON support_tickets (public_id)',
            'CREATE UNIQUE INDEX idx_support_tickets_number ON support_tickets (number)',
            'CREATE INDEX idx_support_tickets_user ON support_tickets (user_id)',
            'CREATE INDEX idx_support_tickets_status ON support_tickets (status)',
            'CREATE UNIQUE INDEX idx_support_messages_public ON support_messages (public_id)',
            'CREATE INDEX idx_support_messages_ticket ON support_messages (ticket_id)',
            'CREATE UNIQUE INDEX idx_support_attachments_public ON support_attachments (public_id)',
            'CREATE INDEX idx_support_attachments_message ON support_attachments (message_id)',
            'CREATE UNIQUE INDEX idx_support_notifications_public ON support_notifications (public_id)',
            'CREATE INDEX idx_support_notifications_user ON support_notifications (user_id)',
        ] as $sql) {
            try {
                $this->db->exec($sql);
            } catch (Throwable $e) {
                // exists
            }
        }

        $gitignore = $this->storageRoot() . DIRECTORY_SEPARATOR . '.gitignore';
        if (!is_dir(dirname($gitignore))) {
            @mkdir(dirname($gitignore), 0750, true);
        }
        if (!is_file($gitignore)) {
            @file_put_contents($gitignore, "*\n!.gitignore\n");
        }
    }

    private function createNotification(int $userId, string $type, string $title, string $body, ?string $ticketPublicId): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO support_notifications (
                public_id, user_id, type, title, body, ticket_public_id, is_read, created_at
            ) VALUES (:p, :u, :t, :ti, :b, :tp, 0, :c)'
        );
        $stmt->execute([
            ':p' => $this->uuid(),
            ':u' => $userId,
            ':t' => $type,
            ':ti' => mb_substr($title, 0, 180),
            ':b' => $body,
            ':tp' => $ticketPublicId,
            ':c' => gmdate('c'),
        ]);
    }

    private function notifyAdmins(string $type, string $title, string $body, string $ticketPublicId, int $exceptUserId): void
    {
        $raw = (string) (function_exists('env') ? env('GEEKHOST_ADMIN_EMAILS', '') : '');
        if ($raw === '') {
            $raw = 'diretoria@geekhost.com.br';
        }
        $emails = array_filter(array_map(
            static fn ($e) => strtolower(trim($e)),
            explode(',', $raw)
        ));
        foreach ($emails as $email) {
            $stmt = $this->db->prepare('SELECT id FROM users WHERE email = :e LIMIT 1');
            $stmt->execute([':e' => $email]);
            $id = (int) ($stmt->fetchColumn() ?: 0);
            if ($id > 0 && $id !== $exceptUserId) {
                $this->createNotification($id, $type, $title, $body, $ticketPublicId);
            }
        }
    }

    private function maybeEmailUser(int $userId, string $subject, string $body): void
    {
        $stmt = $this->db->prepare('SELECT email FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $email = (string) ($stmt->fetchColumn() ?: '');
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $link = function_exists('url') ? url('/painel/suporte') : '/painel/suporte';
        $message = $body . "\n\nAcesse: " . $link . "\n\n— GeekHost";
        $headers = "From: GeekHost <noreply@geekhost.com.br>\r\nContent-Type: text/plain; charset=UTF-8\r\n";
        try {
            @mail($email, $subject, $message, $headers);
        } catch (Throwable $e) {
            // optional channel
        }
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
