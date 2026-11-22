<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

function geekhost_project_root(): string
{
    return dirname(__DIR__, 2);
}

function geekhost_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    geekhost_load_env();
    $driver = strtolower((string) env('DB_DRIVER', 'sqlite'));

    if ($driver === 'mysql') {
        $local = geekhost_is_local_http();
        $host = $local ? '127.0.0.1' : (string) env('DB_HOST', '127.0.0.1');
        $port = $local ? '3306' : (string) env('DB_PORT', '3306');
        $name = $local ? 'geekhost' : (string) env('DB_NAME', 'geekhost');
        $user = $local ? 'root' : (string) env('DB_USER', '');
        $pass = $local ? '' : (string) env('DB_PASS', '');
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        if ($local) {
            $bootstrap = new PDO(sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $host, $port), $user, $pass, $options);
            $bootstrap->exec('CREATE DATABASE IF NOT EXISTS `geekhost` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $bootstrap = null;
        }
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);
        $pdo = new PDO($dsn, $user, $pass, $options);
    } else {
        $relative = env('DB_PATH', 'storage/geekhost.sqlite');
        $path = $relative;
        if (!preg_match('#^(?:[a-zA-Z]:\\\\|/)#', (string) $relative)) {
            $path = geekhost_project_root() . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string) $relative);
        }
        $dir = dirname((string) $path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
    }

    geekhost_migrate($pdo);
    if (geekhost_is_local_http()) {
        try {
            geekhost_seed_local_demo($pdo);
        } catch (Throwable $e) {
            error_log('geekhost local seed: ' . $e->getMessage());
        }
    }
    return $pdo;
}

/**
 * Local XAMPP only: the browser is on 127.0.0.1/localhost AND the request
 * arrived on the loopback interface. A spoofed Host header on a public server
 * does not match, so production keeps the .env credentials.
 */
function geekhost_is_local_http(): bool
{
    if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
        return false;
    }
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $host = preg_replace('/:\d+$/', '', $host) ?? $host;
    $hostIsLoop = $host === '127.0.0.1' || $host === 'localhost' || $host === '::1' || str_starts_with($host, '127.');
    if (!$hostIsLoop) {
        return false;
    }
    $loop = static function (string $ip): bool {
        return $ip === '127.0.0.1' || $ip === '::1' || str_starts_with($ip, '127.');
    };
    $server = (string) ($_SERVER['SERVER_ADDR'] ?? '');
    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    return $loop($server) || $loop($remote);
}

function geekhost_core_tables_ready(PDO $pdo): bool
{
    foreach (['users', 'admin_users'] as $table) {
        try {
            $pdo->query('SELECT 1 FROM `' . str_replace('`', '``', $table) . '` LIMIT 1');
        } catch (Throwable $e) {
            return false;
        }
    }
    return true;
}

function geekhost_schema_file(): ?string
{
    $candidates = [
        geekhost_project_root() . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql',
        // public_html-only deploys (cPanel/DA): schema shipped next to the app
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql',
        dirname(__DIR__) . DIRECTORY_SEPARATOR . 'schema.sql',
    ];
    foreach ($candidates as $path) {
        if (is_readable($path)) {
            return $path;
        }
    }
    return null;
}

