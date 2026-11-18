<?php

declare(strict_types=1);

$panelTitle = 'Domínios';
$panelSection = 'dominios';
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
$canAdd = count($domains) < $maxDomains;
?>
<section class="panel-domains">
    <p class="panel-lead">
        Plano atual: <strong><?= e((string) ($plan['name'] ?? $planCode)) ?></strong>
        · limite de <strong><?= (int) $maxDomains ?></strong> domínio<?= $maxDomains === 1 ? '' : 's' ?>.
        Light = 1 domínio; Empresa até 3; Premium até 10.
    </p>

    <article class="home-card domain-ns-card">
        <div class="home-card-head"><h3>Apontamento DNS obrigatório</h3></div>
        <p class="panel-lead">Para a GeekHost publicar seu site, o domínio precisa apontar para:</p>
        <ul class="domain-ns-list">
            <?php foreach ($nameservers as $ns): ?>
                <li><code><?= e($ns) ?></code></li>
            <?php endforeach; ?>
        </ul>
        <p class="panel-alert panel-alert-ok">
            Depois de alterar os nameservers no registro.br (ou no seu registrador), a propagação DNS pode levar de <strong>2 a 72 horas</strong>.
            Use “Verificar DNS” para checar quando estiver pronto.
        </p>
    </article>

    <?php if ($message): ?><p class="panel-alert panel-alert-ok"><?= e($message) ?></p><?php endif; ?>
    <?php if ($error): ?><p class="panel-alert panel-alert-danger"><?= e($error) ?></p><?php endif; ?>

    <?php if ($canAdd): ?>
        <form method="post" class="panel-form panel-form-wide domain-add-form">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="add">
            <h2>Cadastrar domínio</h2>
            <label>Domínio
                <input name="domain" type="text" placeholder="meusite.com.br" required maxlength="180">
            </label>
            <button class="button button-lime" type="submit">Adicionar domínio</button>
        </form>
    <?php else: ?>
        <p class="panel-alert panel-alert-danger">Você atingiu o limite de domínios do seu plano.</p>
    <?php endif; ?>

    <div class="domain-list">
        <?php if ($domains === []): ?>
            <article class="home-card"><p class="panel-lead">Nenhum domínio cadastrado ainda.</p></article>
        <?php else: foreach ($domains as $domain): ?>
            <article class="home-card domain-item">
                <div class="home-card-head">
                    <h3><?= e((string) $domain['domain']) ?></h3>
                    <span class="panel-badge panel-badge-<?= $domain['nsOk'] ? 'pago' : 'em_aberto' ?>">
                        <?= $domain['nsOk'] ? 'DNS OK' : 'Aguardando DNS' ?>
                    </span>
                </div>
                <p class="panel-lead"><?= e((string) ($domain['nsMessage'] ?: 'Verifique os nameservers GeekHost.')) ?></p>
                <?php if (!empty($domain['nsFound'])): ?>
                    <p class="domain-found">NS atuais: <code><?= e(implode(', ', $domain['nsFound'])) ?></code></p>
                <?php endif; ?>
                <div class="domain-actions">
                    <form method="post">
                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="action" value="refresh">
                        <input type="hidden" name="domain_id" value="<?= e((string) $domain['id']) ?>">
                        <button class="button button-outline-dark" type="submit">Verificar DNS</button>
                    </form>
                    <?php if ($domain['nsOk']): ?>
                        <form method="post">
                            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                            <input type="hidden" name="action" value="create_site">
                            <input type="hidden" name="domain_id" value="<?= e((string) $domain['id']) ?>">
                            <button class="button button-lime" type="submit">Criar meu site</button>
                        </form>
                    <?php else: ?>
                        <button class="button button-lime" type="button" disabled title="Aguarde o DNS">Criar meu site</button>
                    <?php endif; ?>
                    <a class="button button-outline-dark" href="<?= e(url('/painel/sites')) ?>">Meus sites</a>
                </div>
            </article>
        <?php endforeach; endif; ?>
    </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
