<?php

declare(strict_types=1);

/**
 * Mocked DirectAdmin provisioning tests (no real DA calls).
 * Run: php tests/directadmin_provisioning_test.php
 */

$root = dirname(__DIR__);
putenv('DB_DRIVER=sqlite');
putenv('DB_PATH=' . $root . '/storage/test-da-provisioning.sqlite');
putenv('DIRECTADMIN_URL=https://da.example.test:2222');
putenv('DIRECTADMIN_USERNAME=reseller');
putenv('DIRECTADMIN_LOGIN_KEY=test-login-key');
putenv('DIRECTADMIN_PROVISIONING_ENABLED=true');
putenv('DIRECTADMIN_DRY_RUN=0');
putenv('APP_DATA_KEY=test-app-data-key-32chars-minimum!!');
$_ENV['DB_DRIVER'] = 'sqlite';
$_ENV['DB_PATH'] = $root . '/storage/test-da-provisioning.sqlite';
$_ENV['DIRECTADMIN_URL'] = 'https://da.example.test:2222';
$_ENV['DIRECTADMIN_USERNAME'] = 'reseller';
$_ENV['DIRECTADMIN_LOGIN_KEY'] = 'test-login-key';
$_ENV['DIRECTADMIN_PROVISIONING_ENABLED'] = 'true';
$_ENV['DIRECTADMIN_DRY_RUN'] = '0';
$_ENV['APP_DATA_KEY'] = 'test-app-data-key-32chars-minimum!!';

@unlink($root . '/storage/test-da-provisioning.sqlite');

require_once $root . '/public_html/includes/db.php';
require_once $root . '/public_html/includes/DirectAdminClient.php';
require_once $root . '/public_html/includes/ProvisioningService.php';

$pdo = geekhost_db();
$now = gmdate('c');

$pdo->exec("INSERT INTO customers (email, name, cpf_cnpj, created_at, updated_at) VALUES ('cliente@test.local','Cliente','00000000000','$now','$now')");
$customerId = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO users (public_id, customer_id, email, password_hash, name_enc, hosting_status, created_at, updated_at) VALUES ('u1',$customerId,'cliente@test.local','x','Cliente Teste','pending','$now','$now')");
$userId = (int) $pdo->lastInsertId();
$pdo->exec("INSERT INTO orders (public_id, customer_id, user_id, plan_code, plan_name, billing_period, domain, amount_cents, currency, billing_type, status, idempotency_key, provisioning_status, created_at, updated_at, paid_at) VALUES ('o1',$customerId,$userId,'light','Light','mensal','exemplo.com.br',1990,'BRL','PIX','paid_pending_provisioning','idem-1','none','$now','$now','$now')");
$orderId = (int) $pdo->lastInsertId();

$calls = [];
$transport = static function (string $method, string $url, array $opts) use (&$calls): array {
    $calls[] = ['method' => $method, 'url' => $url];
    if (str_contains($url, '/api/users/') && str_contains($url, '/config')) {
        return ['status' => 404, 'body' => ['type' => 'NOT_FOUND'], 'raw' => '{"type":"NOT_FOUND"}', 'headers' => ''];
    }
    if (str_contains($url, 'CMD_API_ACCOUNT_USER')) {
        return ['status' => 200, 'body' => null, 'raw' => 'error=0&text=User created', 'headers' => ''];
    }
    if (str_contains($url, '/api/users/list')) {
        return ['status' => 200, 'body' => ['users' => []], 'raw' => '{"users":[]}', 'headers' => ''];
    }
    if (str_contains($url, 'provision-certs')) {
        return ['status' => 200, 'body' => ['ok' => true], 'raw' => '{}', 'headers' => ''];
    }
    if (str_contains($url, '/certs')) {
        return ['status' => 200, 'body' => ['certs' => [['id' => '1']], 'certIds' => ['1']], 'raw' => '{}', 'headers' => ''];
    }
    if (str_contains($url, 'CMD_API_SELECT_USERS')) {
        return ['status' => 200, 'body' => null, 'raw' => 'error=0&text=Suspended', 'headers' => ''];
    }
    return ['status' => 200, 'body' => [], 'raw' => '{}', 'headers' => ''];
};

$client = new DirectAdminClient($transport);
$svc = new ProvisioningService($client);

$order = $pdo->query('SELECT * FROM orders WHERE id = ' . $orderId)->fetch();
$svc->queueFromOrder($order ?: []);

$account = $pdo->query('SELECT * FROM hosting_accounts WHERE order_id = ' . $orderId)->fetch();
assert_true($account !== false, 'hosting_account created');
assert_true(($account['status'] ?? '') === 'pending', 'account pending before process');

$result = $svc->processPendingJobs(10);
assert_true($result['processed'] >= 1, 'provision job processed');

$account = $pdo->query('SELECT * FROM hosting_accounts WHERE order_id = ' . $orderId)->fetch();
assert_true(($account['status'] ?? '') === 'active', 'account active after provision');

$user = $pdo->query('SELECT hosting_status FROM users WHERE id = ' . $userId)->fetch();
assert_true(($user['hosting_status'] ?? '') === 'active', 'user hosting_status active');

$orderRow = $pdo->query('SELECT provisioning_status FROM orders WHERE id = ' . $orderId)->fetch();
assert_true(($orderRow['provisioning_status'] ?? '') === 'done', 'order provisioning done');

// Idempotent second queue should not duplicate account
$svc->queueFromOrder($order ?: []);
$count = (int) $pdo->query('SELECT COUNT(*) FROM hosting_accounts WHERE order_id = ' . $orderId)->fetchColumn();
assert_true($count === 1, 'idempotent account');

// Feature flag off blocks mutations path for new suspend job after toggling
$_ENV['DIRECTADMIN_PROVISIONING_ENABLED'] = 'false';
putenv('DIRECTADMIN_PROVISIONING_ENABLED=false');

$createCallsBefore = count($calls);
$flagClient = new DirectAdminClient($transport);
$flagSvc = new ProvisioningService($flagClient);
// new order without domain → blocked
$pdo->exec("INSERT INTO orders (public_id, customer_id, user_id, plan_code, plan_name, billing_period, domain, amount_cents, currency, billing_type, status, idempotency_key, provisioning_status, created_at, updated_at, paid_at) VALUES ('o2',$customerId,$userId,'light','Light','mensal','',1990,'BRL','PIX','paid_pending_provisioning','idem-2','none','$now','$now','$now')");
$order2 = $pdo->query("SELECT * FROM orders WHERE public_id = 'o2'")->fetch();
$flagSvc->queueFromOrder($order2 ?: []);
$blocked = $pdo->query("SELECT status FROM hosting_accounts WHERE order_id = (SELECT id FROM orders WHERE public_id='o2')")->fetchColumn();
assert_true($blocked === 'blocked_no_domain', 'blocked without domain / provisional DNS');

echo "OK — " . count($calls) . " mocked DA HTTP calls, createCallsDelta=" . (count($calls) - $createCallsBefore) . "\n";
exit(0);

function assert_true(bool $cond, string $msg): void
{
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
    echo "PASS: {$msg}\n";
}
