<?php

declare(strict_types=1);

$adminTitle = 'Hospedagens';
$adminSection = 'hospedagens';
$adminLead = 'Contas DirectAdmin vinculadas (revenda geek6923).';
require __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/includes/ProvisioningService.php';
require_once dirname(__DIR__) . '/includes/DirectAdminClient.php';

$svc = new ProvisioningService();
$accounts = $svc->listAccountsForAdmin(200);
$da = new DirectAdminClient();
$probe = $da->isConfigured() ? $da->probe() : ['ok' => false, 'message' => 'DA não configurado no .env'];
?>
<div class="gh-alert">
    <div>
        DirectAdmin: <?= !empty($probe['ok']) ? 'conectado' : 'indisponível' ?>
        — <?= e((string) ($probe['message'] ?? '')) ?>
        · Provisioning flag: <?= ProvisioningService::provisioningEnabled() ? 'ON' : 'OFF' ?>
        · Dry-run: <?= $da->isDryRun() ? 'ON' : 'OFF' ?>
    </div>
</div>
<article class="admin-card">
    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
            <tr>
                <th>DA user</th><th>Cliente</th><th>Plano</th><th>Pacote</th>
                <th>Domínio</th><th>Status</th><th>Erro</th><th>Atualizado</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($accounts === []): ?>
                <tr><td colspan="8" class="admin-empty">Nenhuma conta provisionada ainda.</td></tr>
            <?php else: foreach ($accounts as $a): ?>
                <tr>
                    <td><code><?= e((string) $a['daUsername']) ?></code></td>
                    <td><?= e((string) ($a['userEmail'] ?? $a['userId'])) ?></td>
                    <td><?= e((string) $a['planCode']) ?></td>
                    <td><?= e((string) $a['package']) ?></td>
                    <td><?= e((string) ($a['primaryDomain'] ?? '—')) ?></td>
                    <td><span class="admin-badge"><?= e((string) $a['status']) ?></span></td>
                    <td><?= e((string) ($a['lastError'] ?? '')) ?></td>
                    <td><?= e((string) ($a['updatedAt'] ?? '')) ?></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</article>
<?php require __DIR__ . '/_footer.php'; ?>
