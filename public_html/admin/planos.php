<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/csrf.php';
require_once dirname(__DIR__) . '/includes/AdminGate.php';
require_once dirname(__DIR__) . '/includes/plans.php';

$gate = new AdminGate();
try {
    $ctx = $gate->requireAdmin();
} catch (Throwable $e) {
    $ctx = null;
}
if ($ctx && in_array('planos', $gate->pagesFor($ctx['admin']), true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate(isset($_POST['csrf']) ? (string) $_POST['csrf'] : null)) {
        $_SESSION['admin_notice'] = 'Sessão expirada. Recarregue a página.';
    } else {
        $limits = [];
        $posted = isset($_POST['max_domains']) && is_array($_POST['max_domains']) ? $_POST['max_domains'] : [];
        foreach (['light', 'empresa', 'premium'] as $code) {
            $value = (int) ($posted[$code] ?? 1);
            $limits[$code] = max(1, min(100, $value));
        }
        $pdo = geekhost_db();
        $now = gmdate('c');
        $existing = $pdo->prepare('SELECT id FROM app_settings WHERE setting_key = :k LIMIT 1');
        $existing->execute([':k' => 'plans.domain_limits']);
        if ($existing->fetch()) {
            $pdo->prepare('UPDATE app_settings SET value_json = :v, updated_at = :u, updated_by = :by WHERE setting_key = :k')
                ->execute([
                    ':v' => json_encode($limits),
                    ':u' => $now,
                    ':by' => (int) $ctx['user']['id'],
                    ':k' => 'plans.domain_limits',
                ]);
        } else {
            $pdo->prepare('INSERT INTO app_settings (setting_key, value_json, updated_at, updated_by) VALUES (:k, :v, :u, :by)')
                ->execute([
                    ':k' => 'plans.domain_limits',
                    ':v' => json_encode($limits),
                    ':u' => $now,
                    ':by' => (int) $ctx['user']['id'],
                ]);
        }
        $_SESSION['admin_notice'] = 'Limite de domínios por plano atualizado.';
    }
    header('Location: ' . url('/admin/planos'));
    exit;
}

$adminTitle = 'Planos e pacotes';
$adminSection = 'planos';
$adminLead = 'Planos de venda GeekHost vinculados aos pacotes DirectAdmin da revenda geek6923.';
require __DIR__ . '/_layout.php';
?>
<article class="admin-card">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
            <tr>
                <th>Checkout</th><th>Nome</th><th>Pacote DirectAdmin</th>
                <th>Domínios</th><th>Sites</th><th>Mensal</th><th>Anual</th><th>Status</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach (['light', 'empresa', 'premium'] as $code):
                $plan = geekhost_plan($code);
                if (!$plan) {
                    continue;
                }
                $mensal = $plan['periods']['mensal']['amount'] ?? null;
                $anual = $plan['periods']['anual']['amount'] ?? null;
                ?>
                <tr>
                    <td><code><?= e($code) ?></code></td>
                    <td><?= e((string) $plan['name']) ?></td>
                    <td><strong><?= e(geekhost_plan_da_package($code)) ?></strong></td>
                    <td><?= (int) geekhost_plan_domain_limit($code) ?></td>
                    <td><?= (int) ($plan['max_sites'] ?? 0) ?></td>
                    <td><?= $mensal !== null ? e(geekhost_format_brl((float) $mensal)) : '—' ?></td>
                    <td><?= $anual !== null ? e(geekhost_format_brl((float) $anual)) : '—' ?></td>
                    <td><?= !empty($plan['checkout_enabled']) ? 'À venda' : 'Off' ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</article>
<article class="admin-card">
    <h2>Domínios inclusos em cada plano</h2>
    <p>O cliente não compra domínio aqui. Este número é o máximo que ele pode cadastrar no painel depois de contratar.</p>
    <form class="admin-form" method="post">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <div class="admin-form-grid">
            <?php foreach (['light', 'empresa', 'premium'] as $code):
                $plan = geekhost_plan($code);
                if (!$plan) continue; ?>
                <label><?= e((string) $plan['name']) ?>
                    <input type="number" name="max_domains[<?= e($code) ?>]" min="1" max="100" required value="<?= (int) geekhost_plan_domain_limit($code) ?>">
                </label>
            <?php endforeach; ?>
        </div>
        <button class="gh-btn gh-btn-primary" type="submit">Salvar limites</button>
    </form>
</article>
<?php require __DIR__ . '/_footer.php'; ?>
