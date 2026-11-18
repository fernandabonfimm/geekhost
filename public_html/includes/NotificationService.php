<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

final class NotificationService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = geekhost_db();
        $this->ensureTables();
    }

    public function notify(int $userId, string $type, string $title, string $body, ?string $link = null): void
    {
        if ($userId <= 0) {
            return;
        }
        $stmt = $this->db->prepare(
            'INSERT INTO support_notifications (
                public_id, user_id, type, title, body, ticket_public_id, link, is_read, created_at
            ) VALUES (:p, :u, :t, :ti, :b, NULL, :l, 0, :c)'
        );
        $stmt->execute([
            ':p' => $this->uuid(),
            ':u' => $userId,
            ':t' => $type,
            ':ti' => mb_substr($title, 0, 180),
            ':b' => $body,
            ':l' => $link,
            ':c' => gmdate('c'),
        ]);
    }

    public function notifyTicket(int $userId, string $type, string $title, string $body, string $ticketPublicId): void
    {
        $link = function_exists('url')
            ? url('/painel/suporte?ticket=' . rawurlencode($ticketPublicId))
            : '/painel/suporte?ticket=' . rawurlencode($ticketPublicId);
        $stmt = $this->db->prepare(
            'INSERT INTO support_notifications (
                public_id, user_id, type, title, body, ticket_public_id, link, is_read, created_at
            ) VALUES (:p, :u, :t, :ti, :b, :tp, :l, 0, :c)'
        );
        $stmt->execute([
            ':p' => $this->uuid(),
            ':u' => $userId,
            ':t' => $type,
            ':ti' => mb_substr($title, 0, 180),
            ':b' => $body,
            ':tp' => $ticketPublicId,
            ':l' => $link,
            ':c' => gmdate('c'),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listForUser(int $userId, int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM support_notifications WHERE user_id = :u ORDER BY id DESC LIMIT ' . max(1, min(50, $limit))
        );
        $stmt->execute([':u' => $userId]);
        $out = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $link = (string) ($row['link'] ?? '');
            if ($link === '' && !empty($row['ticket_public_id'])) {
                $link = function_exists('url')
                    ? url('/painel/suporte?ticket=' . rawurlencode((string) $row['ticket_public_id']))
                    : '/painel/suporte?ticket=' . rawurlencode((string) $row['ticket_public_id']);
            }
            $out[] = [
                'id' => $row['public_id'],
                'type' => $row['type'],
                'title' => $row['title'],
                'body' => $row['body'],
                'ticketId' => $row['ticket_public_id'],
                'link' => $link !== '' ? $link : (function_exists('url') ? url('/painel/') : '/painel/'),
                'read' => (bool) ((int) $row['is_read']),
                'createdAt' => $row['created_at'],
            ];
        }
        return $out;
    }

    public function unreadCount(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM support_notifications WHERE user_id = :u AND is_read = 0');
        $stmt->execute([':u' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public function markRead(int $userId, ?string $notificationPublicId = null): void
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

    private function ensureTables(): void
    {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $id = $driver === 'mysql'
            ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY'
            : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $int = $driver === 'mysql' ? 'BIGINT UNSIGNED' : 'INTEGER';

        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS support_notifications (
                id {$id},
                public_id VARCHAR(36) NOT NULL,
                user_id {$int} NOT NULL,
                type VARCHAR(60) NOT NULL,
                title VARCHAR(190) NOT NULL,
                body TEXT NOT NULL,
                ticket_public_id VARCHAR(36) DEFAULT NULL,
                link VARCHAR(255) DEFAULT NULL,
                is_read INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL
            )"
        );

        try {
            $this->db->exec('ALTER TABLE support_notifications ADD COLUMN link VARCHAR(255) DEFAULT NULL');
        } catch (Throwable $e) {
            // exists
        }

        foreach ([
            'CREATE UNIQUE INDEX idx_support_notifications_public ON support_notifications (public_id)',
            'CREATE INDEX idx_support_notifications_user ON support_notifications (user_id)',
        ] as $sql) {
            try {
                $this->db->exec($sql);
            } catch (Throwable $e) {
                // exists
            }
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
