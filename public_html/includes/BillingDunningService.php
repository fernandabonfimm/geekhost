<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/plans.php';
require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/ProvisioningService.php';

/**
 * Collection schedule:
 * day 15, 30 and 45 — reminder email
 * day 60 — final email, DirectAdmin backup, hosting suspension
 */
final class BillingDunningService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = geekhost_db();
        $this->ensureTable();
    }

    /** @return array{checked:int,sent:int,cancelled:int} */
    public function run(): array
    {
        $checked = 0;
        $sent = 0;
        $cancelled = 0;
        $rows = $this->db->query(
            "SELECT o.*, u.email AS user_email,
                (SELECT COUNT(*) FROM orders p
                  WHERE p.user_id = o.user_id AND p.status = 'paid_pending_provisioning') AS paid_count
             FROM orders o
             INNER JOIN users u ON u.id = o.user_id
             WHERE o.status IN ('awaiting_payment', 'overdue')
               AND o.user_id IS NOT NULL"
        )->fetchAll() ?: [];

        foreach ($rows as $order) {
            $checked++;
            $days = $this->daysOverdue($order);
            $stage = $this->stageForDays($days);
            if ($stage === null) {
                continue;
            }
            $orderId = (int) $order['id'];
            if ($this->alreadySent($orderId, $stage)) {
                continue;
            }
            $isFirstInvoice = (int) ($order['paid_count'] ?? 0) === 0;
            $this->sendStage($order, $stage, $isFirstInvoice);
            $sent++;
            if ($stage === 60) {
                $this->cancelHosting($order);
                $cancelled++;
            }
        }

        return compact('checked', 'sent', 'cancelled');
    }

    /** @param array<string, mixed> $order */
    private function daysOverdue(array $order): int
    {
        $raw = trim((string) ($order['due_date'] ?? ''));
        if ($raw === '') {
            $raw = substr((string) ($order['created_at'] ?? ''), 0, 10);
        }
        $due = strtotime($raw . ' 00:00:00 UTC');
        if ($due === false) {
            return 0;
        }
        return (int) floor((time() - $due) / 86400);
    }

    private function stageForDays(int $days): ?int
    {
        if ($days >= 60) {
            return 60;
        }
        if ($days >= 45) {
            return 45;
        }
        if ($days >= 30) {
            return 30;
        }
        if ($days >= 15) {
            return 15;
        }
        return null;
    }

    private function alreadySent(int $orderId, int $stage): bool
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM billing_notices WHERE order_id = :o AND stage >= :s LIMIT 1'
        );
        $stmt->execute([':o' => $orderId, ':s' => $stage]);
        return (bool) $stmt->fetchColumn();
    }

    /** @param array<string, mixed> $order */
    private function sendStage(array $order, int $stage, bool $isFirstInvoice): void
    {
        $amount = geekhost_format_brl(((int) ($order['amount_cents'] ?? 0)) / 100);
        $due = trim((string) ($order['due_date'] ?? ''));
        if ($due === '') {
            $due = substr((string) ($order['created_at'] ?? ''), 0, 10);
        }
        $payUrl = trim((string) ($order['asaas_invoice_url'] ?? ''));
        if ($payUrl === '') {
            $payUrl = function_exists('geekhost_public_url')
                ? geekhost_public_url('/painel/segunda-via')
                : 'https://geekhost.com.br/painel/segunda-via';
        }

        if ($stage === 60) {
            $headline = 'Sua hospedagem foi cancelada';
            $intro = 'Passaram 2 meses sem o pagamento desta fatura. A hospedagem foi suspensa e o site saiu do ar. Guardamos um backup da conta no servidor da revenda. Se quiser reativar, fale com o suporte e quite o valor em aberto.';
            $subject = 'Sua hospedagem GeekHost foi cancelada';
            $tone = 'cancel';
            $kind = 'cancelled';
        } elseif ($stage === 15 && $isFirstInvoice) {
            $headline = 'Sua primeira fatura ainda está em aberto';
            $intro = 'Faz 15 dias que a primeira parcela da hospedagem não foi paga. Sem o pagamento o site não é publicado e, depois de 60 dias, a conta é cancelada.';
            $subject = 'GeekHost: sua primeira parcela está em aberto';
            $tone = 'remind';
            $kind = 'first_due';
        } else {
            $headline = 'Fatura com ' . $stage . ' dias de atraso';
            $intro = 'Sua hospedagem segue com pagamento em atraso. Sem regularizar, o site pode sair do ar. Este aviso é enviado a cada 15 dias. Depois de 60 dias a conta é suspensa e cancelada.';
            $subject = 'GeekHost: cobrança da hospedagem em atraso';
            $tone = 'remind';
            $kind = 'reminder';
        }

        $html = Mailer::billingHtml($headline, $intro, $amount, $due, $payUrl, $tone);
        Mailer::send((string) $order['user_email'], $subject, $html);
        $this->db->prepare(
            'INSERT INTO billing_notices (order_id, user_id, stage, kind, sent_at) VALUES (:o, :u, :s, :k, :t)'
        )->execute([
            ':o' => (int) $order['id'],
            ':u' => (int) $order['user_id'],
            ':s' => $stage,
            ':k' => $kind,
            ':t' => gmdate('c'),
        ]);
    }

    /** @param array<string, mixed> $order */
    private function cancelHosting(array $order): void
    {
        $userId = (int) $order['user_id'];
        $now = gmdate('c');
        try {
            $provision = new ProvisioningService();
            $backup = $provision->backupUserAccount($userId);
            geekhost_log('billing_backup', ['user_id' => $userId, 'result' => $backup]);
            $provision->suspendUserHosting($userId, 'nonpayment_60d');
        } catch (Throwable $e) {
            geekhost_log('billing_cancel_error', ['user_id' => $userId, 'error' => $e->getMessage()]);
        }
        $this->db->prepare(
            "UPDATE users SET hosting_status = 'suspended', suspended_at = :t, suspend_reason = 'nonpayment_60d', updated_at = :t WHERE id = :id"
        )->execute([':t' => $now, ':id' => $userId]);
    }

    private function ensureTable(): void
    {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $id = $driver === 'mysql'
            ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY'
            : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $int = $driver === 'mysql' ? 'BIGINT UNSIGNED' : 'INTEGER';
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS billing_notices (
                id {$id},
                order_id {$int} NOT NULL,
                user_id {$int} NOT NULL,
                stage INTEGER NOT NULL,
                kind VARCHAR(40) NOT NULL,
                sent_at TEXT NOT NULL
            )"
        );
        try {
            $this->db->exec('CREATE UNIQUE INDEX idx_billing_notices_order_stage ON billing_notices (order_id, stage)');
        } catch (Throwable $e) {
            // exists
        }
    }
}
