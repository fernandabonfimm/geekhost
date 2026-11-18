<?php

declare(strict_types=1);

$panelTitle = 'Meus sites';
$panelSection = 'sites';
require __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/includes/DomainSiteService.php';
require_once dirname(__DIR__) . '/includes/plans.php';

$svc = new DomainSiteService();
$planCode = $svc->resolveUserPlanCode((int) $panelUser['id']);
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
$created = isset($_GET['created']) ? (string) $_GET['created'] : '';
?>
<section class="panel-sites">
    <div class="home-card-head" style="margin-bottom:16px">
        <p class="panel-lead" style="margin:0">
            Limite do plano: <strong><?= (int) $maxSites ?></strong> site<?= $maxSites === 1 ? '' : 's' ?>.
            Cadastre o domínio e confirme o DNS em <a href="<?= e(url('/painel/dominios')) ?>">Domínios</a>.
        </p>
        <a class="button button-lime" href="<?= e(url('/painel/dominios')) ?>">+ Domínio / criar site</a>
    </div>

    <?php if ($created !== ''): ?>
        <p class="panel-alert panel-alert-ok">Site criado. Agora você pode instalar o WordPress ou acessar o admin quando estiver pronto.</p>
    <?php endif; ?>
    <?php if ($message): ?><p class="panel-alert panel-alert-ok"><?= e($message) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="panel-alert panel-alert-danger"><?= e($error) ?></p><?php endif; ?>

    <div class="site-list">
        <?php if ($sites === []): ?>
            <article class="home-card">
                <p class="panel-lead">Nenhum site ainda.</p>
                <a class="button button-lime" href="<?= e(url('/painel/dominios')) ?>">Começar pelos domínios</a>
            </article>
        <?php else: foreach ($sites as $site): ?>
            <article class="home-card site-item">
                <div class="home-card-head">
                    <div>
                        <h3><?= e((string) $site['title']) ?></h3>
                        <p class="panel-lead" style="margin:4px 0 0"><?= e((string) $site['domain']) ?></p>
                    </div>
                    <span class="panel-badge panel-badge-<?= e($site['wordpressStatus'] === 'installed' ? 'pago' : 'em_aberto') ?>">
                        <?php
                        echo e(match ((string) $site['wordpressStatus']) {
                            'installed' => 'WordPress ativo',
                            'queued' => 'WP na fila',
                            default => 'Sem WordPress',
                        });
                        ?>
                    </span>
                </div>
                <div class="domain-actions">
                    <?php if (in_array((string) $site['wordpressStatus'], ['not_installed'], true)): ?>
                        <form method="post">
                            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                            <input type="hidden" name="action" value="wordpress">
                            <input type="hidden" name="site_id" value="<?= e((string) $site['id']) ?>">
                            <button class="button button-lime" type="submit">Criar com WordPress</button>
                        </form>
                    <?php else: ?>
                        <button class="button button-lime" type="button" disabled>
                            <?= $site['wordpressStatus'] === 'queued' ? 'WordPress na fila…' : 'WordPress instalado' ?>
                        </button>
                    <?php endif; ?>

                    <?php if (!empty($site['adminUrl'])): ?>
                        <a class="button button-outline-dark" href="<?= e((string) $site['adminUrl']) ?>" target="_blank" rel="noopener noreferrer">Acessar admin</a>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; endif; ?>
    </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
