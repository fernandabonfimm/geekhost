<?php

declare(strict_types=1);

/**
 * One-shot: create/repair admin@geekhost.com.br when install seed failed.
 * Open: /fix-admin.php?key=INSTALL_DB_KEY
 * Delete after use.
 */

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/includes/env.php';
geekhost_load_env();

$expected = (string) env('INSTALL_DB_KEY', '');
if ($expected === '') {
    $expected = 'geekhost-install-' . substr(hash('sha256', (string) env('DB_PASS', 'x')), 0, 12);
}
$key = (string) ($_GET['key'] ?? '');

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><title>Fix admin</title>';
echo '<style>body{font-family:system-ui,sans-serif;max-width:720px;margin:40px auto;padding:0 16px;line-height:1.45}';
echo '.ok{color:#067647}.err{color:#b42318}.box{border:1px solid #e4e4e7;border-radius:12px;padding:16px;margin:16px 0}</style></head><body>';
echo '<h1>GeekHost — reparar admin</h1>';

if (!hash_equals($expected, $key)) {
    echo '<p class="err">Chave inválida. Use <code>?key=...</code> (INSTALL_DB_KEY no .env).</p></body></html>';
    exit;
}

$host = (string) env('DB_HOST', '127.0.0.1');
$port = (string) env('DB_PORT', '3306');
$name = (string) env('DB_NAME', '');
$user = (string) env('DB_USER', '');
$pass = (string) env('DB_PASS', '');

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $host, $port, $name),
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (Throwable $e) {
    echo '<p class="err">PDO: ' . h($e->getMessage()) . '</p></body></html>';
    exit;
}

$email = 'admin@geekhost.com.br';
$hash = '$2y$12$cRaQXgSpf.FLKQvQwgNJweXfKXaTFleFGuc4RLJQjofZdj4zRN55e'; // GeekHost@Admin1
$now = gmdate('c');
$publicId = 'a2222222-2222-4222-8222-222222222222';

try {
    $stmt = $pdo->prepare('SELECT id, public_id FROM users WHERE email = :e LIMIT 1');
    $stmt->execute([':e' => $email]);
    $row = $stmt->fetch();

    if ($row) {
        $pdo->prepare(
            'UPDATE users SET password_hash = :h, password_is_set = 1, hosting_status = :hs, updated_at = :u WHERE id = :id'
        )->execute([':h' => $hash, ':hs' => 'active', ':u' => $now, ':id' => $row['id']]);
        $userId = (int) $row['id'];
        echo '<p class="ok">User existente atualizado (id=' . $userId . ').</p>';
    } else {
        // Avoid colliding with a stolen fixed UUID from a previous failed seed
        $check = $pdo->prepare('SELECT id FROM users WHERE public_id = :p LIMIT 1');
        $check->execute([':p' => $publicId]);
        if ($check->fetch()) {
            $publicId = sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                random_int(0, 0xffff),
                random_int(0, 0xffff),
                random_int(0, 0xffff),
                random_int(0, 0x0fff) | 0x4000,
                random_int(0, 0x3fff) | 0x8000,
                random_int(0, 0xffff),
                random_int(0, 0xffff),
                random_int(0, 0xffff)
            );
        }
        $pdo->prepare(
            'INSERT INTO users (
                public_id, customer_id, email, password_hash, password_is_set,
                name_enc, phone_enc, mobile_enc, cpf_cnpj_enc, postal_code_enc,
                address_enc, address_number_enc, complement_enc, province_enc, city_enc,
                hosting_status, created_at, updated_at
            ) VALUES (
                :pid, NULL, :email, :ph, 1,
                :name, \'\', \'\', \'\', \'\',
                \'\', \'\', \'\', \'\', \'\',
                :hs, :c, :u
            )'
        )->execute([
            ':pid' => $publicId,
            ':email' => $email,
            ':ph' => $hash,
            ':name' => 'Geek Admin',
            ':hs' => 'active',
            ':c' => $now,
            ':u' => $now,
        ]);
        $userId = (int) $pdo->lastInsertId();
        echo '<p class="ok">User criado (id=' . $userId . ', public_id=' . h($publicId) . ').</p>';
    }

    $adm = $pdo->prepare('SELECT id FROM admin_users WHERE user_id = :u LIMIT 1');
    $adm->execute([':u' => $userId]);
    if ($adm->fetch()) {
        $pdo->prepare("UPDATE admin_users SET role = 'super_admin', status = 'active', updated_at = :u WHERE user_id = :id")
            ->execute([':u' => $now, ':id' => $userId]);
        echo '<p class="ok">admin_users já existia — status active / super_admin.</p>';
    } else {
        $pdo->prepare(
            'INSERT INTO admin_users (user_id, role, status, created_at, updated_at) VALUES (:u, :r, :s, :c, :up)'
        )->execute([
            ':u' => $userId,
            ':r' => 'super_admin',
            ':s' => 'active',
            ':c' => $now,
            ':up' => $now,
        ]);
        echo '<p class="ok">admin_users criado.</p>';
    }

    echo '<div class="box ok"><strong>Pronto.</strong><br>Login: <a href="/admin/login.php">/admin/login.php</a>';
    echo '<br>Email: <code>' . h($email) . '</code><br>Senha: <code>GeekHost@Admin1</code></div>';
    echo '<p><strong>Apague</strong> <code>fix-admin.php</code> e <code>install-db.php</code> agora.</p>';
} catch (Throwable $e) {
    echo '<p class="err">' . h($e->getMessage()) . '</p>';
}

echo '</body></html>';
