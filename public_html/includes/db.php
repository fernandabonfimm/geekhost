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
        $host = env('DB_HOST', '127.0.0.1');
        $port = env('DB_PORT', '3306');
        $name = env('DB_NAME', 'geekhost');
        $user = env('DB_USER', '');
        $pass = env('DB_PASS', '');
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name);
        $pdo = new PDO($dsn, (string) $user, (string) $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
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
    return $pdo;
}

function geekhost_migrate(PDO $pdo): void
{
    $schemaFile = geekhost_project_root() . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql';
    if (!is_readable($schemaFile)) {
        throw new RuntimeException('Database schema file missing.');
    }
    $sql = file_get_contents($schemaFile);
    if ($sql === false) {
        throw new RuntimeException('Unable to read database schema.');
    }

    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $sql = str_replace('INTEGER PRIMARY KEY AUTOINCREMENT', 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY', $sql);
        // Keep FK column types aligned with MySQL BIGINT UNSIGNED PKs.
        $sql = preg_replace(
            '/\b(customer_id|user_id|order_id|subscription_id)\s+INTEGER\b/',
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
            'asaas_credit_card_token' => 'VARCHAR(120) DEFAULT NULL',
            'overdue_since' => 'TEXT DEFAULT NULL',
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
