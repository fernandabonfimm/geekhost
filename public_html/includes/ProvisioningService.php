<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/crypto.php';
require_once __DIR__ . '/plans.php';
require_once __DIR__ . '/DirectAdminClient.php';
require_once __DIR__ . '/NotificationService.php';

/**
 * Real DirectAdmin provisioning pipeline.
 * Replaces ProvisioningStub: queues after paid Asaas confirmation; mutates DA only when enabled.
 */
final class ProvisioningService
{
    private PDO $db;
    private DirectAdminClient $da;

    public function __construct(?DirectAdminClient $client = null)
    {
        $this->db = geekhost_db();
        $this->da = $client ?? new DirectAdminClient();
        $this->ensureTables();
    }

    public static function provisioningEnabled(): bool
    {
        geekhost_load_env();
        return env('DIRECTADMIN_PROVISIONING_ENABLED', 'false') === '1'
            || strtolower((string) env('DIRECTADMIN_PROVISIONING_ENABLED', 'false')) === 'true';
    }

    /**
     * @param array<string, mixed> $order
     */
    public function queueFromOrder(array $order): void
    {
        $now = gmdate('c');
        $orderId = (int) ($order['id'] ?? 0);
        $userId = (int) ($order['user_id'] ?? 0);
        if ($orderId <= 0 || $userId <= 0) {
            geekhost_log('provisioning_skip_invalid_order', ['order' => $order['public_id'] ?? null]);
            return;
        }

        $this->db->prepare(
            'UPDATE orders SET provisioning_status = :ps, updated_at = :u
             WHERE id = :id AND provisioning_status IN (:none, :pending)'
        )->execute([
            ':ps' => 'pending',
            ':u' => $now,
            ':id' => $orderId,
            ':none' => 'none',
            ':pending' => 'pending',
        ]);

        $existing = $this->db->prepare('SELECT * FROM hosting_accounts WHERE order_id = :o LIMIT 1');
        $existing->execute([':o' => $orderId]);
        $account = $existing->fetch() ?: null;

        if (!$account) {
            $planCode = (string) ($order['plan_code'] ?? 'light');
            $domain = trim((string) ($order['domain'] ?? ''));
            $username = $this->allocateUsername($userId, $domain);
            $password = $this->generatePassword();
            $publicId = $this->uuid();
            $primary = $this->resolvePrimaryDomain($username, $domain);
            $status = $primary === null ? 'blocked_no_domain' : 'pending';

            $serviceId = null;
            $svc = $this->db->prepare('SELECT id FROM services WHERE order_id = :o LIMIT 1');
            $svc->execute([':o' => $orderId]);
            $serviceId = $svc->fetchColumn() ?: null;

            $this->db->prepare(
                'INSERT INTO hosting_accounts (
                    public_id, user_id, order_id, service_id, plan_code, da_username, da_password_enc,
                    da_package, primary_domain, status, created_at, updated_at
                ) VALUES (
                    :p, :u, :o, :s, :plan, :user, :pass, :pkg, :dom, :st, :c, :up
                )'
            )->execute([
                ':p' => $publicId,
                ':u' => $userId,
                ':o' => $orderId,
                ':s' => $serviceId,
                ':plan' => $planCode,
                ':user' => $username,
                ':pass' => geekhost_encrypt($password),
                ':pkg' => $this->packageForPlan($planCode),
                ':dom' => $primary,
                ':st' => $status,
                ':c' => $now,
                ':up' => $now,
            ]);

            $account = $this->findAccountByPublicId($publicId);
            $this->audit((int) $account['id'], $userId, 'system', 'account_queued', [
                'order_public_id' => $order['public_id'] ?? null,
                'status' => $status,
                'primary_domain' => $primary,
            ]);
        }

        if (($account['status'] ?? '') === 'blocked_no_domain') {
            geekhost_log('provisioning_blocked_no_domain', [
                'order' => $order['public_id'] ?? null,
                'account' => $account['public_id'] ?? null,
            ]);
            return;
        }

        if (in_array((string) ($account['status'] ?? ''), ['active', 'creating'], true)) {
            return;
        }

        $this->enqueueJob((int) $account['id'], $userId, 'provision', [
            'order_public_id' => $order['public_id'] ?? null,
        ]);

