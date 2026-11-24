<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/logger.php';

/**
 * Authorization for the standalone GeekHost Admin app (/admin).
 * Client panel (/painel) is unrelated — staff must be in admin_users.
 */
final class AdminGate
{
    public const ROLES = ['super_admin', 'financeiro', 'suporte', 'comercial', 'staff'];

    /** @var array<string, string> */
    public const PAGES = [
        'overview' => 'Visão geral',
        'vendas' => 'Vendas',
        'faturamento' => 'Faturamento',
        'cobrancas' => 'Cobranças',
        'cancelamentos' => 'Cancelamentos',
        'datas' => 'Datas de cobrança',
        'clientes' => 'Clientes',
        'hospedagens' => 'Hospedagens',
        'dominios' => 'Domínios',
        'sites' => 'Sites',
        'planos' => 'Planos e pacotes',
        'administradores' => 'Administradores',
        'relatorios' => 'Relatórios',
        'auditoria' => 'Logs e auditoria',
        'configuracoes' => 'Configurações',
        'conhecimento' => 'Base de conhecimento',
    ];

    /** @var array<string, list<string>> */
    private const ROLE_PAGES = [
        'super_admin' => ['*'],
        'financeiro' => ['overview', 'vendas', 'faturamento', 'cobrancas', 'cancelamentos', 'datas', 'clientes', 'relatorios'],
        'suporte' => ['overview', 'clientes', 'hospedagens', 'dominios', 'sites', 'cobrancas', 'cancelamentos', 'conhecimento'],
        'comercial' => ['overview', 'vendas', 'clientes', 'planos', 'relatorios'],
        'staff' => ['overview'],
    ];

    /** @var array<string, list<string>> */
    private const ROLE_PERMISSIONS = [
        'super_admin' => ['*'],
        'financeiro' => [
            'dashboard.view', 'sales.view', 'sales.manage', 'billing.view', 'billing.export',
            'charges.view', 'charges.manage', 'clients.view', 'reports.view', 'notifications.view',
        ],
        'suporte' => [
            'dashboard.view', 'clients.view', 'clients.manage', 'hosting.view', 'hosting.manage',
            'domains.view', 'sites.view', 'charges.view', 'notifications.view', 'tickets.view',
        ],
        'comercial' => [
            'dashboard.view', 'sales.view', 'sales.manage', 'clients.view', 'clients.manage',
            'plans.view', 'reports.view', 'notifications.view',
        ],
    ];

    private PDO $db;
    private Auth $auth;

    public function __construct(?Auth $auth = null)
    {
        $this->db = geekhost_db();
        $this->auth = $auth ?? new Auth();
        $this->ensureTables();
        $this->seedFromEnv();
    }

    public function auth(): Auth
    {
        return $this->auth;
    }

    /**
     * @return array{user: array<string,mixed>, admin: array<string,mixed>}
     */
    public function requireAdmin(?string $permission = null): array
    {
        $user = $this->auth->user();
        if (!$user) {
            header('Location: ' . (function_exists('url') ? url('/admin/login') : '/admin/login'));
            exit;
        }
        $admin = $this->findAdminByUserId((int) $user['id']);
        if (!$admin || ($admin['status'] ?? '') !== 'active') {
            // Also allow legacy env allowlist once, then auto-provision super_admin
            if ($this->emailInEnvAllowlist((string) ($user['email'] ?? ''))) {
                $admin = $this->ensureAdminForUser((int) $user['id'], 'super_admin');
            }
        }
        if (!$admin || ($admin['status'] ?? '') !== 'active') {
            http_response_code(403);
            throw new RuntimeException('Acesso restrito ao Admin GeekHost.');
        }
        if ($permission !== null && !$this->can($admin, $permission)) {
            http_response_code(403);
            throw new RuntimeException('Permissão insuficiente: ' . $permission);
        }
        return ['user' => $user, 'admin' => $admin];
    }

    /**
     * @return array{user: array<string,mixed>, admin: array<string,mixed>}
     */
    public function requireApiAdmin(?string $permission = null): array
    {
        $user = $this->auth->requireApiUser();
        $admin = $this->findAdminByUserId((int) $user['id']);
        if ((!$admin || ($admin['status'] ?? '') !== 'active') && $this->emailInEnvAllowlist((string) ($user['email'] ?? ''))) {
            $admin = $this->ensureAdminForUser((int) $user['id'], 'super_admin');
        }
        if (!$admin || ($admin['status'] ?? '') !== 'active') {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Acesso restrito ao Admin GeekHost.']);
            exit;
        }
        if ($permission !== null && !$this->can($admin, $permission)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Permissão insuficiente.']);
            exit;
        }
        return ['user' => $user, 'admin' => $admin];
    }

