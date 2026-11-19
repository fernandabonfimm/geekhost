<?php

declare(strict_types=1);

$adminTitle = 'Configurações';
$adminSection = 'configuracoes';
$adminLead = 'Empresa, Asaas e DirectAdmin — sem expor secrets.';
require __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/includes/env.php';
require_once dirname(__DIR__) . '/includes/DirectAdminClient.php';
require_once dirname(__DIR__) . '/includes/ProvisioningService.php';

geekhost_load_env();
$da = new DirectAdminClient();
$probe = $da->isConfigured() ? $da->probe() : ['ok' => false, 'message' => 'Não configurado'];
$asaasEnv = function_exists('geekhost_resolve_asaas_env') ? geekhost_resolve_asaas_env() : (string) env('ASAAS_ENV', 'sandbox');
$userMasked = (string) env('DIRECTADMIN_USERNAME', 'geek6923');
if (strlen($userMasked) > 3) {
    $userMasked = substr($userMasked, 0, 3) . str_repeat('*', max(3, strlen($userMasked) - 3));
}
?>
<div class="admin-grid-2">
    <article class="admin-card">
        <h2>Empresa</h2>
        <table class="admin-table">
            <tbody>
            <tr><td>Nome</td><td>GeekHost</td></tr>
            <tr><td>E-mail</td><td><?= e((string) env('GEEKHOST_CONTACT_EMAIL', 'contato@geekhost.com.br')) ?></td></tr>
            <tr><td>WhatsApp</td><td><?= e((string) env('GEEKHOST_WHATSAPP', '')) ?></td></tr>
            </tbody>
        </table>
    </article>
    <article class="admin-card">
        <h2>DirectAdmin (revenda)</h2>
        <table class="admin-table">
            <tbody>
            <tr><td>URL</td><td><?= e((string) env('DIRECTADMIN_URL', '')) ?></td></tr>
            <tr><td>Usuário revenda</td><td><?= e($userMasked) ?> <span class="admin-badge">geek6923</span></td></tr>
            <tr><td>Login key</td><td>●●●●●●●● (somente .env)</td></tr>
            <tr><td>Conexão</td><td><?= !empty($probe['ok']) ? 'OK' : e((string) ($probe['message'] ?? 'falha')) ?></td></tr>
            <tr><td>Provisioning</td><td><?= ProvisioningService::provisioningEnabled() ? 'enabled' : 'disabled' ?></td></tr>
            <tr><td>Dry-run</td><td><?= $da->isDryRun() ? 'on' : 'off' ?></td></tr>
            <tr><td>Pacotes</td><td><?= e(implode(', ', $probe['packages'] ?? [])) ?></td></tr>
            </tbody>
        </table>
    </article>
    <article class="admin-card">
        <h2>Asaas</h2>
        <table class="admin-table">
            <tbody>
            <tr><td>Ambiente</td><td><?= e($asaasEnv) ?></td></tr>
            <tr><td>API key</td><td>●●●●●●●● (somente .env)</td></tr>
            <tr><td>Webhook</td><td><?= e(url('/api/webhooks/asaas')) ?></td></tr>
            </tbody>
        </table>
    </article>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
