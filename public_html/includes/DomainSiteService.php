<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/plans.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/NotificationService.php';

final class DomainSiteService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = geekhost_db();
        $this->ensureTables();
    }

    public function userHasPaidPlan(int $userId): bool
    {
        // hosting_status "pending" is also set at account creation before payment,
        // so require a paid order or a provisioned/queued service.
        $any = $this->db->prepare(
            "SELECT
                (SELECT COUNT(*) FROM orders WHERE user_id = :u AND status = 'paid_pending_provisioning')
              + (SELECT COUNT(*) FROM services WHERE user_id = :u)
             AS c"
        );
        $any->execute([':u' => $userId]);
        return (int) $any->fetchColumn() > 0;
    }

    public function resolveUserPlanCode(int $userId): string
    {
        $stmt = $this->db->prepare(
            "SELECT plan_code FROM orders
             WHERE user_id = :u AND status = 'paid_pending_provisioning'
             ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([':u' => $userId]);
        $code = (string) ($stmt->fetchColumn() ?: '');
        if ($code !== '' && geekhost_plan($code)) {
            return $code;
        }
        $stmt = $this->db->prepare('SELECT plan_code FROM services WHERE user_id = :u ORDER BY id DESC LIMIT 1');
        $stmt->execute([':u' => $userId]);
        $code = (string) ($stmt->fetchColumn() ?: 'light');
        return geekhost_plan($code) ? $code : 'light';
    }

    /** @return list<array<string, mixed>> */
    public function listDomains(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM user_domains WHERE user_id = :u ORDER BY id DESC');
        $stmt->execute([':u' => $userId]);
        $rows = $stmt->fetchAll() ?: [];
        return array_map(fn ($row) => $this->presentDomain($row), $rows);
    }

    /** @return list<array<string, mixed>> */
    public function listSites(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT s.*, d.domain AS domain_name, d.ns_ok
             FROM user_sites s
             INNER JOIN user_domains d ON d.id = s.domain_id
             WHERE s.user_id = :u
             ORDER BY s.id DESC'
        );
        $stmt->execute([':u' => $userId]);
        $rows = $stmt->fetchAll() ?: [];
        return array_map(fn ($row) => $this->presentSite($row), $rows);
    }

    public function addDomain(int $userId, string $domain): array
    {
        if (!$this->userHasPaidPlan($userId)) {
            throw new InvalidArgumentException(
                'Conclua o pagamento do plano de hospedagem antes de cadastrar um domínio.'
            );
        }
        $domain = $this->normalizeDomain($domain);
        $planCode = $this->resolveUserPlanCode($userId);
        $limit = geekhost_plan_domain_limit($planCode);
        $count = $this->countDomains($userId);
        if ($count >= $limit) {
            throw new InvalidArgumentException(
                $limit === 1
                    ? 'O Plano Light permite apenas 1 domínio. Faça upgrade para cadastrar mais.'
                    : "Seu plano permite no máximo {$limit} domínios."
            );
        }

        $exists = $this->db->prepare('SELECT id FROM user_domains WHERE user_id = :u AND domain = :d LIMIT 1');
        $exists->execute([':u' => $userId, ':d' => $domain]);
        if ($exists->fetch()) {
            throw new InvalidArgumentException('Este domínio já está cadastrado na sua conta.');
        }

        $check = $this->checkNameservers($domain);
        $now = gmdate('c');
        $publicId = $this->uuid();
        $stmt = $this->db->prepare(
            'INSERT INTO user_domains (
                public_id, user_id, domain, plan_code, ns_ok, ns_checked_at, ns_raw_json, status, created_at, updated_at
            ) VALUES (
                :p, :u, :d, :plan, :ok, :checked, :raw, :st, :c, :up
            )'
        );
        $stmt->execute([
            ':p' => $publicId,
            ':u' => $userId,
            ':d' => $domain,
            ':plan' => $planCode,
            ':ok' => $check['ok'] ? 1 : 0,
            ':checked' => $now,
            ':raw' => json_encode($check, JSON_UNESCAPED_UNICODE),
            ':st' => $check['ok'] ? 'pointed' : 'awaiting_dns',
            ':c' => $now,
            ':up' => $now,
        ]);

        $notifications = new NotificationService();
        $notifications->notify(
            $userId,
            'domain_registered',
            'Domínio cadastrado',
            'O domínio ' . $domain . ' foi adicionado à sua conta.',
            function_exists('url') ? url('/painel/dominios') : '/painel/dominios'
        );
        if ($check['ok']) {
            $notifications->notify(
                $userId,
                'domain_configured',
                'Domínio configurado',
                'Nameservers GeekHost confirmados para ' . $domain . '.',
                function_exists('url') ? url('/painel/dominios') : '/painel/dominios'
            );
        }

        $row = $this->findDomainByPublicId($publicId);
        return $this->presentDomain($row ?? []);
    }

    public function refreshDomainDns(int $userId, string $publicId): array
    {
        $row = $this->findDomainForUser($userId, $publicId);
        if (!$row) {
            throw new InvalidArgumentException('Domínio não encontrado.');
        }
        $wasOk = (int) ($row['ns_ok'] ?? 0) === 1;
        $check = $this->checkNameservers((string) $row['domain']);
        $now = gmdate('c');
        $stmt = $this->db->prepare(
            'UPDATE user_domains SET ns_ok = :ok, ns_checked_at = :c, ns_raw_json = :raw, status = :st, updated_at = :u WHERE id = :id'
        );
        $stmt->execute([
            ':ok' => $check['ok'] ? 1 : 0,
            ':c' => $now,
            ':raw' => json_encode($check, JSON_UNESCAPED_UNICODE),
            ':st' => $check['ok'] ? 'pointed' : 'awaiting_dns',
            ':u' => $now,
            ':id' => $row['id'],
        ]);
        if (!$wasOk && $check['ok']) {
            (new NotificationService())->notify(
                $userId,
                'domain_configured',
                'Domínio configurado',
                'Nameservers GeekHost confirmados para ' . $row['domain'] . '.',
                function_exists('url') ? url('/painel/dominios') : '/painel/dominios'
            );
        }
        return $this->presentDomain($this->findDomainByPublicId($publicId) ?? []);
    }

    public function createSite(int $userId, string $domainPublicId, string $label = ''): array
    {
        if ($this->countDomains($userId) < 1) {
            throw new InvalidArgumentException(
                'Cadastre um domínio antes de criar um site.'
            );
        }
        $domain = $this->findDomainForUser($userId, $domainPublicId);
        if (!$domain) {
            throw new InvalidArgumentException('Domínio não encontrado.');
        }
        if (!(int) ($domain['ns_ok'] ?? 0)) {
            throw new InvalidArgumentException(
                'Aguarde o apontamento correto para ns1.geekhost.com.br e ns2.geekhost.com.br (propagação: 2 a 72 horas).'
            );
        }

        $planCode = $this->resolveUserPlanCode($userId);
        $limit = geekhost_plan_site_limit($planCode);
        if ($this->countSites($userId) >= $limit) {
            throw new InvalidArgumentException(
                $limit === 1
                    ? 'O Plano Light permite apenas 1 site.'
                    : "Seu plano permite no máximo {$limit} sites."
            );
        }

        $dup = $this->db->prepare('SELECT id FROM user_sites WHERE domain_id = :d LIMIT 1');
        $dup->execute([':d' => $domain['id']]);
        if ($dup->fetch()) {
            throw new InvalidArgumentException('Já existe um site para este domínio.');
        }

        $now = gmdate('c');
        $publicId = $this->uuid();
        $title = trim($label) !== '' ? trim($label) : (string) $domain['domain'];
        $stmt = $this->db->prepare(
            'INSERT INTO user_sites (
                public_id, user_id, domain_id, title, status, wordpress_status, created_at, updated_at
            ) VALUES (
                :p, :u, :d, :t, :st, :wp, :c, :up
            )'
        );
        $stmt->execute([
            ':p' => $publicId,
            ':u' => $userId,
            ':d' => $domain['id'],
            ':t' => $title,
            ':st' => 'ready',
            ':wp' => 'not_installed',
            ':c' => $now,
            ':up' => $now,
        ]);

        (new NotificationService())->notify(
            $userId,
            'site_created',
            'Site criado',
            'O site ' . $title . ' foi criado com sucesso.',
            function_exists('url') ? url('/painel/sites') : '/painel/sites'
        );

        return $this->presentSite($this->findSiteByPublicId($publicId) ?? []);
    }

    public function requestWordpress(int $userId, string $sitePublicId): array
    {
        $site = $this->findSiteForUser($userId, $sitePublicId);
        if (!$site) {
            throw new InvalidArgumentException('Site não encontrado.');
        }
        $now = gmdate('c');
        $stmt = $this->db->prepare(
            "UPDATE user_sites SET wordpress_status = 'queued', status = 'wordpress_queued', updated_at = :u WHERE id = :id"
        );
        $stmt->execute([':u' => $now, ':id' => $site['id']]);
        geekhost_log('wordpress_install_queued', [
            'user_id' => $userId,
            'site' => $sitePublicId,
            'domain' => $site['domain'] ?? null,
        ]);
        return $this->presentSite($this->findSiteByPublicId($sitePublicId) ?? []);
    }

    /**
     * @return array{ok:bool, found:list<string>, missing:list<string>, message:string}
     */
    public function checkNameservers(string $domain): array
    {
        $expected = geekhost_nameservers();
        $found = [];
        if (function_exists('dns_get_record')) {
            $records = @dns_get_record($domain, DNS_NS);
            if (is_array($records)) {
                foreach ($records as $record) {
                    $target = strtolower(rtrim((string) ($record['target'] ?? ''), '.'));
                    if ($target !== '') {
                        $found[] = $target;
                    }
                }
            }
        }
        $found = array_values(array_unique($found));
        $missing = [];
        foreach ($expected as $ns) {
            if (!in_array($ns, $found, true)) {
                $missing[] = $ns;
            }
        }
        $ok = $missing === [];
        return [
            'ok' => $ok,
            'found' => $found,
            'missing' => $missing,
            'expected' => $expected,
            'message' => $ok
                ? 'Nameservers GeekHost detectados.'
                : 'Ainda não encontramos ns1.geekhost.com.br e ns2.geekhost.com.br. A propagação DNS pode levar de 2 a 72 horas.',
        ];
    }

    private function countDomains(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM user_domains WHERE user_id = :u');
        $stmt->execute([':u' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    private function countSites(int $userId): int
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM user_sites WHERE user_id = :u');
        $stmt->execute([':u' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    private function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain) ?? $domain;
        $domain = preg_replace('#^www\.#', '', $domain) ?? $domain;
        $domain = rtrim($domain, '/');
        if ($domain === '' || !preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $domain)) {
            throw new InvalidArgumentException('Informe um domínio válido (ex: meusite.com.br).');
        }
        return $domain;
    }

    /** @return array<string, mixed>|null */
    private function findDomainByPublicId(string $publicId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM user_domains WHERE public_id = :p LIMIT 1');
        $stmt->execute([':p' => $publicId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    private function findDomainForUser(int $userId, string $publicId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM user_domains WHERE public_id = :p AND user_id = :u LIMIT 1');
        $stmt->execute([':p' => $publicId, ':u' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    private function findSiteByPublicId(string $publicId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT s.*, d.domain AS domain_name, d.ns_ok
             FROM user_sites s
             INNER JOIN user_domains d ON d.id = s.domain_id
             WHERE s.public_id = :p LIMIT 1'
        );
        $stmt->execute([':p' => $publicId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    private function findSiteForUser(int $userId, string $publicId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT s.*, d.domain AS domain_name, d.ns_ok
             FROM user_sites s
             INNER JOIN user_domains d ON d.id = s.domain_id
             WHERE s.public_id = :p AND s.user_id = :u LIMIT 1'
        );
        $stmt->execute([':p' => $publicId, ':u' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @param array<string, mixed> $row */
    private function presentDomain(array $row): array
    {
        $raw = [];
        if (!empty($row['ns_raw_json'])) {
            $decoded = json_decode((string) $row['ns_raw_json'], true);
            if (is_array($decoded)) {
                $raw = $decoded;
            }
        }
        return [
            'id' => $row['public_id'] ?? '',
            'domain' => $row['domain'] ?? '',
            'planCode' => $row['plan_code'] ?? '',
            'nsOk' => (bool) ((int) ($row['ns_ok'] ?? 0)),
            'nsCheckedAt' => $row['ns_checked_at'] ?? null,
            'status' => $row['status'] ?? 'awaiting_dns',
            'nsFound' => $raw['found'] ?? [],
            'nsMissing' => $raw['missing'] ?? geekhost_nameservers(),
            'nsMessage' => $raw['message'] ?? '',
        ];
    }

    /** @param array<string, mixed> $row */
    private function presentSite(array $row): array
    {
        $domain = (string) ($row['domain_name'] ?? $row['domain'] ?? '');
        $wp = (string) ($row['wordpress_status'] ?? 'not_installed');
        return [
            'id' => $row['public_id'] ?? '',
            'title' => $row['title'] ?? $domain,
            'domain' => $domain,
            'status' => $row['status'] ?? 'ready',
            'wordpressStatus' => $wp,
            'nsOk' => (bool) ((int) ($row['ns_ok'] ?? 0)),
            'adminUrl' => $domain !== '' ? 'https://' . $domain . '/wp-admin' : null,
            'siteUrl' => $domain !== '' ? 'https://' . $domain : null,
        ];
    }

    private function ensureTables(): void
    {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $id = $driver === 'mysql'
            ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY'
            : 'INTEGER PRIMARY KEY AUTOINCREMENT';

        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS user_domains (
                id {$id},
                public_id VARCHAR(36) NOT NULL,
                user_id " . ($driver === 'mysql' ? 'BIGINT UNSIGNED' : 'INTEGER') . " NOT NULL,
                domain VARCHAR(190) NOT NULL,
                plan_code VARCHAR(40) NOT NULL DEFAULT 'light',
                ns_ok INTEGER NOT NULL DEFAULT 0,
                ns_checked_at TEXT DEFAULT NULL,
                ns_raw_json TEXT DEFAULT NULL,
                status VARCHAR(40) NOT NULL DEFAULT 'awaiting_dns',
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )"
        );
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS user_sites (
                id {$id},
                public_id VARCHAR(36) NOT NULL,
                user_id " . ($driver === 'mysql' ? 'BIGINT UNSIGNED' : 'INTEGER') . " NOT NULL,
                domain_id " . ($driver === 'mysql' ? 'BIGINT UNSIGNED' : 'INTEGER') . " NOT NULL,
                title VARCHAR(190) NOT NULL DEFAULT '',
                status VARCHAR(40) NOT NULL DEFAULT 'ready',
                wordpress_status VARCHAR(40) NOT NULL DEFAULT 'not_installed',
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )"
        );

        foreach ([
            'CREATE UNIQUE INDEX idx_user_domains_public ON user_domains (public_id)',
            'CREATE UNIQUE INDEX idx_user_domains_user_domain ON user_domains (user_id, domain)',
            'CREATE UNIQUE INDEX idx_user_sites_public ON user_sites (public_id)',
            'CREATE INDEX idx_user_sites_user ON user_sites (user_id)',
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
