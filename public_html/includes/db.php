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

    // MySQL: adapt AUTOINCREMENT / TEXT timestamps lightly
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $sql = str_replace('INTEGER PRIMARY KEY AUTOINCREMENT', 'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY', $sql);
        $sql = preg_replace('/^CREATE UNIQUE INDEX IF NOT EXISTS .*?;$/m', '', $sql) ?? $sql;
        $sql = preg_replace('/^CREATE INDEX IF NOT EXISTS .*?;$/m', '', $sql) ?? $sql;
        // Create tables then indexes separately for MySQL uniqueness
    }

    $pdo->exec($sql);

    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
        $indexes = [
            'CREATE UNIQUE INDEX idx_customers_cpf_cnpj ON customers (cpf_cnpj)',
            'CREATE INDEX idx_customers_email ON customers (email)',
            'CREATE UNIQUE INDEX idx_customers_asaas ON customers (asaas_customer_id)',
            'CREATE UNIQUE INDEX idx_orders_public_id ON orders (public_id)',
            'CREATE UNIQUE INDEX idx_orders_idempotency ON orders (idempotency_key)',
            'CREATE UNIQUE INDEX idx_orders_asaas_payment ON orders (asaas_payment_id)',
            'CREATE INDEX idx_orders_status ON orders (status)',
            'CREATE UNIQUE INDEX idx_subscriptions_public_id ON subscriptions (public_id)',
            'CREATE UNIQUE INDEX idx_subscriptions_asaas ON subscriptions (asaas_subscription_id)',
            'CREATE UNIQUE INDEX idx_webhook_events_key ON webhook_events (event_key)',
        ];
        foreach ($indexes as $indexSql) {
            try {
                $pdo->exec($indexSql);
            } catch (Throwable $e) {
                // index may already exist
            }
        }
    }
}
