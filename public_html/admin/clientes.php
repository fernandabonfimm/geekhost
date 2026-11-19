<?php

declare(strict_types=1);

$adminTitle = 'Clientes';
$adminSection = 'clientes';
$adminLead = 'Usuários do painel GeekHost (CRM operacional).';
require __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/includes/db.php';

$pdo = geekhost_db();
$q = trim((string) ($_GET['q'] ?? ''));
$filter = trim((string) ($_GET['filter'] ?? ''));

$sql = 'SELECT u.public_id, u.email, u.hosting_status, u.created_at, u.suspended_at,
               (SELECT COUNT(*) FROM hosting_accounts h WHERE h.user_id = u.id) AS hosting_count,
               (SELECT COALESCE(SUM(amount_cents),0) FROM orders o WHERE o.user_id = u.id AND o.status = \'paid_pending_provisioning\') AS spent_cents
        FROM users u WHERE 1=1';
$params = [];
if ($q !== '') {
    $sql .= ' AND u.email LIKE :q';
    $params[':q'] = '%' . $q . '%';
}
if ($filter === 'active') {
    $sql .= " AND u.hosting_status = 'active'";
} elseif ($filter === 'suspended') {
    $sql .= " AND u.hosting_status = 'suspended'";
} elseif ($filter === 'with_hosting') {
    $sql .= ' AND EXISTS (SELECT 1 FROM hosting_accounts h WHERE h.user_id = u.id AND h.status = \'active\')';
} elseif ($filter === 'without_hosting') {
    $sql .= ' AND NOT EXISTS (SELECT 1 FROM hosting_accounts h WHERE h.user_id = u.id)';
}
$sql .= ' ORDER BY u.id DESC LIMIT 200';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll() ?: [];
$fmt = static fn (int $c) => 'R$ ' . number_format($c / 100, 2, ',', '.');
?>
<form class="admin-filters" method="get">
    <input type="search" name="q" placeholder="E-mail…" value="<?= e($q) ?>">
    <select name="filter">
        <option value="">Todos</option>
        <option value="active" <?= $filter === 'active' ? 'selected' : '' ?>>Ativos</option>
        <option value="suspended" <?= $filter === 'suspended' ? 'selected' : '' ?>>Suspensos</option>
        <option value="with_hosting" <?= $filter === 'with_hosting' ? 'selected' : '' ?>>Com hospedagem</option>
        <option value="without_hosting" <?= $filter === 'without_hosting' ? 'selected' : '' ?>>Sem hospedagem</option>
    </select>
    <button class="gh-btn gh-btn-primary" type="submit">Filtrar</button>
</form>
<article class="admin-card">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
            <tr><th>E-mail</th><th>Status</th><th>Hospedagens</th><th>Total pago</th><th>Cadastro</th></tr>
            </thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="5" class="admin-empty">Nenhum cliente.</td></tr>
            <?php else: foreach ($rows as $row): ?>
                <tr>
                    <td><?= e((string) $row['email']) ?></td>
                    <td><span class="admin-badge"><?= e((string) $row['hosting_status']) ?></span></td>
                    <td><?= (int) $row['hosting_count'] ?></td>
                    <td><?= e($fmt((int) $row['spent_cents'])) ?></td>
                    <td><?= e((string) $row['created_at']) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</article>
<?php require __DIR__ . '/_footer.php'; ?>