    /** @param array<string, mixed> $admin */
    public function can(array $admin, string $permission): bool
    {
        $role = (string) ($admin['role'] ?? '');
        $perms = self::ROLE_PERMISSIONS[$role] ?? [];
        if (in_array('*', $perms, true)) {
            return true;
        }
        return in_array($permission, $perms, true);
    }

    /** @param array<string, mixed> $admin */
    public function isSuper(array $admin): bool
    {
        return (string) ($admin['role'] ?? '') === 'super_admin';
    }

    /**
     * Pages this admin may open. Super admin always sees every page.
     * A saved permissions_json list wins for every other role.
     *
     * @param array<string, mixed> $admin
     * @return list<string>
     */
    public function pagesFor(array $admin): array
    {
        $all = array_keys(self::PAGES);
        if ($this->isSuper($admin)) {
            return $all;
        }
        $raw = $admin['permissions_json'] ?? null;
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return array_values(array_intersect($all, array_map('strval', $decoded)));
            }
        }
        $rolePages = self::ROLE_PAGES[(string) ($admin['role'] ?? '')] ?? ['overview'];
        if (in_array('*', $rolePages, true)) {
            return $all;
        }
        return array_values(array_intersect($all, $rolePages));
    }

    /** @return list<array<string, mixed>> */
    public function listStaff(): array
    {
        $rows = $this->db->query(
            'SELECT a.*, u.email, u.name_enc
             FROM admin_users a
             INNER JOIN users u ON u.id = a.user_id
             ORDER BY a.id DESC'
        )->fetchAll() ?: [];
        foreach ($rows as &$row) {
            $name = geekhost_decrypt((string) ($row['name_enc'] ?? ''));
            $row['name'] = is_string($name) && $name !== '' ? $name : (string) $row['email'];
            $decoded = json_decode((string) ($row['permissions_json'] ?? ''), true);
            $row['pages'] = is_array($decoded) ? array_values(array_map('strval', $decoded)) : [];
        }
        unset($row);
        return $rows;
    }

    /**
     * @param list<string> $pages
     * @return array{email:string,password:string,emailed:bool}
     */
    public function createStaff(string $name, string $email, string $password, array $pages, int $actorAdminId, int $actorUserId): array
    {
        $name = trim($name);
        $email = strtolower(trim($email));
        $pages = array_values(array_intersect(array_keys(self::PAGES), $pages));
        if ($name === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Informe nome e e-mail válidos.');
        }
        if ($pages === []) {
            throw new InvalidArgumentException('Marque ao menos uma página que este admin pode ver.');
        }
        if ($password === '') {
            $password = bin2hex(random_bytes(4)) . 'Gh!';
        }
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('A senha deve ter pelo menos 8 caracteres.');
        }
        if ($this->auth->findByEmail($email)) {
            throw new InvalidArgumentException('Já existe uma conta com este e-mail. Use outro e-mail para o admin.');
        }

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
        $this->db->prepare(
            'INSERT INTO admin_users (user_id, role, status, created_by, permissions_json, created_at, updated_at)
             VALUES (:u, :r, :s, :by, :p, :c, :up)'
        )->execute([
            ':u' => $userId,
            ':r' => 'staff',
            ':s' => 'active',
            ':by' => $actorUserId,
            ':p' => json_encode($pages, JSON_UNESCAPED_UNICODE),
            ':c' => $now,
            ':up' => $now,
        ]);
        $created = $this->findAdminByUserId($userId);
        $this->audit($actorAdminId, $userId, 'admin_created', 'admin_user', (string) ($created['id'] ?? ''), 'ok', [
            'email' => $email,
            'pages' => $pages,
        ]);
        $emailed = $this->emailStaffCredentials($email, $name, $password);
        return ['email' => $email, 'password' => $password, 'emailed' => $emailed];
    }

    /** @param list<string> $pages */
    public function updateStaffPages(int $adminId, array $pages, int $actorAdminId, int $actorUserId): void
    {
        $stmt = $this->db->prepare('SELECT * FROM admin_users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $adminId]);
        $admin = $stmt->fetch();
        if (!$admin) {
            throw new InvalidArgumentException('Admin não encontrado.');
        }
        if ($this->isSuper($admin)) {
            throw new InvalidArgumentException('O super admin sempre vê todas as páginas.');
        }
        $pages = array_values(array_intersect(array_keys(self::PAGES), $pages));
        if ($pages === []) {
            throw new InvalidArgumentException('Marque ao menos uma página.');
        }
        $this->db->prepare('UPDATE admin_users SET permissions_json = :p, updated_at = :u WHERE id = :id')
            ->execute([
                ':p' => json_encode($pages, JSON_UNESCAPED_UNICODE),
                ':u' => gmdate('c'),
                ':id' => $adminId,
            ]);
        $this->audit($actorAdminId, $actorUserId, 'admin_pages_updated', 'admin_user', (string) $adminId, 'ok', [
            'pages' => $pages,
        ]);
    }

    public function setStaffStatus(int $adminId, string $status, int $actorAdminId, int $actorUserId): void
    {
        if (!in_array($status, ['active', 'suspended'], true)) {
            throw new InvalidArgumentException('Status inválido.');
        }
        $stmt = $this->db->prepare('SELECT * FROM admin_users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $adminId]);
        $admin = $stmt->fetch();
        if (!$admin) {
            throw new InvalidArgumentException('Admin não encontrado.');
        }
        if ((int) $admin['user_id'] === $actorUserId) {
            throw new InvalidArgumentException('Você não pode desativar a própria conta.');
        }
        if ($this->isSuper($admin) && $status !== 'active') {
            throw new InvalidArgumentException('Não é possível desativar um super admin por aqui.');
        }
        $this->db->prepare('UPDATE admin_users SET status = :s, updated_at = :u WHERE id = :id')
            ->execute([':s' => $status, ':u' => gmdate('c'), ':id' => $adminId]);
        $this->audit($actorAdminId, $actorUserId, 'admin_status', 'admin_user', (string) $adminId, 'ok', [
            'status' => $status,
        ]);
    }

    public function audit(
        int $adminUserId,
        int $userId,
        string $action,
        string $resourceType,
        ?string $resourceId,
        string $result,
        array $detail = []
    ): void {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        $this->db->prepare(
            'INSERT INTO admin_audit_logs (
                admin_user_id, user_id, action, resource_type, resource_id, result, ip, detail_json, created_at
            ) VALUES (
                :a, :u, :action, :rt, :rid, :res, :ip, :d, :c
            )'
        )->execute([
            ':a' => $adminUserId,
            ':u' => $userId,
            ':action' => $action,
            ':rt' => $resourceType,
            ':rid' => $resourceId,
            ':res' => $result,
            ':ip' => $ip,
            ':d' => json_encode($detail, JSON_UNESCAPED_UNICODE),
            ':c' => gmdate('c'),
        ]);
    }

    /** @return array<string, mixed>|null */
    public function findAdminByUserId(int $userId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM admin_users WHERE user_id = :u LIMIT 1');
        $stmt->execute([':u' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array<string, mixed> */
    public function ensureAdminForUser(int $userId, string $role = 'super_admin'): array
    {
        $existing = $this->findAdminByUserId($userId);
        if ($existing) {
            if (($existing['status'] ?? '') !== 'active') {
                $this->db->prepare("UPDATE admin_users SET status = 'active', updated_at = :u WHERE id = :id")
                    ->execute([':u' => gmdate('c'), ':id' => $existing['id']]);
                $existing['status'] = 'active';
            }
            return $existing;
        }
        if (!in_array($role, self::ROLES, true)) {
            $role = 'super_admin';
        }
        $now = gmdate('c');
        $this->db->prepare(
            'INSERT INTO admin_users (user_id, role, status, created_at, updated_at) VALUES (:u, :r, :s, :c, :up)'
        )->execute([
            ':u' => $userId,
            ':r' => $role,
            ':s' => 'active',
            ':c' => $now,
            ':up' => $now,
        ]);
        return $this->findAdminByUserId($userId) ?? [];
    }

    public function emailInEnvAllowlist(string $email): bool
    {
        $raw = (string) env('GEEKHOST_ADMIN_EMAILS', 'diretoria@geekhost.com.br');
        $list = array_filter(array_map(
            static fn ($e) => strtolower(trim($e)),
            explode(',', $raw)
        ));
        return in_array(strtolower(trim($email)), $list, true);
    }

    private function seedFromEnv(): void
    {
        $raw = (string) env('GEEKHOST_ADMIN_EMAILS', '');
        if ($raw === '') {
            return;
        }
        foreach (explode(',', $raw) as $email) {
            $email = strtolower(trim($email));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $stmt = $this->db->prepare('SELECT id FROM users WHERE email = :e LIMIT 1');
            $stmt->execute([':e' => $email]);
            $userId = (int) ($stmt->fetchColumn() ?: 0);
            if ($userId > 0) {
                $this->ensureAdminForUser($userId, 'super_admin');
            }
        }
    }

    private function ensureTables(): void
    {
        $driver = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME);
        $id = $driver === 'mysql'
            ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY'
            : 'INTEGER PRIMARY KEY AUTOINCREMENT';
        $int = $driver === 'mysql' ? 'BIGINT UNSIGNED' : 'INTEGER';

        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS admin_users (
                id {$id},
                user_id {$int} NOT NULL,
                role VARCHAR(40) NOT NULL DEFAULT 'suporte',
                status VARCHAR(40) NOT NULL DEFAULT 'active',
                created_by {$int} DEFAULT NULL,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL
            )"
        );
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS admin_audit_logs (
                id {$id},
                admin_user_id {$int} NOT NULL,
                user_id {$int} NOT NULL,
                action VARCHAR(80) NOT NULL,
                resource_type VARCHAR(80) NOT NULL DEFAULT '',
                resource_id VARCHAR(80) DEFAULT NULL,
                result VARCHAR(40) NOT NULL DEFAULT 'ok',
                ip VARCHAR(64) DEFAULT NULL,
                detail_json TEXT DEFAULT NULL,
                created_at TEXT NOT NULL
            )"
        );
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS admin_notifications (
                id {$id},
                type VARCHAR(80) NOT NULL,
                title VARCHAR(190) NOT NULL,
                body TEXT NOT NULL,
                link VARCHAR(255) DEFAULT NULL,
                read_at TEXT DEFAULT NULL,
                created_at TEXT NOT NULL
            )"
        );
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS app_settings (
                id {$id},
                setting_key VARCHAR(120) NOT NULL,
                value_json TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                updated_by {$int} DEFAULT NULL
            )"
        );

        $cols = geekhost_table_columns($this->db, 'admin_users');
        if ($cols !== [] && !in_array('permissions_json', $cols, true)) {
            try {
                $this->db->exec('ALTER TABLE admin_users ADD COLUMN permissions_json TEXT DEFAULT NULL');
            } catch (Throwable $e) {
                // exists
            }
        }

        foreach ([
            'CREATE UNIQUE INDEX idx_admin_users_user ON admin_users (user_id)',
            'CREATE INDEX idx_admin_audit_created ON admin_audit_logs (created_at)',
            'CREATE UNIQUE INDEX idx_app_settings_key ON app_settings (setting_key)',
        ] as $sql) {
            try {
                $this->db->exec($sql);
            } catch (Throwable $e) {
                // exists
            }
        }

        // Ensure DA reseller identity is recorded in settings (not a secret)
        $check = $this->db->prepare('SELECT id FROM app_settings WHERE setting_key = :k LIMIT 1');
        $check->execute([':k' => 'directadmin.reseller_username']);
        if (!$check->fetch()) {
            $this->db->prepare(
                'INSERT INTO app_settings (setting_key, value_json, updated_at) VALUES (:k, :v, :u)'
            )->execute([
                ':k' => 'directadmin.reseller_username',
                ':v' => json_encode('geek6923'),
                ':u' => gmdate('c'),
            ]);
        }
    }

    private function emailStaffCredentials(string $email, string $name, string $password): bool
    {
        require_once __DIR__ . '/Mailer.php';
        $login = function_exists('geekhost_public_url')
            ? geekhost_public_url('/admin/login')
            : '/admin/login';
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $safePass = htmlspecialchars($password, ENT_QUOTES, 'UTF-8');
        $safeUrl = htmlspecialchars($login, ENT_QUOTES, 'UTF-8');
        $html = '<!doctype html><html lang="pt-BR"><body style="font-family:Arial,sans-serif;color:#1a1e24;">'
            . '<h1>Seu acesso ao Admin GeekHost</h1>'
            . '<p>Olá, ' . $safeName . '.</p>'
            . '<p>Use estes dados para entrar no painel administrativo:</p>'
            . '<p><strong>E-mail:</strong> ' . $safeEmail . '<br><strong>Senha:</strong> ' . $safePass . '</p>'
            . '<p><a href="' . $safeUrl . '">Abrir o admin</a></p>'
            . '<p>Troque a senha depois do primeiro acesso. Você só verá as páginas liberadas para o seu usuário.</p>'
            . '</body></html>';
        return Mailer::send($email, 'Acesso ao Admin GeekHost', $html, false);
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
