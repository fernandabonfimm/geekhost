<?php

declare(strict_types=1);

$adminTitle = 'Vendas';
$adminSection = 'vendas';
$adminLead = 'Pedidos reais do checkout Asaas (espelho local).';
require __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/plans.php';

$pdo = geekhost_db();
$q = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$plan = trim((string) ($_GET['plan'] ?? ''));
$id = trim((string) ($_GET['id'] ?? ''));

$fmt = static fn (int $c) => 'R$ ' . number_format($c / 100, 2, ',', '.');

if ($id !== '') {
    $stmt = $pdo->prepare(
        'SELECT o.*, u.email AS user_email
         FROM orders o
         LEFT JOIN users u ON u.id = o.user_id
         WHERE o.public_id = :p LIMIT 1'
    );
    $stmt->execute([':p' => $id]);
    $order = $stmt->fetch() ?: null;
    ?>
    <p><a class="gh-btn" href="<?= e(url('/admin/vendas')) ?>">← Voltar</a></p>
    <?php if (!$order): ?>
        <div class="gh-alert gh-alert-danger">Venda não encontrada.</div>
    <?php else: ?>
        <article class="admin-card">
            <h2>Pedido <?= e((string) $order['public_id']) ?></h2>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <tbody>
                    <tr><td>Cliente</td><td><?= e((string) ($order['user_email'] ?? '—')) ?></td></tr>
                    <tr><td>Plano</td><td><?= e((string) $order['plan_name']) ?> (<?= e((string) $order['plan_code']) ?>)</td></tr>
                    <tr><td>Pacote DA</td><td><?= e(geekhost_plan_da_package((string) $order['plan_code'])) ?></td></tr>
                    <tr><td>Valor</td><td><?= e($fmt((int) $order['amount_cents'])) ?></td></tr>
                    <tr><td>Ciclo</td><td><?= e((string) $order['billing_period']) ?></td></tr>
                    <tr><td>Método</td><td><?= e((string) $order['billing_type']) ?></td></tr>
                    <tr><td>Status</td><td><?= e((string) $order['status']) ?></td></tr>
                    <tr><td>Provisionamento</td><td><?= e((string) ($order['provisioning_status'] ?? 'none')) ?></td></tr>
                    <tr><td>Asaas payment</td><td><?= e((string) ($order['asaas_payment_id'] ?? '—')) ?></td></tr>
                    <tr><td>Link cobrança</td><td>
                        <?php if (!empty($order['asaas_invoice_url'])): ?>
                            <a href="<?= e((string) $order['asaas_invoice_url']) ?>" target="_blank" rel="noopener">Abrir</a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td></tr>
                    <tr><td>Criado</td><td><?= e((string) $order['created_at']) ?></td></tr>
                    <tr><td>Pago em</td><td><?= e((string) ($order['paid_at'] ?? '—')) ?></td></tr>
                    </tbody>
                </table>
            </div>
        </article>
    <?php endif;
    require __DIR__ . '/_footer.php';
    exit;
}

$sql = 'SELECT o.public_id, o.plan_code, o.plan_name, o.amount_cents, o.billing_period, o.billing_type,
               o.status, o.provisioning_status, o.created_at, o.asaas_invoice_url, u.email AS user_email
        FROM orders o
        LEFT JOIN users u ON u.id = o.user_id
        WHERE 1=1';
$params = [];
if ($q !== '') {
    $sql .= ' AND (u.email LIKE :q OR o.public_id LIKE :q2 OR o.plan_name LIKE :q3)';
    $params[':q'] = '%' . $q . '%';
    $params[':q2'] = '%' . $q . '%';
    $params[':q3'] = '%' . $q . '%';
}
if ($status !== '') {
    $sql .= ' AND o.status = :st';
    $params[':st'] = $status;
}
if ($plan !== '') {
    $sql .= ' AND o.plan_code = :plan';
    $params[':plan'] = $plan;
}
$sql .= ' ORDER BY o.id DESC LIMIT 200';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll() ?: [];
?>
<form class="admin-filters" method="get">
    <input type="search" name="q" placeholder="E-mail, ID, plano…" value="<?= e($q) ?>">
    <select name="status">
        <option value="">Todos status</option>
        <?php foreach (['awaiting_payment','paid_pending_provisioning','overdue','canceled'] as $st): ?>
            <option value="<?= e($st) ?>" <?= $status === $st ? 'selected' : '' ?>><?= e($st) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="plan">
        <option value="">Todos planos</option>
        <?php foreach (['light','empresa','premium'] as $p): ?>
            <option value="<?= e($p) ?>" <?= $plan === $p ? 'selected' : '' ?>><?= e($p) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="gh-btn gh-btn-primary" type="submit">Filtrar</button>
</form>
<article class="admin-card">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
            <tr>
                <th>ID</th><th>Cliente</th><th>Plano</th><th>Valor</th><th>Ciclo</th>
                <th>Método</th><th>Status</th><th>Prov.</th><th>Data</th><th></th>
            </tr>
            </thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="10" class="admin-empty">Nenhuma venda encontrada.</td></tr>
            <?php else: foreach ($rows as $row): ?>
                <tr>
                    <td><code><?= e(substr((string) $row['public_id'], 0, 8)) ?>…</code></td>
                    <td><?= e((string) ($row['user_email'] ?? '—')) ?></td>
                    <td><?= e((string) $row['plan_name']) ?></td>
                    <td><?= e($fmt((int) $row['amount_cents'])) ?></td>
                    <td><?= e((string) $row['billing_period']) ?></td>
                    <td><?= e((string) $row['billing_type']) ?></td>
                    <td><span class="admin-badge"><?= e((string) $row['status']) ?></span></td>
                    <td><?= e((string) ($row['provisioning_status'] ?? 'none')) ?></td>
                    <td><?= e((string) $row['created_at']) ?></td>
                    <td><a class="gh-btn" href="<?= e(url('/admin/vendas?id=' . rawurlencode((string) $row['public_id']))) ?>">Detalhes</a></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</article>
<?php require __DIR__ . '/_footer.php'; ?>