        geekhost_log('provisioning_queued', [
            'order_public_id' => $order['public_id'] ?? null,
            'plan_code' => $order['plan_code'] ?? null,
            'account' => $account['public_id'] ?? null,
            'enabled' => self::provisioningEnabled(),
        ]);
    }

    /**
     * Process pending hosting jobs (cron / admin reprocess).
     *
     * @return array{processed:int, failed:int, skipped:int}
     */
    public function processPendingJobs(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        $now = gmdate('c');
        $stmt = $this->db->prepare(
            "SELECT * FROM hosting_jobs
             WHERE status = 'pending' AND (available_at IS NULL OR available_at <= :now)
             ORDER BY id ASC LIMIT {$limit}"
        );
        $stmt->execute([':now' => $now]);
        $jobs = $stmt->fetchAll() ?: [];

        $processed = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($jobs as $job) {
            $result = $this->runJob($job);
            if ($result === 'done') {
                $processed++;
            } elseif ($result === 'failed') {
                $failed++;
            } else {
                $skipped++;
            }
        }

        return compact('processed', 'failed', 'skipped');
    }

    /**
     * Poll domains awaiting DNS, then queue SSL when pointed.
     *
     * @return array{checked:int, pointed:int, ssl_queued:int}
     */
    public function pollDnsAndSsl(int $limit = 50): array
    {
        require_once __DIR__ . '/DomainSiteService.php';
        $domains = new DomainSiteService();
        $stmt = $this->db->prepare(
            "SELECT * FROM user_domains WHERE status = 'awaiting_dns' OR ns_ok = 0 ORDER BY id ASC LIMIT " . max(1, min(200, $limit))
        );
        $stmt->execute();
        $rows = $stmt->fetchAll() ?: [];
        $checked = 0;
        $pointed = 0;
        $sslQueued = 0;

        foreach ($rows as $row) {
            $checked++;
            $presented = $domains->refreshDomainDns((int) $row['user_id'], (string) $row['public_id']);
            if (!empty($presented['nsOk'])) {
                $pointed++;
                $fresh = $this->db->prepare('SELECT * FROM user_domains WHERE id = :id');
                $fresh->execute([':id' => $row['id']]);
                $domainRow = $fresh->fetch() ?: $row;
                $ssl = (string) ($domainRow['ssl_status'] ?? 'none');
                if (in_array($ssl, ['none', 'failed', ''], true)) {
                    $account = $this->findActiveAccountForUser((int) $row['user_id']);
                    if ($account) {
                        $this->enqueueJob((int) $account['id'], (int) $row['user_id'], 'ssl', [
                            'domain' => $row['domain'],
                            'domain_id' => (int) $row['id'],
                        ]);
                        $this->db->prepare("UPDATE user_domains SET ssl_status = 'pending', updated_at = :u WHERE id = :id")
                            ->execute([':u' => gmdate('c'), ':id' => $row['id']]);
                        $sslQueued++;
                    }
                }
            }
        }

        // Also SSL for already-pointed domains still pending
        $pendingSsl = $this->db->query(
            "SELECT d.*, a.id AS hosting_account_id FROM user_domains d
             LEFT JOIN hosting_accounts a ON a.user_id = d.user_id AND a.status IN ('active','creating')
             WHERE d.ns_ok = 1 AND (d.ssl_status IS NULL OR d.ssl_status IN ('none','pending','failed'))
             LIMIT 50"
        );
        foreach ($pendingSsl ? ($pendingSsl->fetchAll() ?: []) : [] as $row) {
            if (empty($row['hosting_account_id'])) {
                continue;
            }
            if ((string) ($row['ssl_status'] ?? '') === 'pending') {
                // already queued; ensure job exists
                continue;
            }
            $this->enqueueJob((int) $row['hosting_account_id'], (int) $row['user_id'], 'ssl', [
                'domain' => $row['domain'],
                'domain_id' => (int) $row['id'],
            ]);
            $this->db->prepare("UPDATE user_domains SET ssl_status = 'pending', updated_at = :u WHERE id = :id")
                ->execute([':u' => gmdate('c'), ':id' => $row['id']]);
            $sslQueued++;
        }

        return compact('checked', 'pointed', 'sslQueued');
    }

    public function reprocessAccount(string $publicId, string $actor = 'admin'): bool
    {
        $account = $this->findAccountByPublicId($publicId);
        if (!$account) {
            return false;
        }
        $now = gmdate('c');
        if (in_array((string) $account['status'], ['active'], true)) {
            // allow retry of failed side jobs only
        } else {
            $this->db->prepare("UPDATE hosting_accounts SET status = 'pending', last_error = NULL, updated_at = :u WHERE id = :id")
                ->execute([':u' => $now, ':id' => $account['id']]);
        }
        $this->enqueueJob((int) $account['id'], (int) $account['user_id'], 'provision', ['reprocess' => true], true);
        $this->audit((int) $account['id'], (int) $account['user_id'], $actor, 'reprocess_requested', []);
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function backupUserAccount(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM hosting_accounts WHERE user_id = :u AND da_username <> '' ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([':u' => $userId]);
        $account = $stmt->fetch() ?: null;
        if (!$account) {
            return ['skipped' => true, 'reason' => 'no_directadmin_account'];
        }
        $result = $this->da->backupUser(
            (string) $account['da_username'],
            (string) ($account['primary_domain'] ?? '')
        );
        $this->audit((int) $account['id'], $userId, 'billing', 'backup_requested', [
            'dry_run' => !empty($result['dry_run']),
            'error' => $result['error'] ?? null,
        ]);
        return $result;
    }

    public function adminSuspendAccount(string $publicId): void
    {
        $account = $this->findAccountByPublicId($publicId);
        if (!$account) {
            throw new InvalidArgumentException('Hospedagem não encontrada.');
        }
        $this->da->suspendUser((string) $account['da_username']);
        $this->db->prepare("UPDATE hosting_accounts SET status = 'suspended', updated_at = :u WHERE id = :id")
            ->execute([':u' => gmdate('c'), ':id' => $account['id']]);
    }

    public function adminUnsuspendAccount(string $publicId): void
    {
        $account = $this->findAccountByPublicId($publicId);
        if (!$account) {
            throw new InvalidArgumentException('Hospedagem não encontrada.');
        }
        $this->da->unsuspendUser((string) $account['da_username']);
        $this->db->prepare("UPDATE hosting_accounts SET status = 'active', updated_at = :u WHERE id = :id")
            ->execute([':u' => gmdate('c'), ':id' => $account['id']]);
    }

    public function suspendUserHosting(int $userId, string $reason = 'overdue'): void
    {
        $account = $this->findActiveAccountForUser($userId);
        if (!$account) {
            return;
        }
        $this->enqueueJob((int) $account['id'], $userId, 'suspend', ['reason' => $reason], true);
        $this->processSingleJobType((int) $account['id'], 'suspend');
    }

    public function unsuspendUserHosting(int $userId): void
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM hosting_accounts WHERE user_id = :u AND status = 'suspended' ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([':u' => $userId]);
        $account = $stmt->fetch() ?: null;
        if (!$account) {
            return;
        }
        $this->enqueueJob((int) $account['id'], $userId, 'unsuspend', [], true);
        $this->processSingleJobType((int) $account['id'], 'unsuspend');
    }

    public function queueAddDomain(int $userId, string $domain, int $domainId): void
    {
        $account = $this->findActiveAccountForUser($userId);
        if ($account && trim((string) ($account['primary_domain'] ?? '')) === '') {
            $this->attachPrimaryDomain($account, $domain);
            return;
        }
        if ($account) {
            $this->enqueueJob((int) $account['id'], $userId, 'add_domain', [
                'domain' => $domain,
                'domain_id' => $domainId,
            ]);
            return;
        }
        $blocked = $this->findBlockedAccountForUser($userId);
        if ($blocked) {
            $this->attachPrimaryDomain($blocked, $domain);
        }
    }

    /**
     * @return array{userId:int,email:string,panelPassword:?string,createdUser:bool,daUsername:string,status:string}
     */
    public function createClientForAdmin(string $name, string $email, string $password, string $planCode, string $domain): array
    {
        $name = trim($name);
        $email = strtolower(trim($email));
        $domain = $this->normalizeDomain($domain);
        if ($name === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Informe nome e e-mail válidos.');
        }
        if (geekhost_plan($planCode) === null) {
            throw new InvalidArgumentException('Plano inválido.');
        }

        $existing = $this->db->prepare('SELECT * FROM users WHERE email = :e LIMIT 1');
        $existing->execute([':e' => $email]);
        $user = $existing->fetch() ?: null;
        $panelPassword = null;
        $createdUser = false;
        if (!$user) {
            if ($password === '') {
                $password = $this->generatePassword(12);
            }
            if (strlen($password) < 8) {
                throw new InvalidArgumentException('A senha deve ter pelo menos 8 caracteres.');
            }
            $panelPassword = $password;
            $createdUser = true;
            $now = gmdate('c');
            $this->db->prepare(
                'INSERT INTO users (
                    public_id, customer_id, email, password_hash, password_is_set,
                    name_enc, hosting_status, created_at, updated_at
                ) VALUES (
                    :pid, NULL, :email, :ph, 1, :name, :hs, :c, :u
                )'
            )->execute([
                ':pid' => $this->uuid(),
                ':email' => $email,
                ':ph' => password_hash($password, PASSWORD_DEFAULT),
                ':name' => geekhost_encrypt($name) ?? '',
                ':hs' => 'pending',
                ':c' => $now,
                ':u' => $now,
            ]);
            $userId = (int) $this->db->lastInsertId();
        } else {
            if ($password !== '') {
                throw new InvalidArgumentException('Este e-mail já tem conta. Deixe a senha em branco para só abrir a hospedagem.');
            }
            $userId = (int) $user['id'];
        }

        $dup = $this->db->prepare('SELECT id FROM hosting_accounts WHERE user_id = :u LIMIT 1');
        $dup->execute([':u' => $userId]);
        if ($dup->fetch()) {
            throw new InvalidArgumentException('Este cliente já tem uma hospedagem.');
        }

        $username = $this->allocateUsername($userId, $domain);
        $status = $domain === '' ? 'blocked_no_domain' : 'pending';
        $now = gmdate('c');
        $publicId = $this->uuid();
        $this->db->prepare(
            'INSERT INTO hosting_accounts (
                public_id, user_id, order_id, service_id, plan_code, da_username, da_password_enc,
                da_package, primary_domain, status, created_at, updated_at
            ) VALUES (
                :p, :u, NULL, NULL, :plan, :user, :pass, :pkg, :dom, :st, :c, :up
            )'
        )->execute([
            ':p' => $publicId,
            ':u' => $userId,
            ':plan' => $planCode,
            ':user' => $username,
            ':pass' => geekhost_encrypt($this->generatePassword()),
            ':pkg' => $this->packageForPlan($planCode),
            ':dom' => $domain !== '' ? $domain : null,
            ':st' => $status,
            ':c' => $now,
            ':up' => $now,
        ]);
        $account = $this->findAccountByPublicId($publicId);
        if ($account && $status === 'pending') {
            $this->enqueueJob((int) $account['id'], $userId, 'provision', ['source' => 'admin']);
        }
        if ($account) {
            $this->audit((int) $account['id'], $userId, 'admin', 'client_created', [
                'status' => $status,
                'domain' => $domain,
            ]);
        }
        return [
            'userId' => $userId,
            'email' => $email,
            'panelPassword' => $panelPassword,
            'createdUser' => $createdUser,
            'daUsername' => $username,
            'status' => $status,
        ];
    }

    public function adminAttachDomain(string $publicId, string $domain): void
    {
        $account = $this->findAccountByPublicId($publicId);
        if (!$account) {
            throw new InvalidArgumentException('Hospedagem não encontrada.');
        }
        $domain = $this->normalizeDomain($domain);
        $userId = (int) $account['user_id'];
        $planCode = (string) ($account['plan_code'] ?? 'light');
        $limit = geekhost_plan_domain_limit($planCode);
        $countStmt = $this->db->prepare('SELECT COUNT(*) FROM user_domains WHERE user_id = :u');
        $countStmt->execute([':u' => $userId]);
        $count = (int) $countStmt->fetchColumn();
        $primary = trim((string) ($account['primary_domain'] ?? ''));
        $replacingEmpty = $primary === '' || (string) ($account['status'] ?? '') === 'blocked_no_domain';
        if (!$replacingEmpty && $count >= $limit) {
            throw new InvalidArgumentException("O plano permite no máximo {$limit} domínios.");
        }
        $exists = $this->db->prepare('SELECT id FROM user_domains WHERE user_id = :u AND domain = :d LIMIT 1');
        $exists->execute([':u' => $userId, ':d' => $domain]);
        if (!$exists->fetch()) {
            $now = gmdate('c');
            $this->db->prepare(
                'INSERT INTO user_domains (
                    public_id, user_id, domain, plan_code, ns_ok, status, hosting_account_id, created_at, updated_at
                ) VALUES (
                    :p, :u, :d, :plan, 0, :st, :h, :c, :up
                )'
            )->execute([
                ':p' => $this->uuid(),
                ':u' => $userId,
                ':d' => $domain,
                ':plan' => $planCode,
                ':st' => 'awaiting_dns',
                ':h' => $account['id'],
                ':c' => $now,
                ':up' => $now,
            ]);
        }
        if ($replacingEmpty) {
            $this->attachPrimaryDomain($account, $domain);
            return;
        }
        $this->enqueueJob((int) $account['id'], $userId, 'add_domain', ['domain' => $domain]);
    }

    public function directAdminLoginUrl(string $publicId, string $path): ?string
    {
        $account = $this->findAccountByPublicId($publicId);
        if (!$account) {
            throw new InvalidArgumentException('Hospedagem não encontrada.');
        }
        $username = trim((string) ($account['da_username'] ?? ''));
        if ($username === '') {
            throw new InvalidArgumentException('Conta sem usuário DirectAdmin.');
        }
        return $this->da->oneTimeLoginUrl($username, $path);
    }

    /** @return list<string> */
    public function databasesForAccount(string $publicId): array
    {
        $account = $this->findAccountByPublicId($publicId);
        if (!$account) {
            throw new InvalidArgumentException('Hospedagem não encontrada.');
        }
        return $this->da->listDatabases((string) ($account['da_username'] ?? ''));
    }

    public function queueWordpress(int $userId, int $siteId, string $domain, string $title, string $email): void
    {
        $account = $this->findActiveAccountForUser($userId);
        if (!$account) {
            throw new InvalidArgumentException('Hospedagem DirectAdmin ainda não está ativa.');
        }
        $this->enqueueJob((int) $account['id'], $userId, 'wordpress', [
            'site_id' => $siteId,
            'domain' => $domain,
            'title' => $title,
            'email' => $email,
        ], true);
    }

    /** @return list<array<string, mixed>> */
    public function listAccountsForAdmin(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $rows = $this->db->query(
            "SELECT h.*, u.email AS user_email, u.name AS user_name
             FROM hosting_accounts h
             LEFT JOIN users u ON u.id = h.user_id
             ORDER BY h.id DESC LIMIT {$limit}"
        )->fetchAll() ?: [];
        return array_map(fn ($r) => $this->presentAccount($r), $rows);
    }

    /** @return list<array<string, mixed>> */
    public function listJobsForAdmin(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        return $this->db->query(
            "SELECT j.*, h.public_id AS account_public_id, h.da_username
             FROM hosting_jobs j
             LEFT JOIN hosting_accounts h ON h.id = j.hosting_account_id
             ORDER BY j.id DESC LIMIT {$limit}"
        )->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> */
    public function listAuditForAdmin(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        return $this->db->query(
            "SELECT a.*, h.public_id AS account_public_id, h.da_username
             FROM hosting_audit_logs a
             LEFT JOIN hosting_accounts h ON h.id = a.hosting_account_id
             ORDER BY a.id DESC LIMIT {$limit}"
        )->fetchAll() ?: [];
    }

    public function findAccountForUser(int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM hosting_accounts WHERE user_id = :u ORDER BY id DESC LIMIT 1');
        $stmt->execute([':u' => $userId]);
        $row = $stmt->fetch();
        return $row ? $this->presentAccount($row) : null;
    }

    /**
     * @param array<string, mixed> $job
     * @return 'done'|'failed'|'skipped'
     */
    private function runJob(array $job): string
    {
        $id = (int) $job['id'];
        $now = gmdate('c');
        $lock = $this->db->prepare(
            "UPDATE hosting_jobs SET status = 'running', attempts = attempts + 1, updated_at = :u
             WHERE id = :id AND status = 'pending'"
        );
        $lock->execute([':u' => $now, ':id' => $id]);
        if ($lock->rowCount() === 0) {
            return 'skipped';
        }

        try {
            $type = (string) $job['job_type'];
            $account = $this->findAccountById((int) $job['hosting_account_id']);
            if (!$account) {
                throw new RuntimeException('Conta de hospedagem não encontrada.');
            }
            $payload = [];
            if (!empty($job['payload_json'])) {
                $decoded = json_decode((string) $job['payload_json'], true);
                if (is_array($decoded)) {
                    $payload = $decoded;
                }
            }

            match ($type) {
                'provision' => $this->jobProvision($account),
                'add_domain' => $this->jobAddDomain($account, $payload),
                'ssl' => $this->jobSsl($account, $payload),
                'wordpress' => $this->jobWordpress($account, $payload),
                'suspend' => $this->jobSuspend($account, $payload),
                'unsuspend' => $this->jobUnsuspend($account),
                default => throw new RuntimeException('Tipo de job desconhecido: ' . $type),
            };

            $this->db->prepare(
                "UPDATE hosting_jobs SET status = 'done', last_error = NULL, updated_at = :u WHERE id = :id"
            )->execute([':u' => gmdate('c'), ':id' => $id]);
            return 'done';
        } catch (Throwable $e) {
            $attempts = (int) ($job['attempts'] ?? 0) + 1;
            $retryAt = $attempts < 5 ? gmdate('c', time() + min(3600, 60 * (2 ** $attempts))) : null;
            $status = $retryAt ? 'pending' : 'failed';
            $this->db->prepare(
                'UPDATE hosting_jobs SET status = :st, last_error = :err, available_at = :av, updated_at = :u WHERE id = :id'
            )->execute([
                ':st' => $status,
                ':err' => mb_substr($e->getMessage(), 0, 500),
                ':av' => $retryAt,
                ':u' => gmdate('c'),
                ':id' => $id,
            ]);
            $this->audit(
                (int) $job['hosting_account_id'],
                (int) $job['user_id'],
                'system',
                'job_failed',
                ['job_type' => $job['job_type'], 'error' => $e->getMessage()]
            );
            geekhost_log('provisioning_job_failed', [
                'job_id' => $id,
                'type' => $job['job_type'],
                'error' => $e->getMessage(),
            ]);
            return 'failed';
        }
    }

    /** @param array<string, mixed> $account */
    private function jobProvision(array $account): void
    {
        if (!self::provisioningEnabled()) {
            $this->db->prepare(
                "UPDATE hosting_accounts SET status = 'pending', last_error = :e, updated_at = :u WHERE id = :id"
            )->execute([
                ':e' => 'DIRECTADMIN_PROVISIONING_ENABLED=false — reprocessar após habilitar',
                ':u' => gmdate('c'),
                ':id' => $account['id'],
            ]);
            $this->audit((int) $account['id'], (int) $account['user_id'], 'system', 'provision_deferred', [
                'reason' => 'feature_flag_off',
            ]);
            // Job completes without DA mutation; admin reprocess after enabling the flag.
            return;
        }

        if (!$this->da->isConfigured()) {
            throw new RuntimeException('Credenciais DirectAdmin ausentes.');
        }

        $domain = trim((string) ($account['primary_domain'] ?? ''));
        if ($domain === '') {
            $this->db->prepare(
                "UPDATE hosting_accounts SET status = 'blocked_no_domain', updated_at = :u WHERE id = :id"
            )->execute([':u' => gmdate('c'), ':id' => $account['id']]);
            throw new RuntimeException('Domínio primário ausente — não inventar DNS.');
        }

        $now = gmdate('c');
        $this->db->prepare("UPDATE hosting_accounts SET status = 'creating', updated_at = :u WHERE id = :id")
            ->execute([':u' => $now, ':id' => $account['id']]);

        $username = (string) $account['da_username'];
        $password = geekhost_decrypt((string) ($account['da_password_enc'] ?? '')) ?: $this->generatePassword();
        if (empty($account['da_password_enc'])) {
            $this->db->prepare('UPDATE hosting_accounts SET da_password_enc = :p, updated_at = :u WHERE id = :id')
                ->execute([':p' => geekhost_encrypt($password), ':u' => $now, ':id' => $account['id']]);
        }

        $email = $this->userEmail((int) $account['user_id']);
        if ($this->da->userExists($username)) {
            geekhost_log('da_user_already_exists', ['username' => $username]);
        } else {
            $this->da->createUser([
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'domain' => $domain,
                'package' => (string) $account['da_package'],
                'notify' => false,
            ]);
        }

        $this->db->prepare(
            "UPDATE hosting_accounts SET status = 'active', last_error = NULL, provisioned_at = :p, updated_at = :u WHERE id = :id"
        )->execute([':p' => $now, ':u' => $now, ':id' => $account['id']]);

        $this->db->prepare(
            "UPDATE orders SET provisioning_status = 'done', updated_at = :u WHERE id = :id"
        )->execute([':u' => $now, ':id' => $account['order_id']]);

        $this->db->prepare(
            "UPDATE services SET status = 'active', updated_at = :u WHERE order_id = :o"
        )->execute([':u' => $now, ':o' => $account['order_id']]);

        $this->db->prepare(
            "UPDATE users SET hosting_status = 'active', updated_at = :u WHERE id = :id AND hosting_status != 'suspended'"
        )->execute([':u' => $now, ':id' => $account['user_id']]);

        $this->audit((int) $account['id'], (int) $account['user_id'], 'system', 'provisioned', [
            'username' => $username,
            'domain' => $domain,
            'dry_run' => $this->da->isDryRun(),
        ]);

        try {
            $notifications = new NotificationService();
            $notifications->notify(
                (int) $account['user_id'],
                'hosting_active',
                'Hospedagem ativa',
                'Sua hospedagem está ativa. Cadastre o domínio e aponte para ns1/ns2.geekhost.com.br.',
                function_exists('url') ? url('/painel/') : '/painel/'
            );
        } catch (Throwable $e) {
            geekhost_log('hosting_notify_failed', ['error' => $e->getMessage()]);
        }

        $this->sendWelcomeEmail((int) $account['user_id'], $username, $domain);
    }

    /**
     * @param array<string, mixed> $account
     * @param array<string, mixed> $payload
     */
    private function jobAddDomain(array $account, array $payload): void
    {
        if (!self::provisioningEnabled()) {
            throw new RuntimeException('Provisionamento desabilitado.');
        }
        $domain = (string) ($payload['domain'] ?? '');
        if ($domain === '') {
            throw new InvalidArgumentException('Domínio ausente no job add_domain.');
        }
        // Primary domain already created with the user — skip duplicate.
        if (strcasecmp($domain, (string) ($account['primary_domain'] ?? '')) === 0) {
            return;
        }
        $this->da->addDomain((string) $account['da_username'], $domain);
        $this->audit((int) $account['id'], (int) $account['user_id'], 'system', 'domain_added', ['domain' => $domain]);
    }

    /**
     * @param array<string, mixed> $account
     * @param array<string, mixed> $payload
     */
    private function jobSsl(array $account, array $payload): void
    {
        if (!self::provisioningEnabled()) {
            throw new RuntimeException('Provisionamento desabilitado.');
        }
        $domain = (string) ($payload['domain'] ?? '');
        $domainId = (int) ($payload['domain_id'] ?? 0);
        if ($domain === '') {
            throw new InvalidArgumentException('Domínio ausente no job SSL.');
        }

        // Confirm DNS still ok before marking active
        require_once __DIR__ . '/DomainSiteService.php';
        $check = (new DomainSiteService())->checkNameservers($domain);
        if (empty($check['ok'])) {
            if ($domainId > 0) {
                $this->db->prepare("UPDATE user_domains SET ssl_status = 'waiting_dns', updated_at = :u WHERE id = :id")
                    ->execute([':u' => gmdate('c'), ':id' => $domainId]);
            }
            throw new RuntimeException('DNS ainda não aponta para GeekHost — SSL adiado.');
        }

        $res = $this->da->provisionSsl($domain, false);
        if ($res['status'] >= 400) {
            if ($domainId > 0) {
                $this->db->prepare("UPDATE user_domains SET ssl_status = 'failed', updated_at = :u WHERE id = :id")
                    ->execute([':u' => gmdate('c'), ':id' => $domainId]);
            }
            throw new RuntimeException('Falha ao solicitar certificado SSL (HTTP ' . $res['status'] . ').');
        }

        // Confirm certs present before "active"
        $status = $this->da->sslDomainStatus($domain);
        $active = false;
        if ($status['status'] < 400 && is_array($status['body'])) {
            $certs = $status['body']['certs'] ?? $status['body']['certIds'] ?? [];
            $active = is_array($certs) && $certs !== [];
        }
        if ($this->da->isDryRun()) {
            $active = true;
        }

        if ($domainId > 0) {
            $this->db->prepare('UPDATE user_domains SET ssl_status = :st, updated_at = :u WHERE id = :id')
                ->execute([
                    ':st' => $active ? 'active' : 'pending',
                    ':u' => gmdate('c'),
                    ':id' => $domainId,
                ]);
        }
        if (!$active) {
            throw new RuntimeException('SSL solicitado mas ainda sem certificado confirmado.');
        }
        $this->audit((int) $account['id'], (int) $account['user_id'], 'system', 'ssl_active', ['domain' => $domain]);
    }

    /**
     * @param array<string, mixed> $account
     * @param array<string, mixed> $payload
     */
    private function jobWordpress(array $account, array $payload): void
    {
        if (!self::provisioningEnabled()) {
            throw new RuntimeException('Provisionamento desabilitado.');
        }
        $domain = (string) ($payload['domain'] ?? '');
        $siteId = (int) ($payload['site_id'] ?? 0);
        $title = (string) ($payload['title'] ?? $domain);
        $email = (string) ($payload['email'] ?? $this->userEmail((int) $account['user_id']));
        if ($domain === '') {
            throw new InvalidArgumentException('Domínio ausente no job WordPress.');
        }

        $adminPass = $this->generatePassword();
        $filePath = '/home/' . $account['da_username'] . '/domains/' . $domain . '/public_html';
        $res = $this->da->installWordpressQuick((string) $account['da_username'], [
            'filePath' => $filePath,
            'title' => $title,
            'adminEmail' => $email,
            'adminName' => 'admin',
            'adminPass' => $adminPass,
        ]);
        if ($res['status'] >= 400) {
            $type = is_array($res['body']) ? (string) ($res['body']['type'] ?? '') : '';
            if ($type !== 'WORDPRESS_ALREADY_INSTALLED') {
                if ($siteId > 0) {
                    $this->db->prepare("UPDATE user_sites SET wordpress_status = 'failed', status = 'wordpress_failed', updated_at = :u WHERE id = :id")
                        ->execute([':u' => gmdate('c'), ':id' => $siteId]);
                }
                throw new RuntimeException('Instalação WordPress falhou (HTTP ' . $res['status'] . ').');
            }
        }

        if ($siteId > 0) {
            $this->db->prepare(
                "UPDATE user_sites SET wordpress_status = 'installed', status = 'ready', updated_at = :u WHERE id = :id"
            )->execute([':u' => gmdate('c'), ':id' => $siteId]);
        }
        $this->audit((int) $account['id'], (int) $account['user_id'], 'system', 'wordpress_installed', [
            'domain' => $domain,
            // never store admin password in audit
        ]);
    }

    /**
     * @param array<string, mixed> $account
     * @param array<string, mixed> $payload
     */
    private function jobSuspend(array $account, array $payload): void
    {
        if (!self::provisioningEnabled()) {
            throw new RuntimeException('Provisionamento desabilitado.');
        }
        $this->da->suspendUser((string) $account['da_username']);
        $now = gmdate('c');
        $this->db->prepare("UPDATE hosting_accounts SET status = 'suspended', updated_at = :u WHERE id = :id")
            ->execute([':u' => $now, ':id' => $account['id']]);
        $this->audit((int) $account['id'], (int) $account['user_id'], 'system', 'suspended', [
            'reason' => $payload['reason'] ?? null,
        ]);
    }

    /** @param array<string, mixed> $account */
    private function jobUnsuspend(array $account): void
    {
        if (!self::provisioningEnabled()) {
            throw new RuntimeException('Provisionamento desabilitado.');
        }
        $this->da->unsuspendUser((string) $account['da_username']);
        $now = gmdate('c');
        $this->db->prepare("UPDATE hosting_accounts SET status = 'active', updated_at = :u WHERE id = :id")
            ->execute([':u' => $now, ':id' => $account['id']]);
        $this->audit((int) $account['id'], (int) $account['user_id'], 'system', 'unsuspended', []);
    }

    private function processSingleJobType(int $accountId, string $type): void
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM hosting_jobs WHERE hosting_account_id = :a AND job_type = :t AND status = 'pending' ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([':a' => $accountId, ':t' => $type]);
        $job = $stmt->fetch();
        if ($job) {
            $this->runJob($job);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function enqueueJob(int $accountId, int $userId, string $type, array $payload = [], bool $force = false): void
    {
        if (!$force) {
            $dup = $this->db->prepare(
                "SELECT id FROM hosting_jobs
                 WHERE hosting_account_id = :a AND job_type = :t AND status IN ('pending','running')
                 LIMIT 1"
            );
            $dup->execute([':a' => $accountId, ':t' => $type]);
            if ($dup->fetch()) {
                return;
            }
        }
        $now = gmdate('c');
        $this->db->prepare(
            'INSERT INTO hosting_jobs (
                public_id, hosting_account_id, user_id, job_type, payload_json, status, attempts, created_at, updated_at
            ) VALUES (
                :p, :a, :u, :t, :pay, :st, 0, :c, :up
            )'
        )->execute([
            ':p' => $this->uuid(),
            ':a' => $accountId,
            ':u' => $userId,
            ':t' => $type,
            ':pay' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ':st' => 'pending',
            ':c' => $now,
            ':up' => $now,
        ]);
    }

    /**
     * @param array<string, mixed> $detail
     */
    private function audit(int $accountId, int $userId, string $actor, string $action, array $detail): void
    {
        $this->db->prepare(
            'INSERT INTO hosting_audit_logs (hosting_account_id, user_id, actor, action, detail_json, created_at)
             VALUES (:a, :u, :actor, :action, :d, :c)'
        )->execute([
            ':a' => $accountId,
            ':u' => $userId,
            ':actor' => $actor,
            ':action' => $action,
            ':d' => json_encode($detail, JSON_UNESCAPED_UNICODE),
            ':c' => gmdate('c'),
        ]);
    }

    private function packageForPlan(string $planCode): string
    {
        return geekhost_plan_da_package($planCode);
    }

    private function allocateUsername(int $userId, string $domain): string
    {
        $base = '';
        if ($domain !== '') {
            $label = preg_replace('/[^a-z0-9]/', '', strtolower(explode('.', $domain)[0] ?? '')) ?? '';
            $base = substr($label, 0, 6);
        }
        if ($base === '' || strlen($base) < 3) {
            $base = 'gh' . substr((string) $userId, -4);
        }
        $base = preg_replace('/[^a-z0-9]/', '', strtolower($base)) ?? 'ghuser';
        $base = substr($base, 0, 8);
        if ($base === '' || ctype_digit($base[0])) {
            $base = 'g' . $base;
        }

        for ($i = 0; $i < 20; $i++) {
            $candidate = $i === 0 ? $base : substr($base, 0, 6) . substr(bin2hex(random_bytes(2)), 0, 2);
            $candidate = substr($candidate, 0, 10);
            $stmt = $this->db->prepare('SELECT id FROM hosting_accounts WHERE da_username = :u LIMIT 1');
            $stmt->execute([':u' => $candidate]);
            if (!$stmt->fetch()) {
                return $candidate;
            }
        }
        return 'gh' . bin2hex(random_bytes(4));
    }

    private function resolvePrimaryDomain(string $username, string $orderDomain): ?string
    {
        $orderDomain = strtolower(trim($orderDomain));
        if ($orderDomain !== '' && preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $orderDomain)) {
            return $orderDomain;
        }
        $suffix = trim((string) env('DIRECTADMIN_PROVISIONAL_DOMAIN_SUFFIX', ''), '.');
        if ($suffix !== '' && env('DIRECTADMIN_PROVISIONAL_DNS_READY', '0') === '1') {
            return strtolower($username) . '.' . $suffix;
        }
        return null;
    }

    private function generatePassword(int $length = 20): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%';
        $out = '';
        $max = strlen($alphabet) - 1;
        for ($i = 0; $i < $length; $i++) {
            $out .= $alphabet[random_int(0, $max)];
        }
        return $out;
    }

    private function userEmail(int $userId): string
    {
        $stmt = $this->db->prepare('SELECT email FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $email = (string) ($stmt->fetchColumn() ?: '');
        return $email !== '' ? $email : 'noreply@geekhost.com.br';
    }

    private function sendWelcomeEmail(int $userId, string $username, string $domain): void
    {
        $email = $this->userEmail($userId);
        $subject = 'GeekHost — Hospedagem ativa';
        $body = "Olá,\n\nSua hospedagem GeekHost está ativa.\n"
            . "Usuário DirectAdmin: {$username}\n"
            . "Domínio primário: {$domain}\n"
            . "Nameservers: ns1.geekhost.com.br / ns2.geekhost.com.br\n\n"
            . "Acesse o painel para cadastrar domínios e sites.\n";
        $from = (string) env('GEEKHOST_CONTACT_EMAIL', 'contato@geekhost.com.br');
        $headers = 'From: GeekHost <' . $from . ">\r\nContent-Type: text/plain; charset=UTF-8";
        @mail($email, $subject, $body, $headers);
    }

    /** @return array<string, mixed>|null */
    private function findAccountByPublicId(string $publicId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM hosting_accounts WHERE public_id = :p LIMIT 1');
        $stmt->execute([':p' => $publicId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    private function findAccountById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM hosting_accounts WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @param array<string, mixed> $account */
    private function attachPrimaryDomain(array $account, string $domain): void
    {
        $now = gmdate('c');
        $this->db->prepare(
            "UPDATE hosting_accounts
             SET primary_domain = :d, status = 'pending', last_error = NULL, updated_at = :u
             WHERE id = :id"
        )->execute([
            ':d' => $domain,
            ':u' => $now,
            ':id' => $account['id'],
        ]);
        $orderId = (int) ($account['order_id'] ?? 0);
        if ($orderId > 0) {
            $this->db->prepare('UPDATE orders SET domain = :d, updated_at = :u WHERE id = :id AND (domain IS NULL OR domain = \'\')')
                ->execute([':d' => $domain, ':u' => $now, ':id' => $orderId]);
        }
        $this->enqueueJob((int) $account['id'], (int) $account['user_id'], 'provision', [
            'domain' => $domain,
            'source' => 'domain_added',
        ]);
    }

    private function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = preg_replace('#^www\.#', '', $domain) ?? $domain;
        $domain = rtrim($domain, '/');
        if ($domain === '') {
            return '';
        }
        if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $domain)) {
            throw new InvalidArgumentException('Informe um domínio válido, ex: meusite.com.br.');
        }
        return $domain;
    }

    /** @return array<string, mixed>|null */
    private function findBlockedAccountForUser(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM hosting_accounts WHERE user_id = :u AND status = 'blocked_no_domain' ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([':u' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    private function findActiveAccountForUser(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM hosting_accounts WHERE user_id = :u AND status IN ('active','creating','pending') ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([':u' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @param array<string, mixed> $row */
    private function presentAccount(array $row): array
    {
        return [
            'id' => $row['public_id'] ?? '',
            'userId' => (int) ($row['user_id'] ?? 0),
            'userEmail' => $row['user_email'] ?? null,
            'userName' => $row['user_name'] ?? null,
            'planCode' => $row['plan_code'] ?? '',
            'daUsername' => $row['da_username'] ?? '',
            'package' => $row['da_package'] ?? '',
            'primaryDomain' => $row['primary_domain'] ?? null,
            'status' => $row['status'] ?? '',
            'lastError' => $row['last_error'] ?? null,
            'provisionedAt' => $row['provisioned_at'] ?? null,
            'createdAt' => $row['created_at'] ?? null,
            'updatedAt' => $row['updated_at'] ?? null,
        ];
    }

    private function ensureTables(): void
    {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $id = $driver === 'mysql'
            ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY'
            : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $int = $driver === 'mysql' ? 'BIGINT UNSIGNED' : 'INTEGER';

        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS hosting_accounts (
                id {$id},
                public_id VARCHAR(36) NOT NULL,
                user_id {$int} NOT NULL,
                order_id {$int} DEFAULT NULL,
                service_id {$int} DEFAULT NULL,
                plan_code VARCHAR(40) NOT NULL,
                da_username VARCHAR(64) NOT NULL,
                da_password_enc TEXT DEFAULT NULL,
                da_package VARCHAR(80) NOT NULL DEFAULT '',
                primary_domain VARCHAR(190) DEFAULT NULL,
                status VARCHAR(40) NOT NULL DEFAULT 'pending',
                last_error TEXT DEFAULT NULL,
                provisioned_at TEXT DEFAULT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )"
        );
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS hosting_jobs (
                id {$id},
                public_id VARCHAR(36) NOT NULL,
                hosting_account_id {$int} NOT NULL,
                user_id {$int} NOT NULL,
                job_type VARCHAR(40) NOT NULL,
                payload_json TEXT DEFAULT NULL,
                status VARCHAR(40) NOT NULL DEFAULT 'pending',
                attempts INTEGER NOT NULL DEFAULT 0,
                last_error TEXT DEFAULT NULL,
                available_at TEXT DEFAULT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )"
        );
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS hosting_audit_logs (
                id {$id},
                hosting_account_id {$int} NOT NULL,
                user_id {$int} NOT NULL,
                actor VARCHAR(40) NOT NULL,
                action VARCHAR(80) NOT NULL,
                detail_json TEXT DEFAULT NULL,
                created_at TEXT NOT NULL
            )"
        );

        foreach ([
            'CREATE UNIQUE INDEX idx_hosting_accounts_public ON hosting_accounts (public_id)',
            'CREATE UNIQUE INDEX idx_hosting_accounts_username ON hosting_accounts (da_username)',
            'CREATE INDEX idx_hosting_accounts_user ON hosting_accounts (user_id)',
            'CREATE UNIQUE INDEX idx_hosting_jobs_public ON hosting_jobs (public_id)',
            'CREATE INDEX idx_hosting_jobs_status ON hosting_jobs (status)',
            'CREATE INDEX idx_hosting_audit_account ON hosting_audit_logs (hosting_account_id)',
        ] as $sql) {
            try {
                $this->db->exec($sql);
            } catch (Throwable $e) {
                // exists
            }
        }

        // Domain/site SSL columns
        foreach ([
            'user_domains' => [
                'ssl_status' => "VARCHAR(40) NOT NULL DEFAULT 'none'",
                'hosting_account_id' => $int . ' DEFAULT NULL',
            ],
            'user_sites' => [
                'site_type' => "VARCHAR(40) NOT NULL DEFAULT 'blank'",
                'ssl_status' => "VARCHAR(40) NOT NULL DEFAULT 'none'",
            ],
        ] as $table => $cols) {
            $existing = geekhost_table_columns($this->db, $table);
            if ($existing === []) {
                continue;
            }
            foreach ($cols as $col => $def) {
                if (!in_array($col, $existing, true)) {
                    try {
                        $this->db->exec("ALTER TABLE {$table} ADD COLUMN {$col} {$def}");
                    } catch (Throwable $e) {
                        // ignore
                    }
                }
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