function geekhost_migrate(PDO $pdo): void
{
    $schemaFile = geekhost_schema_file();
    if ($schemaFile === null) {
        // Production often uploads only public_html + .env; tables come from install-db.php.
        if (geekhost_core_tables_ready($pdo)) {
            geekhost_ensure_columns($pdo);
            return;
        }
        throw new RuntimeException('Database schema file missing and core tables not found. Run install-db.php or upload database/schema.sql.');
    }
    $sql = file_get_contents($schemaFile);
    if ($sql === false) {
        throw new RuntimeException('Unable to read database schema.');
    }

    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $sql = str_replace('INTEGER PRIMARY KEY AUTOINCREMENT', 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY', $sql);
        // Keep FK column types aligned with MySQL BIGINT UNSIGNED PKs.
        $sql = preg_replace(
            '/\b(customer_id|user_id|order_id|subscription_id|domain_id)\s+INTEGER\b/',
            '$1 BIGINT UNSIGNED',
            $sql
        ) ?? $sql;
        $sql = preg_replace('/^CREATE UNIQUE INDEX IF NOT EXISTS .*?;$/m', '', $sql) ?? $sql;
        $sql = preg_replace('/^CREATE INDEX IF NOT EXISTS .*?;$/m', '', $sql) ?? $sql;
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    }

    $pdo->exec($sql);

    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    geekhost_ensure_columns($pdo);

    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $indexes = [
            'CREATE UNIQUE INDEX idx_customers_cpf_cnpj ON customers (cpf_cnpj)',
            'CREATE INDEX idx_customers_email ON customers (email)',
            'CREATE UNIQUE INDEX idx_customers_asaas ON customers (asaas_customer_id)',
            'CREATE UNIQUE INDEX idx_users_public_id ON users (public_id)',
            'CREATE UNIQUE INDEX idx_users_email ON users (email)',
            'CREATE UNIQUE INDEX idx_user_sessions_token ON user_sessions (token_hash)',
            'CREATE UNIQUE INDEX idx_orders_public_id ON orders (public_id)',
            'CREATE UNIQUE INDEX idx_orders_idempotency ON orders (idempotency_key)',
            'CREATE UNIQUE INDEX idx_orders_asaas_payment ON orders (asaas_payment_id)',
            'CREATE INDEX idx_orders_status ON orders (status)',
            'CREATE UNIQUE INDEX idx_subscriptions_public_id ON subscriptions (public_id)',
            'CREATE UNIQUE INDEX idx_subscriptions_asaas ON subscriptions (asaas_subscription_id)',
            'CREATE UNIQUE INDEX idx_services_public_id ON services (public_id)',
            'CREATE UNIQUE INDEX idx_webhook_events_key ON webhook_events (event_key)',
            'CREATE UNIQUE INDEX idx_user_domains_public ON user_domains (public_id)',
            'CREATE UNIQUE INDEX idx_user_domains_user_domain ON user_domains (user_id, domain)',
            'CREATE UNIQUE INDEX idx_user_sites_public ON user_sites (public_id)',
            'CREATE INDEX idx_user_sites_user ON user_sites (user_id)',
            'CREATE UNIQUE INDEX idx_hosting_accounts_public ON hosting_accounts (public_id)',
            'CREATE UNIQUE INDEX idx_hosting_accounts_username ON hosting_accounts (da_username)',
            'CREATE INDEX idx_hosting_accounts_user ON hosting_accounts (user_id)',
            'CREATE UNIQUE INDEX idx_hosting_jobs_public ON hosting_jobs (public_id)',
            'CREATE INDEX idx_hosting_jobs_status ON hosting_jobs (status)',
            'CREATE INDEX idx_hosting_audit_account ON hosting_audit_logs (hosting_account_id)',
            'CREATE UNIQUE INDEX idx_admin_users_user ON admin_users (user_id)',
            'CREATE INDEX idx_admin_audit_created ON admin_audit_logs (created_at)',
            'CREATE UNIQUE INDEX idx_app_settings_key ON app_settings (setting_key)',
        ];
        foreach ($indexes as $indexSql) {
            try {
                $pdo->exec($indexSql);
            } catch (Throwable $e) {
                // may already exist
            }
        }
    }
}

