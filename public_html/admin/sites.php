<?php

declare(strict_types=1);

$adminTitle = 'Sites';
$adminSection = 'sites';
$adminLead = 'Sites WordPress e blank dos clientes.';
require __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/includes/db.php';

$pdo = geekhost_db();
$rows = $pdo->query(
    'SELECT s.title, s.status, s.wordpress_status, s.site_type, s.created_at, d.domain, u.email
     FROM user_sites s
     INNER JOIN user_domains d ON d.id = s.domain_id
     LEFT JOIN users u ON u.id = s.user_id
     ORDER BY s.id DESC LIMIT 200'
)->fetchAll() ?: [];
?>
<article class="admin-card">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
            <tr><th>Site</th><th>Domínio</th><th>Cliente</th><th>Tipo</th><th>WP</th><th>Status</th></tr>
            </thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="6" class="admin-empty">Nenhum site.</td></tr>
            <?php else: foreach ($rows as $row): ?>
                <tr>
                    <td><?= e((string) $row['title']) ?></td>
                    <td><?= e((string) $row['domain']) ?></td>
                    <td><?= e((string) ($row['email'] ?? '—')) ?></td>
                    <td><?= e((string) ($row['site_type'] ?? 'blank')) ?></td>
                    <td><?= e((string) ($row['wordpress_status'] ?? '')) ?></td>
                    <td><?= e((string) $row['status']) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</article>
<?php require __DIR__ . '/_footer.php'; ?>
