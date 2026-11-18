<?php

declare(strict_types=1);

$panelTitle = 'Meus sites';
$panelSection = 'sites';
$panelLead = 'Gerencie sites, WordPress e acessos vinculados aos seus domínios.';
require __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/includes/DomainSiteService.php';
require_once dirname(__DIR__) . '/includes/plans.php';

$svc = new DomainSiteService();
$planCode = $svc->resolveUserPlanCode((int) $panelUser['id']);
$plan = geekhost_plan($planCode) ?? geekhost_plan('light');
$maxSites = geekhost_plan_site_limit($planCode);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_validate($_POST['csrf'] ?? null)) {
        $error = 'Sessão expirada.';
    } else {
        try {
            $action = (string) ($_POST['action'] ?? '');
            if ($action === 'wordpress') {
                $site = $svc->requestWordpress((int) $panelUser['id'], (string) ($_POST['site_id'] ?? ''));
                $message = 'Instalação WordPress enfileirada para ' . $site['domain'] . '. Em breve o admin estará disponível.';
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$sites = $svc->listSites((int) $panelUser['id']);
$used = count($sites);
$pct = $maxSites > 0 ? min(100, (int) round(($used / $maxSites) * 100)) : 0;
$created = isset($_GET['created']) ? (string) $_GET['created'] : '';
$hostingStatus = (string) ($panelUser['hostingStatus'] ?? 'pending');
?>
<section class="panel-sites">
    <div class="panel-page-actions" style="margin-bottom:16px;justify-content:flex-end">
        <a class="gh-btn gh-btn-primary" href="<?= e(url('/painel/dominios')) ?>"><i data-lucide="plus"></i> Novo site</a>
    </div>
    <?php if ($created !== ''): ?>
        <div class="gh-alert gh-alert-ok"><i data-lucide="check-circle-2"></i><div>Site criado. Agora você pode instalar o WordPress quando estiver pronto.</div></div>
    <?php endif; ?>
    <?php if ($message): ?><div class="gh-alert gh-alert-ok"><i data-lucide="check-circle-2"></i><div><?= e($message) ?></div></div><?php endif; ?>
    <?php if ($error): ?><div class="gh-alert gh-alert-danger"><i data-lucide="triangle-alert"></i><div><?= e($error) ?></div></div><?php endif; ?>

    <article class="gh-card usage-banner">
        <div class="usage-block">
            <span class="usage-block-label"><i data-lucide="layers"></i> Uso do plano</span>
            <strong><?= (int) $used ?> de <?= (int) $maxSites ?> site<?= $maxSites === 1 ? '' : 's' ?></strong>
            <div class="gh-progress"><span style="width:<?= (int) $pct ?>%"></span></div>
        </div>
        <div class="usage-block">
            <span class="usage-block-label">Plano atual</span>
            <strong><?= e((string) ($plan['name'] ?? $planCode)) ?></strong>
            <a class="gh-link" href="<?= e(url('/painel/dominios')) ?>">Começar pelo domínio →</a>
        </div>
    </article>

    <div class="site-cards">
        <?php if ($sites === []): ?>
            <article class="gh-card site-empty">
                <strong style="font-size:18px;color:var(--gh-ink)">Nenhum site ainda</strong>
                <p style="margin:0;max-width:42ch;color:var(--gh-muted)">Cadastre um domínio, aponte o DNS para ns1/ns2.geekhost.com.br e use “Criar meu site” quando a propagação estiver OK.</p>
                <a class="gh-btn gh-btn-primary" href="<?= e(url('/painel/dominios')) ?>">Começar pelos domínios</a>
            </article>
        <?php else: foreach ($sites as $site):
            $wp = (string) ($site['wordpressStatus'] ?? 'not_installed');
            $status = (string) ($site['status'] ?? 'ready');
            $isActiveHosting = $hostingStatus === 'active';
            ?>
            <article class="gh-card site-card">
                <div class="site-card-top">
                    <div class="site-card-title">
                        <span class="home-service-icon"><i data-lucide="app-window"></i></span>
                        <div>
                            <h3><?= e((string) $site['domain']) ?></h3>
                            <p>Hospedagem · <?= e((string) ($plan['name'] ?? $planCode)) ?></p>
                        </div>
                    </div>
                    <div class="site-badges">
                        <?php if ($isActiveHosting && $status === 'ready'): ?>
                            <span class="gh-badge gh-badge-ok gh-badge-dot">Ativo</span>
                        <?php elseif ($status === 'wordpress_queued' || $wp === 'queued'): ?>
                            <span class="gh-badge gh-badge-warn gh-badge-dot">Provisionando WP</span>
                        <?php else: ?>
                            <span class="gh-badge gh-badge-warn gh-badge-dot">Cadastrado</span>
                        <?php endif; ?>
                        <?php if ($wp === 'installed'): ?>
                            <span class="gh-badge gh-badge-ok"><i data-lucide="lock" style="width:12px;height:12px"></i> WordPress</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="site-meta">
                    <span>Status real: <?= e($isActiveHosting ? 'hospedagem ativa' : 'aguardando provisionamento') ?></span>
                    <span>WP: <?= e(match ($wp) {
                        'installed' => 'instalado',
                        'queued' => 'na fila',
                        default => 'não instalado',
                    }) ?></span>
                </div>
                <div class="site-actions">
                    <?php if ($wp === 'not_installed'): ?>
                        <form method="post">
                            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                            <input type="hidden" name="action" value="wordpress">
                            <input type="hidden" name="site_id" value="<?= e((string) $site['id']) ?>">
                            <button class="gh-btn gh-btn-primary" type="submit"><i data-lucide="layout-template"></i> Criar com WordPress</button>
                        </form>
                    <?php elseif ($wp === 'queued'): ?>
                        <button class="gh-btn gh-btn-secondary" type="button" disabled>WordPress na fila…</button>
                    <?php elseif ($wp === 'installed' && !empty($site['adminUrl'])): ?>
                        <a class="gh-btn gh-btn-primary" href="<?= e((string) $site['adminUrl']) ?>" target="_blank" rel="noopener noreferrer">Acessar admin</a>
                    <?php endif; ?>

                    <button class="gh-btn gh-btn-secondary" type="button" disabled title="Disponível após provisionamento do cPanel">cPanel</button>
                    <a class="gh-btn gh-btn-secondary" href="<?= e(url('/painel/dominios')) ?>">Gerenciar</a>
                </div>
            </article>
        <?php endforeach; endif; ?>
    </div>

    <?php if ($used >= $maxSites && $maxSites > 0): ?>
        <article class="gh-card site-upsell">
            <div>
                <strong style="font-size:16px">Precisa de mais sites?</strong>
                <p style="margin:4px 0 0;color:var(--gh-muted);font-size:14px">Você atingiu o limite do <?= e((string) ($plan['name'] ?? $planCode)) ?>.</p>
            </div>
            <a class="gh-btn gh-btn-primary" href="<?= e(url('/planos.php')) ?>">Contratar hospedagem</a>
        </article>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