function geekhost_ensure_columns(PDO $pdo): void
{
    $columns = [
        'orders' => [
            'invoice_number' => 'INTEGER DEFAULT NULL',
            'user_id' => 'INTEGER DEFAULT NULL',
            'domain' => "VARCHAR(190) DEFAULT NULL",
            'renew_amount_cents' => 'INTEGER DEFAULT NULL',
            'due_date' => 'TEXT DEFAULT NULL',
        ],
        'subscriptions' => [
            'user_id' => 'INTEGER DEFAULT NULL',
            'domain' => "VARCHAR(190) DEFAULT NULL",
            'renew_amount_cents' => 'INTEGER NOT NULL DEFAULT 0',
            'asaas_credit_card_token' => 'TEXT DEFAULT NULL',
            'overdue_since' => 'TEXT DEFAULT NULL',
            'billing_day' => 'INTEGER DEFAULT NULL',
        ],
        'users' => [
            'password_is_set' => 'INTEGER NOT NULL DEFAULT 1',
            'password_setup_token_hash' => 'VARCHAR(64) DEFAULT NULL',
            'password_setup_expires' => 'TEXT DEFAULT NULL',
            'password_reset_token_hash' => 'VARCHAR(64) DEFAULT NULL',
            'password_reset_expires' => 'TEXT DEFAULT NULL',
            'email_verified' => 'INTEGER NOT NULL DEFAULT 1',
            'billing_day' => 'INTEGER DEFAULT NULL',
            'billing_day_source' => 'VARCHAR(20) DEFAULT NULL',
            'phone_enc' => "TEXT NOT NULL DEFAULT ''",
            'mobile_enc' => "TEXT NOT NULL DEFAULT ''",
            'postal_code_enc' => "TEXT NOT NULL DEFAULT ''",
            'address_enc' => "TEXT NOT NULL DEFAULT ''",
            'address_number_enc' => "TEXT NOT NULL DEFAULT ''",
            'complement_enc' => "TEXT NOT NULL DEFAULT ''",
            'province_enc' => "TEXT NOT NULL DEFAULT ''",
            'city_enc' => "TEXT NOT NULL DEFAULT ''",
        ],
        'user_sessions' => [
            'ip_address' => 'VARCHAR(64) DEFAULT NULL',
            'user_agent' => 'VARCHAR(255) DEFAULT NULL',
            'device_label' => 'VARCHAR(120) DEFAULT NULL',
            'last_seen_at' => 'TEXT DEFAULT NULL',
        ],
        'customers' => [
            'cpf_hash' => 'VARCHAR(64) DEFAULT NULL',
        ],
    ];

    foreach ($columns as $table => $defs) {
        $existing = geekhost_table_columns($pdo, $table);
        if ($existing === []) {
            continue;
        }
        foreach ($defs as $col => $definition) {
            if (!in_array($col, $existing, true)) {
                try {
                    $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$col} {$definition}");
                } catch (Throwable $e) {
                    // ignore race / exists
                }
            }
        }
    }

    geekhost_widen_sensitive($pdo);
    geekhost_seal_stored_secrets($pdo);
    geekhost_ensure_billing_tables($pdo);
}

function geekhost_ensure_billing_tables(PDO $pdo): void
{
    $mysql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    $id = $mysql ? 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $uid = $mysql ? 'BIGINT UNSIGNED' : 'INTEGER';
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS hosting_cancellations (
            id {$id},
            user_id {$uid} NOT NULL,
            email VARCHAR(190) NOT NULL,
            plan_code VARCHAR(80) DEFAULT NULL,
            domain VARCHAR(190) DEFAULT NULL,
            billing_type VARCHAR(40) DEFAULT NULL,
            billing_period VARCHAR(40) DEFAULT NULL,
            reasons_json TEXT NOT NULL,
            comment TEXT,
            refund_amount_cents INTEGER NOT NULL DEFAULT 0,
            access_until TEXT,
            status VARCHAR(40) NOT NULL DEFAULT 'scheduled',
            created_at TEXT NOT NULL
        )"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS billing_date_requests (
            id {$id},
            user_id {$uid} NOT NULL,
            email VARCHAR(190) NOT NULL,
            plan_code VARCHAR(80) DEFAULT NULL,
            billing_period VARCHAR(40) DEFAULT NULL,
            previous_day INTEGER DEFAULT NULL,
            requested_day INTEGER NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            admin_note TEXT,
            created_at TEXT NOT NULL,
            decided_at TEXT DEFAULT NULL
        )"
    );
}

/**
 * @return list<string>
 */
function geekhost_table_columns(PDO $pdo, string $table): array
{
    try {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $rows = $pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll();
            return array_values(array_map(static fn ($r) => (string) $r['name'], $rows));
        }
        $stmt = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '``', $table) . '`');
        $rows = $stmt ? $stmt->fetchAll() : [];
        return array_values(array_map(static fn ($r) => (string) $r['Field'], $rows));
    } catch (Throwable $e) {
        return [];
    }
}

