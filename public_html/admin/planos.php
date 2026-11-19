<?php

declare(strict_types=1);

$adminTitle = 'Planos e pacotes';
$adminSection = 'planos';
$adminLead = 'Planos de venda GeekHost vinculados aos pacotes DirectAdmin da revenda geek6923.';
require __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/includes/plans.php';
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
                    <td><?= (int) ($plan['max_domains'] ?? 0) ?></td>
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
<?php require __DIR__ . '/_footer.php'; ?>
