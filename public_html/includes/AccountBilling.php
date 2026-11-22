<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/crypto.php';
require_once __DIR__ . '/logger.php';

/**
 * Hosting cancellation and card billing-day requests.
 * No payment method is refunded. Access lasts until the next charge date.
 */
final class AccountBilling
{
    /** @var array<string, string> */
    public const REASONS = [
        'preco' => 'Preço',
        'nao_uso' => 'Não estou usando o serviço',
        'outro_provedor' => 'Vou para outro provedor',
        'tecnico' => 'Problemas técnicos',
        'atendimento' => 'Atendimento',
        'outro' => 'Outro',
    ];

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? geekhost_db();
    }

    public static function clampDay(int $day): int
    {
        return min(28, max(1, $day));
    }

    /** Next calendar day $day strictly after $fromTs. Bought on the 1st and cancelled on the 25th → the 1st of the next month. */
    public static function nextAnniversary(int $day, ?int $fromTs = null): string
    {
        $fromTs = $fromTs ?? time();
        $day = self::clampDay($day);
        $year = (int) gmdate('Y', $fromTs);
        $month = (int) gmdate('n', $fromTs);
        $current = (int) gmdate('j', $fromTs);
        if ($current >= $day) {
            $month++;
            if ($month > 12) {
                $month = 1;
                $year++;
            }
        }
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    public static function dueOnDay(string $anchor, int $day, string $period, bool $forceNextCycle = false): string
    {
        $day = self::clampDay($day);
        $ts = strtotime($anchor !== '' ? $anchor : 'today') ?: time();
        if ($forceNextCycle || $ts <= time()) {
            $add = $period === 'anual' ? 12 : ($period === 'semestral' ? 6 : 1);
            $ts = strtotime('+' . $add . ' months', $ts) ?: (time() + 30 * 86400);
        }
        $year = (int) date('Y', $ts);
        $month = (int) date('n', $ts);
        $candidate = sprintf('%04d-%02d-%02d', $year, $month, $day);
        if (strtotime($candidate . ' 23:59:59') <= time()) {
            return self::nextAnniversary($day);
        }
        return $candidate;
    }

    public function effectiveDay(int $userId): int
    {
        $row = $this->userBilling($userId);
        $source = (string) ($row['billing_day_source'] ?? '');
        if ($source === 'pending' || $source === 'rejected') {
            return 10;
        }
        $day = (int) ($row['billing_day'] ?? 0);
        if ($day >= 1) {
            return self::clampDay($day);
        }
        $sub = $this->latestSubscription($userId);
        $created = (string) ($sub['created_at'] ?? '');
        $ts = strtotime($created);
        if ($ts !== false) {
            return self::clampDay((int) gmdate('j', $ts));
        }
        return self::clampDay((int) gmdate('j'));
    }

    /** @return array<string, mixed> */
    public function summary(int $userId): array
    {
        $sub = $this->latestSubscription($userId);
        $order = $this->latestOrder($userId);
        $request = $this->latestDateRequest($userId);
        $cancel = $this->openCancellation($userId);
        $row = $this->userBilling($userId);
        return [
            'billingDay' => $this->effectiveDay($userId),
            'purchaseDay' => (int) ($row['billing_day'] ?? 0),
            'source' => (string) ($row['billing_day_source'] ?? ''),
            'period' => (string) ($sub['billing_period'] ?? $order['billing_period'] ?? 'mensal'),
            'plan' => (string) ($sub['plan_code'] ?? $order['plan_code'] ?? ''),
            'domain' => (string) ($sub['domain'] ?? $order['domain'] ?? ''),
            'billingType' => (string) ($order['billing_type'] ?? ''),
            'cancellation' => $cancel,
            'request' => $request,
            'canRequestDay' => (string) ($order['billing_type'] ?? '') === 'CREDIT_CARD',
            'hasService' => $sub !== null || $order !== null,
        ];
    }

    /**
     * @param list<string> $reasonKeys
     * @return array{access_until:string,billing_type:string}
     */
    public function cancel(int $userId, array $reasonKeys, string $comment): array
    {
        $reasonKeys = array_values(array_unique(array_filter(
            $reasonKeys,
            static fn ($key) => isset(self::REASONS[(string) $key])
        )));
        if ($reasonKeys === []) {
            throw new InvalidArgumentException('Selecione ao menos um motivo.');
        }
        $comment = trim($comment);
        if (mb_strlen($comment) > 1000) {
            throw new InvalidArgumentException('O comentário pode ter no máximo 1000 caracteres.');
        }
        if ($this->openCancellation($userId) !== null) {
            throw new RuntimeException('Já existe um cancelamento registrado nesta conta.');
        }
        $user = $this->userRow($userId);
        $sub = $this->latestSubscription($userId);
        $order = $this->latestOrder($userId);
        if ($sub === null && $order === null) {
            throw new RuntimeException('Não encontramos uma hospedagem para cancelar.');
        }
        $accessUntil = self::nextAnniversary($this->effectiveDay($userId));
        $now = gmdate('c');
        $billingType = (string) ($order['billing_type'] ?? '');
        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                'INSERT INTO hosting_cancellations (
                    user_id, email, plan_code, domain, billing_type, billing_period,
                    reasons_json, comment, refund_amount_cents, access_until, status, created_at
                ) VALUES (
                    :uid, :email, :plan, :domain, :bt, :bp, :reasons, :comment, 0, :until, :status, :created
                )'
            )->execute([
                ':uid' => $userId,
                ':email' => (string) ($user['email'] ?? ''),
                ':plan' => (string) ($sub['plan_code'] ?? $order['plan_code'] ?? ''),
                ':domain' => (string) ($sub['domain'] ?? $order['domain'] ?? ''),
                ':bt' => $billingType,
                ':bp' => (string) ($sub['billing_period'] ?? $order['billing_period'] ?? ''),
                ':reasons' => json_encode(array_values($reasonKeys), JSON_UNESCAPED_UNICODE),
                ':comment' => $comment,
                ':until' => $accessUntil,
                ':status' => 'scheduled',
                ':created' => $now,
            ]);
            if ($sub !== null) {
                $asaasId = (string) ($sub['asaas_subscription_id'] ?? '');
                if ($asaasId !== '') {
                    try {
                        require_once __DIR__ . '/AsaasClient.php';
                        (new AsaasClient())->deleteSubscription($asaasId);
                    } catch (Throwable $e) {
                        geekhost_log('cancel_subscription_failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
                    }
                }
                $this->db->prepare(
                    "UPDATE subscriptions SET status = 'cancel_scheduled', next_due_date = :n, updated_at = :u WHERE id = :id"
                )->execute([':n' => $accessUntil, ':u' => $now, ':id' => (int) $sub['id']]);
            }
            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
        return ['access_until' => $accessUntil, 'billing_type' => $billingType];
    }

    public function requestDay(int $userId, int $day): void
    {
        $day = self::clampDay($day);
        $order = $this->latestOrder($userId);
        if ((string) ($order['billing_type'] ?? '') !== 'CREDIT_CARD') {
            throw new RuntimeException('A data de cobrança vale para hospedagem paga no cartão.');
        }
        $current = $this->latestDateRequest($userId);
        if ($current !== null && (string) ($current['status'] ?? '') === 'pending') {
            throw new RuntimeException('Você já tem uma data em análise. O dia 10 fica valendo até a resposta.');
        }
        $user = $this->userRow($userId);
        $sub = $this->latestSubscription($userId);
        $previous = (int) ($this->userBilling($userId)['billing_day'] ?? 0);
        $now = gmdate('c');
        $this->db->prepare(
            'INSERT INTO billing_date_requests (
                user_id, email, plan_code, billing_period, previous_day, requested_day, status, created_at
            ) VALUES (
                :uid, :email, :plan, :period, :prev, :day, :status, :created
            )'
        )->execute([
            ':uid' => $userId,
            ':email' => (string) ($user['email'] ?? ''),
            ':plan' => (string) ($sub['plan_code'] ?? $order['plan_code'] ?? ''),
            ':period' => (string) ($sub['billing_period'] ?? $order['billing_period'] ?? 'mensal'),
            ':prev' => $previous > 0 ? $previous : null,
            ':day' => $day,
            ':status' => 'pending',
            ':created' => $now,
        ]);
        $this->db->prepare(
            "UPDATE users SET billing_day = 10, billing_day_source = 'pending', updated_at = :u WHERE id = :id"
        )->execute([':u' => $now, ':id' => $userId]);
    }

    public function decide(int $requestId, string $action, string $note): void
    {
        $stmt = $this->db->prepare('SELECT * FROM billing_date_requests WHERE id = :id');
        $stmt->execute([':id' => $requestId]);
        $request = $stmt->fetch();
        if (!$request || (string) $request['status'] !== 'pending') {
            throw new RuntimeException('Essa solicitação já foi respondida.');
        }
        $note = trim($note);
        $now = gmdate('c');
        $userId = (int) $request['user_id'];
        if ($action === 'approve') {
            $day = self::clampDay((int) $request['requested_day']);
            $this->db->prepare(
                "UPDATE billing_date_requests SET status = 'approved', admin_note = :n, decided_at = :d WHERE id = :id"
            )->execute([':n' => $note !== '' ? $note : null, ':d' => $now, ':id' => $requestId]);
            $this->db->prepare(
                "UPDATE users SET billing_day = :day, billing_day_source = 'approved', updated_at = :u WHERE id = :id"
            )->execute([':day' => $day, ':u' => $now, ':id' => $userId]);
            $this->pushSubscriptionDay($userId, $day, (string) ($request['billing_period'] ?? 'mensal'));
            return;
        }
        if ($action !== 'reject') {
            throw new InvalidArgumentException('Ação inválida.');
        }
        if ($note === '') {
            throw new InvalidArgumentException('Informe o motivo da recusa.');
        }
        $this->db->prepare(
            "UPDATE billing_date_requests SET status = 'rejected', admin_note = :n, decided_at = :d WHERE id = :id"
        )->execute([':n' => $note, ':d' => $now, ':id' => $requestId]);
        $this->db->prepare(
            "UPDATE users SET billing_day = 10, billing_day_source = 'rejected', updated_at = :u WHERE id = :id"
        )->execute([':u' => $now, ':id' => $userId]);
        $this->pushSubscriptionDay($userId, 10, (string) ($request['billing_period'] ?? 'mensal'));
    }

    /** @return list<array<string, mixed>> */
    public function listCancellations(): array
    {
        $sql = 'SELECT c.*, u.email AS user_email, u.name_enc,
                       cu.name AS customer_name, cu.phone, cu.mobile_phone, cu.cpf_cnpj
                FROM hosting_cancellations c
                JOIN users u ON u.id = c.user_id
                LEFT JOIN customers cu ON cu.id = u.customer_id
                ORDER BY c.id DESC';
        $rows = $this->db->query($sql)->fetchAll() ?: [];
        foreach ($rows as &$row) {
            $row['name'] = geekhost_open((string) ($row['name_enc'] ?: ($row['customer_name'] ?? '')));
            $row['phone_plain'] = geekhost_open((string) (($row['mobile_phone'] ?? '') !== '' ? $row['mobile_phone'] : ($row['phone'] ?? '')));
            $row['document'] = geekhost_open((string) ($row['cpf_cnpj'] ?? ''));
            $decoded = json_decode((string) ($row['reasons_json'] ?? '[]'), true);
            $labels = [];
            if (is_array($decoded)) {
                foreach ($decoded as $key) {
                    $labels[] = self::REASONS[(string) $key] ?? (string) $key;
                }
            }
            $row['reason_labels'] = $labels;
        }
        unset($row);
        return $rows;
    }

    /** @return list<array<string, mixed>> */
    public function listDateRequests(): array
    {
        $sql = 'SELECT r.*, u.email AS user_email, u.name_enc
                FROM billing_date_requests r
                JOIN users u ON u.id = r.user_id
                ORDER BY CASE r.status WHEN \'pending\' THEN 0 ELSE 1 END, r.id DESC';
        $rows = $this->db->query($sql)->fetchAll() ?: [];
        foreach ($rows as &$row) {
            $row['name'] = geekhost_open((string) ($row['name_enc'] ?? ''));
        }
        unset($row);
        return $rows;
    }

    /** @return array<string, mixed>|null */
    private function openCancellation(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM hosting_cancellations WHERE user_id = :u AND status = 'scheduled' ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([':u' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    private function latestDateRequest(int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM billing_date_requests WHERE user_id = :u ORDER BY id DESC LIMIT 1');
        $stmt->execute([':u' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    private function latestSubscription(int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM subscriptions WHERE user_id = :u ORDER BY id DESC LIMIT 1');
        $stmt->execute([':u' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    private function latestOrder(int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM orders WHERE user_id = :u ORDER BY id DESC LIMIT 1');
        $stmt->execute([':u' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<string, mixed> */
    private function userRow(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException('Conta não encontrada.');
        }
        return $row;
    }

    /** @return array<string, mixed> */
    private function userBilling(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT billing_day, billing_day_source FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: [];
    }

    private function pushSubscriptionDay(int $userId, int $day, string $period): void
    {
        $sub = $this->latestSubscription($userId);
        if ($sub === null) {
            return;
        }
        $next = self::dueOnDay((string) ($sub['next_due_date'] ?? ''), $day, $period, false);
        $now = gmdate('c');
        $this->db->prepare(
            'UPDATE subscriptions SET billing_day = :d, next_due_date = :n, updated_at = :u WHERE id = :id'
        )->execute([':d' => $day, ':n' => $next, ':u' => $now, ':id' => (int) $sub['id']]);
        $asaasId = (string) ($sub['asaas_subscription_id'] ?? '');
        if ($asaasId === '' || (string) ($sub['status'] ?? '') === 'cancel_scheduled') {
            return;
        }
        try {
            require_once __DIR__ . '/AsaasClient.php';
            (new AsaasClient())->updateSubscription($asaasId, ['nextDueDate' => $next]);
        } catch (Throwable $e) {
            geekhost_log('billing_day_push_failed', ['user_id' => $userId, 'error' => $e->getMessage()]);
        }
    }
}