function geekhost_widen_sensitive(PDO $pdo): void
{
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) !== 'mysql') {
        return;
    }
    foreach ([
        'ALTER TABLE customers DROP INDEX idx_customers_cpf_cnpj',
        'ALTER TABLE users MODIFY card_last4 VARCHAR(255) NULL',
        'ALTER TABLE users MODIFY asaas_credit_card_token TEXT NULL',
        'ALTER TABLE subscriptions MODIFY asaas_credit_card_token TEXT NULL',
        'ALTER TABLE orders MODIFY card_last4 VARCHAR(255) NULL',
        'ALTER TABLE customers MODIFY name TEXT NOT NULL',
        'ALTER TABLE customers MODIFY cpf_cnpj TEXT NOT NULL',
        'ALTER TABLE customers MODIFY phone TEXT NOT NULL',
        'ALTER TABLE customers MODIFY mobile_phone TEXT NOT NULL',
        'ALTER TABLE customers MODIFY postal_code TEXT NOT NULL',
        'ALTER TABLE customers MODIFY address TEXT NOT NULL',
        'ALTER TABLE customers MODIFY address_number TEXT NOT NULL',
        'ALTER TABLE customers MODIFY complement TEXT NOT NULL',
        'ALTER TABLE customers MODIFY province VARCHAR(255) NOT NULL',
        'ALTER TABLE customers MODIFY city TEXT NOT NULL',
    ] as $sql) {
        try {
            $pdo->exec($sql);
        } catch (Throwable $e) {
            // already widened, or the index was already removed
        }
    }
    try {
        $pdo->exec('CREATE UNIQUE INDEX idx_customers_cpf_hash ON customers (cpf_hash)');
    } catch (Throwable $e) {
        // already exists
    }
}

function geekhost_seal_stored_secrets(PDO $pdo): void
{
    require_once __DIR__ . '/crypto.php';
    $jobs = [
        ['users', ['card_last4', 'asaas_credit_card_token']],
        ['subscriptions', ['asaas_credit_card_token']],
        ['orders', ['card_last4']],
    ];
    foreach ($jobs as [$table, $fields]) {
        $cols = geekhost_table_columns($pdo, $table);
        $present = array_values(array_filter($fields, static fn (string $field): bool => in_array($field, $cols, true)));
        if ($present === [] || !in_array('id', $cols, true)) {
            continue;
        }
        $where = implode(' OR ', array_map(
            static fn (string $field): string => "({$field} IS NOT NULL AND {$field} <> '' AND {$field} NOT LIKE 'gh1:%')",
            $present
        ));
        $rows = $pdo->query('SELECT id, ' . implode(', ', $present) . " FROM {$table} WHERE {$where} LIMIT 100")->fetchAll();
        $sets = implode(', ', array_map(static fn (string $field): string => "{$field} = :{$field}", $present));
        $update = $pdo->prepare("UPDATE {$table} SET {$sets} WHERE id = :id");
        foreach ($rows as $row) {
            $params = [':id' => $row['id']];
            foreach ($present as $field) {
                $value = $row[$field] ?? null;
                $params[':' . $field] = is_string($value) && $value !== '' && !geekhost_is_sealed($value)
                    ? geekhost_seal($value)
                    : $value;
            }
            $update->execute($params);
        }
    }

    $customerCols = geekhost_table_columns($pdo, 'customers');
    $pii = array_values(array_filter(
        ['name', 'cpf_cnpj', 'phone', 'mobile_phone', 'postal_code', 'address', 'address_number', 'complement', 'province', 'city'],
        static fn (string $field): bool => in_array($field, $customerCols, true)
    ));
    if ($pii === [] || !in_array('cpf_hash', $customerCols, true)) {
        return;
    }
    $rows = $pdo->query(
        'SELECT id, ' . implode(', ', $pii) . " FROM customers WHERE cpf_hash IS NULL OR name NOT LIKE 'gh1:%' LIMIT 100"
    )->fetchAll();
    $sets = implode(', ', array_map(static fn (string $field): string => "{$field} = :{$field}", $pii));
    $update = $pdo->prepare("UPDATE customers SET {$sets}, cpf_hash = :hash WHERE id = :id");
    foreach ($rows as $row) {
        $params = [':id' => $row['id']];
        foreach ($pii as $field) {
            $value = (string) ($row[$field] ?? '');
            $params[':' . $field] = $value === '' || geekhost_is_sealed($value) ? $value : (geekhost_seal($value) ?? $value);
        }
        $digits = preg_replace('/\D+/', '', geekhost_is_sealed((string) ($row['cpf_cnpj'] ?? ''))
            ? (string) geekhost_open((string) $row['cpf_cnpj'])
            : (string) ($row['cpf_cnpj'] ?? '')) ?? '';
        $params[':hash'] = $digits !== '' ? hash('sha256', $digits) : null;
        try {
            $update->execute($params);
        } catch (Throwable $e) {
            // duplicate cpf hash
        }
    }
}

