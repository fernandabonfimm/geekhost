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
    public const ROLES = ['super_admin', 'financeiro', 'suporte', 'comercial'];

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
}
