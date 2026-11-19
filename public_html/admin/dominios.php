<?php

declare(strict_types=1);

$adminTitle = 'Domínios';
$adminSection = 'dominios';
$adminLead = 'Domínios cadastrados por clientes (DNS / SSL).';
require __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/includes/db.php';

$pdo = geekhost_db();
$rows = $pdo->query(
    'SELECT d.domain, d.status, d.ns_ok, d.ssl_status, d.ns_checked_at, d.created_at, u.email
     FROM user_domains d
     LEFT JOIN users u ON u.id = d.user_id
     ORDER BY d.id DESC LIMIT 200'
)->fetchAll() ?: [];
?>
<article class="admin-card">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
            <tr><th>Domínio</th><th>Cliente</th><th>DNS</th><th>SSL</th><th>Status</th><th>Checagem</th></tr>
            </thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="6" class="admin-empty">Nenhum domínio.</td></tr>
            <?php else: foreach ($rows as $row): ?>
                <tr>
                    <td><?= e((string) $row['domain']) ?></td>
                    <td><?= e((string) ($row['email'] ?? '—')) ?></td>
                    <td><?= ((int) $row['ns_ok']) === 1 ? 'OK' : 'Aguardando' ?></td>
                    <td><?= e((string) ($row['ssl_status'] ?? 'none')) ?></td>
                    <td><?= e((string) $row['status']) ?></td>
                    <td><?= e((string) ($row['ns_checked_at'] ?? '—')) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</article>
<?php require __DIR__ . '/_footer.php'; ?>
