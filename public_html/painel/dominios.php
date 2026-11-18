<?php

declare(strict_types=1);

$panelTitle = 'Domínios';
$panelSection = 'dominios';
$panelLead = 'Gerencie seus domínios, configurações de DNS e acompanhe a renovação.';
ob_start();
?>
<button type="button" class="gh-btn gh-btn-primary" data-modal-open="add-domain"><i data-lucide="plus"></i> Cadastrar domínio</button>
<?php
$panelActions = ob_get_clean();
require __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/includes/DomainSiteService.php';
require_once dirname(__DIR__) . '/includes/plans.php';

$svc = new DomainSiteService();
$planCode = $svc->resolveUserPlanCode((int) $panelUser['id']);
$plan = geekhost_plan($planCode) ?? geekhost_plan('light');
$maxDomains = geekhost_plan_domain_limit($planCode);
$nameservers = geekhost_nameservers();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf'] ?? null)) {
        $error = 'Sessão expirada.';
    } else {
        try {
            $action = (string) ($_POST['action'] ?? 'add');
            if ($action === 'refresh') {
                $domain = $svc->refreshDomainDns((int) $panelUser['id'], (string) ($_POST['domain_id'] ?? ''));
                $message = $domain['nsOk']
                    ? 'Nameservers GeekHost confirmados para ' . $domain['domain'] . '.'
                    : 'Ainda propagando. Confira ns1/ns2 e aguarde de 2 a 72 horas.';
            } elseif ($action === 'create_site') {
                $site = $svc->createSite((int) $panelUser['id'], (string) ($_POST['domain_id'] ?? ''));
                header('Location: ' . url('/painel/sites?created=' . rawurlencode((string) $site['id'])));
                exit;
            } else {
                $domain = $svc->addDomain((int) $panelUser['id'], (string) ($_POST['domain'] ?? ''));
                $message = $domain['nsOk']
                    ? 'Domínio cadastrado e nameservers OK.'
                    : 'Domínio cadastrado. Ajuste o DNS e aguarde a propagação (2 a 72 horas).';
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$domains = $svc->listDomains((int) $panelUser['id']);
$used = count($domains);
$canAdd = $used < $maxDomains;
$pct = $maxDomains > 0 ? min(100, (int) round(($used / $maxDomains) * 100)) : 0;
?>
<section class="panel-domains">
    <?php if ($message): ?><div class="gh-alert gh-alert-ok"><i data-lucide="check-circle-2"></i><div><?= e($message) ?></div></div><?php endif; ?>
    <?php if ($error): ?><div class="gh-alert gh-alert-danger"><i data-lucide="triangle-alert"></i><div><?= e($error) ?></div></div><?php endif; ?>

    <article class="gh-card usage-banner">
        <div class="usage-block">
            <span class="usage-block-label"><i data-lucide="globe-2"></i> Uso do plano</span>
            <strong><?= (int) $used ?> de <?= (int) $maxDomains ?> domínio<?= $maxDomains === 1 ? '' : 's' ?></strong>
            <div class="gh-progress"><span style="width:<?= (int) $pct ?>%"></span></div>
        </div>
        <div class="usage-block">
            <span class="usage-block-label">Plano atual</span>
            <strong><?= e((string) ($plan['name'] ?? $planCode)) ?> <span class="gh-badge gh-badge-ok">Conta</span></strong>
            <a class="gh-link" href="<?= e(url('/planos.php')) ?>">Ver detalhes do plano →</a>
        </div>
    </article>

    <div class="domain-toolbar" data-domain-filter>
        <input type="search" placeholder="Buscar domínio…" aria-label="Buscar domínio">
        <select aria-label="Filtrar status DNS">
            <option value="">Todos os status</option>
            <option value="ok">Propagado</option>
            <option value="pending">Não configurado</option>
        </select>
    </div>

    <div class="gh-table-wrap">
        <table class="gh-table">
            <thead>
            <tr>
                <th>Domínio</th>
                <th>Status DNS</th>
                <th>Plano</th>
                <th>Ações</th>
            </tr>
            </thead>
            <tbody>
            <?php if ($domains === []): ?>
                <tr><td colspan="4">Nenhum domínio cadastrado ainda. Use “Cadastrar domínio” para começar.</td></tr>
            <?php else: foreach ($domains as $domain):
                $dnsOk = !empty($domain['nsOk']);
                ?>
                <tr data-domain-row data-domain="<?= e((string) $domain['domain']) ?>" data-dns="<?= $dnsOk ? 'ok' : 'pending' ?>">
                    <td><strong><?= e((string) $domain['domain']) ?></strong></td>
                    <td>
                        <span class="gh-badge gh-badge-<?= $dnsOk ? 'ok' : 'danger' ?> gh-badge-dot">
                            <?= $dnsOk ? 'Propagado' : 'Não configurado' ?>
                        </span>
                    </td>
                    <td><?= e((string) ($plan['name'] ?? $planCode)) ?></td>
                    <td>
                        <div class="domain-actions-inline">
                            <form method="post">
                                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                <input type="hidden" name="action" value="refresh">
                                <input type="hidden" name="domain_id" value="<?= e((string) $domain['id']) ?>">
                                <button class="gh-btn gh-btn-secondary gh-btn-sm" type="submit">Verificar DNS</button>
                            </form>
                            <?php if ($dnsOk): ?>
                                <form method="post">
                                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="action" value="create_site">
                                    <input type="hidden" name="domain_id" value="<?= e((string) $domain['id']) ?>">
                                    <button class="gh-btn gh-btn-primary gh-btn-sm" type="submit">Criar meu site</button>
                                </form>
                            <?php else: ?>
                                <button class="gh-btn gh-btn-primary gh-btn-sm" type="button" disabled title="Aguarde o DNS">Criar meu site</button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <article class="ns-card">
        <div>
            <h3 style="margin:0 0 4px;font-size:16px">Apontamento DNS (Nameservers)</h3>
            <p class="panel-page-lead" style="padding:0;margin:0">Aponte o domínio para os nameservers GeekHost no seu registrador:</p>
            <div class="ns-list">
                <?php foreach ($nameservers as $ns): ?>
                    <div class="ns-item">
                        <code><?= e($ns) ?></code>
                        <button type="button" class="gh-btn gh-btn-secondary gh-btn-sm" data-copy="<?= e($ns) ?>">Copiar</button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="ns-note">
            <h3>Importante</h3>
            <p>Após alterar os nameservers, a propagação DNS pode levar de <strong>2 a 72 horas</strong>. Use “Verificar DNS” para checar quando estiver pronto.</p>
            <p style="margin:0;color:var(--gh-muted);font-size:13px">Este fluxo cadastra um domínio que você já possui. Não é registro/compra de domínio.</p>
        </div>
    </article>
</section>

<div class="gh-modal" data-modal="add-domain" hidden>
    <div class="gh-modal-backdrop" data-modal-close></div>
    <div class="gh-modal-panel" role="dialog" aria-modal="true" aria-labelledby="add-domain-title">
        <?php if ($canAdd): ?>
            <h2 id="add-domain-title">Cadastrar domínio</h2>
            <p>Informe um domínio que você já possui. Depois, aponte o DNS para a GeekHost.</p>
            <form method="post" class="gh-form">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="add">
                <label>Domínio
                    <input name="domain" type="text" placeholder="meusite.com.br" required maxlength="180">
                </label>
                <div class="gh-modal-actions">
                    <button type="button" class="gh-btn gh-btn-secondary" data-modal-close>Cancelar</button>
                    <button class="gh-btn gh-btn-primary" type="submit">Adicionar</button>
                </div>
            </form>
        <?php else: ?>
            <h2 id="add-domain-title">Limite do plano</h2>
            <p>Você atingiu o limite de <?= (int) $maxDomains ?> domínio<?= $maxDomains === 1 ? '' : 's' ?> do <?= e((string) ($plan['name'] ?? $planCode)) ?>.</p>
            <div class="gh-modal-actions">
                <button type="button" class="gh-btn gh-btn-secondary" data-modal-close>Fechar</button>
                <a class="gh-btn gh-btn-primary" href="<?= e(url('/planos.php')) ?>">Ver planos</a>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