function geekhost_seed_local_demo(PDO $pdo): void
{
    require_once __DIR__ . '/crypto.php';
    $now = gmdate('c');
    $hash = '$2y$10$WP5Dgi4oKibqljS.IVP2i.B7fWro6m.FjBeQlkK2q4/W9M5pyXvFW';
    $flag = $pdo->prepare('SELECT id FROM app_settings WHERE setting_key = :k LIMIT 1');
    $flag->execute([':k' => 'local.demo_passwords']);
    if (!$flag->fetchColumn()) {
        $set = $pdo->prepare('UPDATE users SET password_hash = :h, password_is_set = 1, email_verified = 1, updated_at = :u WHERE email = :e');
        foreach (['fernandabtech@gmail.com', 'bonfimfernanda12@gmail.com'] as $email) {
            $set->execute([':h' => $hash, ':u' => $now, ':e' => $email]);
        }
        $pdo->prepare('INSERT INTO app_settings (setting_key, value_json, updated_at) VALUES (:k, :v, :u)')
            ->execute([':k' => 'local.demo_passwords', ':v' => '{"applied":true}', ':u' => $now]);
    }

    $find = $pdo->prepare('SELECT id FROM users WHERE email = :e LIMIT 1');
    $insertUser = $pdo->prepare(
        'INSERT INTO users (public_id, customer_id, email, password_hash, password_is_set, email_verified, name_enc, hosting_status, created_at, updated_at)
         VALUES (:p, :cid, :e, :h, 1, 1, :n, :s, :c, :u)'
    );

    $find->execute([':e' => 'fernandabtech@gmail.com']);
    $adminId = (int) ($find->fetchColumn() ?: 0);
    if ($adminId === 0) {
        $insertUser->execute([
            ':p' => bin2hex(random_bytes(16)),
            ':cid' => null,
            ':e' => 'fernandabtech@gmail.com',
            ':h' => $hash,
            ':n' => geekhost_encrypt('Fernanda') ?? '',
            ':s' => 'pending',
            ':c' => $now,
            ':u' => $now,
        ]);
        $adminId = (int) $pdo->lastInsertId();
    }
    $adminRow = $pdo->prepare('SELECT id FROM admin_users WHERE user_id = :u LIMIT 1');
    $adminRow->execute([':u' => $adminId]);
    if (!$adminRow->fetchColumn()) {
        $pdo->prepare(
            'INSERT INTO admin_users (user_id, role, status, created_at, updated_at) VALUES (:u, :r, :s, :c, :up)'
        )->execute([
            ':u' => $adminId,
            ':r' => 'super_admin',
            ':s' => 'active',
            ':c' => $now,
            ':up' => $now,
        ]);
    }

    $find->execute([':e' => 'bonfimfernanda12@gmail.com']);
    $clientId = (int) ($find->fetchColumn() ?: 0);
    if ($clientId > 0) {
        $has = $pdo->prepare('SELECT id FROM services WHERE user_id = :u LIMIT 1');
        $has->execute([':u' => $clientId]);
        if ($has->fetchColumn()) {
            return;
        }
    }

    $cpf = '39053344705';
    $customer = $pdo->prepare(
        'INSERT INTO customers (email, name, cpf_cnpj, cpf_hash, phone, mobile_phone, postal_code, address, address_number, complement, province, city, created_at, updated_at)
         VALUES (:e,:n,:c,:h,:p,:m,:z,:a,:num,:comp,:uf,:city,:c1,:u)'
    );
    $customer->execute([
        ':e' => 'bonfimfernanda12@gmail.com',
        ':n' => geekhost_seal('Fernanda Bonfim'),
        ':c' => geekhost_seal($cpf),
        ':h' => hash('sha256', $cpf),
        ':p' => geekhost_seal('16997399174'),
        ':m' => geekhost_seal('16997399174'),
        ':z' => geekhost_seal('05424150'),
        ':a' => geekhost_seal('Rua Pais Leme'),
        ':num' => geekhost_seal('215'),
        ':comp' => geekhost_seal('Conj 1713'),
        ':uf' => geekhost_seal('SP'),
        ':city' => geekhost_seal('Sao Paulo'),
        ':c1' => $now,
        ':u' => $now,
    ]);
    $customerId = (int) $pdo->lastInsertId();

    if ($clientId === 0) {
        $insertUser->execute([
            ':p' => bin2hex(random_bytes(16)),
            ':cid' => $customerId,
            ':e' => 'bonfimfernanda12@gmail.com',
            ':h' => $hash,
            ':n' => geekhost_encrypt('Fernanda Bonfim') ?? '',
            ':s' => 'active',
            ':c' => $now,
            ':u' => $now,
        ]);
        $clientId = (int) $pdo->lastInsertId();
    } else {
        $pdo->prepare('UPDATE users SET customer_id = :c, hosting_status = :s, updated_at = :u WHERE id = :id')
            ->execute([':c' => $customerId, ':s' => 'active', ':u' => $now, ':id' => $clientId]);
    }

    $order = $pdo->prepare(
        'INSERT INTO orders (public_id, customer_id, user_id, plan_code, plan_name, billing_period, domain, amount_cents, renew_amount_cents, billing_type, status, idempotency_key, provisioning_status, paid_at, created_at, updated_at)
         VALUES (:p,:c,:u,:plan,:name,:period,:d,:a,:r,:b,:st,:k,:prov,:paid,:c1,:u1)'
    );
    $order->execute([
        ':p' => bin2hex(random_bytes(16)),
        ':c' => $customerId,
        ':u' => $clientId,
        ':plan' => 'empresa',
        ':name' => 'Plano Empresa',
        ':period' => 'monthly',
        ':d' => 'bonfimteste.com.br',
        ':a' => 2990,
        ':r' => 4590,
        ':b' => 'PIX',
        ':st' => 'paid_pending_provisioning',
        ':k' => 'seed-bonfim-hosting',
        ':prov' => 'done',
        ':paid' => $now,
        ':c1' => $now,
        ':u1' => $now,
    ]);
    $orderId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        'INSERT INTO services (public_id, user_id, order_id, plan_code, domain, status, created_at, updated_at)
         VALUES (:p,:u,:o,:plan,:d,:s,:c,:up)'
    )->execute([
        ':p' => bin2hex(random_bytes(16)),
        ':u' => $clientId,
        ':o' => $orderId,
        ':plan' => 'empresa',
        ':d' => 'bonfimteste.com.br',
        ':s' => 'active',
        ':c' => $now,
        ':up' => $now,
    ]);
    $serviceId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        'INSERT INTO hosting_accounts (public_id, user_id, order_id, service_id, plan_code, da_username, da_password_enc, da_package, primary_domain, status, provisioned_at, created_at, updated_at)
         VALUES (:p,:u,:o,:s,:plan,:user,:pass,:pkg,:d,:st,:prov,:c,:up)'
    )->execute([
        ':p' => bin2hex(random_bytes(16)),
        ':u' => $clientId,
        ':o' => $orderId,
        ':s' => $serviceId,
        ':plan' => 'empresa',
        ':user' => 'bfteste',
        ':pass' => geekhost_seal(bin2hex(random_bytes(8))),
        ':pkg' => 'Plano-Empresa-GeekHost',
        ':d' => 'bonfimteste.com.br',
        ':st' => 'active',
        ':prov' => $now,
        ':c' => $now,
        ':up' => $now,
    ]);
    $hostingId = (int) $pdo->lastInsertId();

    $pdo->prepare(
        'INSERT INTO user_domains (public_id, user_id, domain, plan_code, ns_ok, status, ssl_status, hosting_account_id, created_at, updated_at)
         VALUES (:p,:u,:d,:plan,1,:st,:ssl,:h,:c,:up)'
    )->execute([
        ':p' => bin2hex(random_bytes(16)),
        ':u' => $clientId,
        ':d' => 'bonfimteste.com.br',
        ':plan' => 'empresa',
        ':st' => 'active',
        ':ssl' => 'active',
        ':h' => $hostingId,
        ':c' => $now,
        ':up' => $now,
    ]);
}
