<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/plans.php';

/**
 * Real dashboard metrics for /admin (from GeekHost DB — Asaas mirror).
 */
final class AdminMetricsService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = geekhost_db();
    }

    /**
     * @return array{from:string,to:string,prevFrom:string,prevTo:string,label:string}
     */
    public function resolveRange(string $preset, ?string $from = null, ?string $to = null): array
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $end = $now->setTime(23, 59, 59);
        $start = $now->setTime(0, 0, 0);

        switch ($preset) {
            case 'today':
                break;
            case 'yesterday':
                $start = $start->modify('-1 day');
                $end = $end->modify('-1 day');
                break;
            case '7d':
                $start = $start->modify('-6 days');
                break;
            case '30d':
                $start = $start->modify('-29 days');
                break;
            case 'this_month':
                $start = $start->modify('first day of this month');
                break;
            case 'last_month':
                $start = $start->modify('first day of last month');
                $end = $start->modify('last day of this month')->setTime(23, 59, 59);
                break;
            case '3m':
                $start = $start->modify('-89 days');
                break;
            case 'year':
                $start = $start->setDate((int) $start->format('Y'), 1, 1);
                break;
            case 'custom':
                if ($from && $to) {
                    $start = new DateTimeImmutable($from . ' 00:00:00', new DateTimeZone('UTC'));
                    $end = new DateTimeImmutable($to . ' 23:59:59', new DateTimeZone('UTC'));
                }
                break;
            default:
                $preset = '30d';
                $start = $start->modify('-29 days');
        }

        $seconds = max(1, $end->getTimestamp() - $start->getTimestamp());
        $prevEnd = $start->modify('-1 second');
        $prevStart = (new DateTimeImmutable('@' . ($prevEnd->getTimestamp() - $seconds)))->setTimezone(new DateTimeZone('UTC'));

        return [
            'from' => $start->format('c'),
            'to' => $end->format('c'),
            'prevFrom' => $prevStart->format('c'),
            'prevTo' => $prevEnd->format('c'),
            'label' => $preset,
        ];
    }

    /**
     * @param array{from:string,to:string,prevFrom:string,prevTo:string} $range
     * @return array<string, mixed>
     */
    public function overview(array $range): array
    {
        $gross = $this->sumOrders($range['from'], $range['to'], null);
        $grossPrev = $this->sumOrders($range['prevFrom'], $range['prevTo'], null);
        $received = $this->sumOrders($range['from'], $range['to'], 'paid');
        $receivedPrev = $this->sumOrders($range['prevFrom'], $range['prevTo'], 'paid');
        $open = $this->sumOrders($range['from'], $range['to'], 'open');
        $overdue = $this->sumOrders($range['from'], $range['to'], 'overdue');
        $paidCount = $this->countOrders($range['from'], $range['to'], 'paid');

        $mrr = $this->mrrCents();
        $clients = $this->clientStats($range['from'], $range['to']);
        $hosting = $this->hostingStats();

        return [
            'range' => $range,
            'financial' => [
                'grossCents' => $gross,
                'grossDeltaPct' => $this->deltaPct($gross, $grossPrev),
                'receivedCents' => $received,
                'receivedDeltaPct' => $this->deltaPct($received, $receivedPrev),
                'netCents' => null,
                'netNote' => 'Receita líquida indisponível até sincronizar taxas do gateway.',
                'mrrCents' => $mrr,
                'arrCents' => $mrr * 12,
                'avgTicketCents' => $paidCount > 0 ? (int) round($received / $paidCount) : 0,
                'openCents' => $open,
                'overdueCents' => $overdue,
                'refundsCents' => 0,
            ],
            'clients' => $clients,
            'hosting' => $hosting,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentActivity(int $limit = 25): array
    {
        $limit = max(1, min(100, $limit));
        $items = [];

        $orders = $this->db->query(
            "SELECT public_id, plan_name, status, amount_cents, created_at, paid_at, user_id
             FROM orders ORDER BY id DESC LIMIT {$limit}"
        )->fetchAll() ?: [];
        foreach ($orders as $o) {
            $items[] = [
                'type' => ($o['status'] ?? '') === 'paid_pending_provisioning' ? 'payment_approved' : 'sale',
                'title' => (($o['status'] ?? '') === 'paid_pending_provisioning' ? 'Pagamento aprovado' : 'Nova venda') . ' — ' . ($o['plan_name'] ?? ''),
                'amountCents' => (int) ($o['amount_cents'] ?? 0),
                'at' => $o['paid_at'] ?: $o['created_at'],
                'link' => '/admin/vendas?id=' . rawurlencode((string) $o['public_id']),
            ];
        }

        try {
            $hosts = $this->db->query(
                "SELECT public_id, status, da_username, updated_at FROM hosting_accounts ORDER BY id DESC LIMIT 15"
            )->fetchAll() ?: [];
            foreach ($hosts as $h) {
                $items[] = [
                    'type' => 'hosting',
                    'title' => 'Hospedagem ' . ($h['status'] ?? '') . ' — ' . ($h['da_username'] ?? ''),
                    'amountCents' => null,
                    'at' => $h['updated_at'],
                    'link' => '/admin/hospedagens?id=' . rawurlencode((string) $h['public_id']),
                ];
            }
        } catch (Throwable $e) {
            // table may not exist yet on old DBs
        }

        usort($items, static fn ($a, $b) => strcmp((string) ($b['at'] ?? ''), (string) ($a['at'] ?? '')));
        return array_slice($items, 0, $limit);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function salesSeries(string $from, string $to): array
    {
        $stmt = $this->db->prepare(
            "SELECT substr(created_at, 1, 10) AS day, SUM(amount_cents) AS total
             FROM orders
             WHERE created_at >= :f AND created_at <= :t
             GROUP BY substr(created_at, 1, 10)
             ORDER BY day ASC"
        );
        $stmt->execute([':f' => $from, ':t' => $to]);
        return $stmt->fetchAll() ?: [];
    }

    private function sumOrders(string $from, string $to, ?string $bucket): int
    {
        $sql = 'SELECT COALESCE(SUM(amount_cents), 0) FROM orders WHERE created_at >= :f AND created_at <= :t';
        if ($bucket === 'paid') {
            $sql = "SELECT COALESCE(SUM(amount_cents), 0) FROM orders
                    WHERE paid_at IS NOT NULL AND paid_at >= :f AND paid_at <= :t
                      AND status = 'paid_pending_provisioning'";
        } elseif ($bucket === 'open') {
            $sql .= " AND status IN ('awaiting_payment')";
        } elseif ($bucket === 'overdue') {
            $sql .= " AND status = 'overdue'";
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':f' => $from, ':t' => $to]);
        return (int) $stmt->fetchColumn();
    }

    private function countOrders(string $from, string $to, string $bucket): int
    {
        if ($bucket === 'paid') {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM orders
                 WHERE paid_at IS NOT NULL AND paid_at >= :f AND paid_at <= :t
                   AND status = 'paid_pending_provisioning'"
            );
            $stmt->execute([':f' => $from, ':t' => $to]);
            return (int) $stmt->fetchColumn();
        }
        return 0;
    }

    private function mrrCents(): int
    {
        $stmt = $this->db->query(
            "SELECT billing_period, renew_amount_cents, amount_cents
             FROM subscriptions WHERE status = 'active'"
        );
        $rows = $stmt ? ($stmt->fetchAll() ?: []) : [];
        $mrr = 0;
        foreach ($rows as $row) {
            $cents = (int) (($row['renew_amount_cents'] ?? 0) ?: ($row['amount_cents'] ?? 0));
            $period = geekhost_normalize_period((string) ($row['billing_period'] ?? 'mensal'));
            $mrr += match ($period) {
                'semestral' => (int) round($cents / 6),
                'anual' => (int) round($cents / 12),
                default => $cents,
            };
        }
        return $mrr;
    }

    /** @return array<string, int|float|null> */
    private function clientStats(string $from, string $to): array
    {
        $total = (int) $this->db->query('SELECT COUNT(*) FROM users')->fetchColumn();
        $active = (int) $this->db->query(
            "SELECT COUNT(*) FROM users WHERE hosting_status = 'active'"
        )->fetchColumn();
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM users WHERE created_at >= :f AND created_at <= :t');
        $stmt->execute([':f' => $from, ':t' => $to]);
        $new = (int) $stmt->fetchColumn();
        $suspended = (int) $this->db->query(
            "SELECT COUNT(*) FROM users WHERE hosting_status = 'suspended'"
        )->fetchColumn();
        $withHosting = 0;
        try {
            $withHosting = (int) $this->db->query(
                "SELECT COUNT(DISTINCT user_id) FROM hosting_accounts WHERE status = 'active'"
            )->fetchColumn();
        } catch (Throwable $e) {
        }
        $delinquent = (int) $this->db->query(
            "SELECT COUNT(DISTINCT user_id) FROM subscriptions WHERE status = 'overdue'"
        )->fetchColumn();

        return [
            'total' => $total,
            'active' => $active,
            'new' => $new,
            'inactive' => max(0, $total - $active),
            'delinquent' => $delinquent,
            'canceled' => 0,
            'withActiveHosting' => $withHosting,
            'suspended' => $suspended,
        ];
    }

    /** @return array<string, int> */
    private function hostingStats(): array
    {
        $out = [
            'total' => 0,
            'active' => 0,
            'suspended' => 0,
            'pending' => 0,
            'failed' => 0,
            'domains' => 0,
            'wordpress' => 0,
            'blankSites' => 0,
        ];
        try {
            $out['total'] = (int) $this->db->query('SELECT COUNT(*) FROM hosting_accounts')->fetchColumn();
            $out['active'] = (int) $this->db->query("SELECT COUNT(*) FROM hosting_accounts WHERE status = 'active'")->fetchColumn();
            $out['suspended'] = (int) $this->db->query("SELECT COUNT(*) FROM hosting_accounts WHERE status = 'suspended'")->fetchColumn();
            $out['pending'] = (int) $this->db->query(
                "SELECT COUNT(*) FROM hosting_accounts WHERE status IN ('pending','creating','blocked_no_domain')"
            )->fetchColumn();
            $out['failed'] = (int) $this->db->query(
                "SELECT COUNT(*) FROM hosting_jobs WHERE status = 'failed'"
            )->fetchColumn();
        } catch (Throwable $e) {
        }
        try {
            $out['domains'] = (int) $this->db->query('SELECT COUNT(*) FROM user_domains')->fetchColumn();
            $out['wordpress'] = (int) $this->db->query(
                "SELECT COUNT(*) FROM user_sites WHERE wordpress_status IN ('installed','queued') OR site_type = 'wordpress'"
            )->fetchColumn();
            $out['blankSites'] = (int) $this->db->query(
                "SELECT COUNT(*) FROM user_sites WHERE COALESCE(site_type,'blank') = 'blank'"
            )->fetchColumn();
        } catch (Throwable $e) {
        }
        return $out;
    }

    private function deltaPct(int $current, int $previous): ?float
    {
        if ($previous === 0) {
            return $current > 0 ? 100.0 : null;
        }
        return round((($current - $previous) / $previous) * 100, 1);
    }
}
