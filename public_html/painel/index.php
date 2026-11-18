<?php

declare(strict_types=1);

$panelTitle = 'Início';
$panelSection = 'inicio';
$panelLead = 'Acompanhe seus serviços, domínios e faturas em um só lugar.';
require __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/includes/OrderService.php';
require_once dirname(__DIR__) . '/includes/DomainSiteService.php';
require_once dirname(__DIR__) . '/includes/plans.php';
require_once dirname(__DIR__) . '/includes/db.php';

$orders = new OrderService();
$domainSvc = new DomainSiteService();
$userId = (int) $panelUser['id'];
$counts = $orders->invoiceCounts($userId);
$allInvoices = $orders->listInvoicesForUser($userId);
$openAmount = 0.0;
$paidAmount = 0.0;
$overdueAmount = 0.0;
foreach ($allInvoices as $inv) {
    $amount = (float) ($inv['amount'] ?? 0);
    $status = (string) ($inv['status'] ?? '');
    if (in_array($status, ['awaiting_payment', 'overdue'], true)) {
        $openAmount += $amount;
        if ($status === 'overdue') {
            $overdueAmount += $amount;
        }
    }
    if ($status === 'paid_pending_provisioning') {
        $paidAmount += $amount;
    }
}

$domains = $domainSvc->listDomains($userId);
$sites = $domainSvc->listSites($userId);
$planCode = $domainSvc->resolveUserPlanCode($userId);
$plan = geekhost_plan($planCode) ?? geekhost_plan('light');

$pdo = geekhost_db();
$services = $pdo->prepare('SELECT * FROM services WHERE user_id = :u ORDER BY id DESC LIMIT 12');
$services->execute([':u' => $userId]);
$serviceRows = $services->fetchAll() ?: [];

$firstName = explode(' ', (string) $panelUser['name'])[0] ?: 'cliente';
$hostingStatus = (string) ($panelUser['hostingStatus'] ?? 'pending');
$hasCard = !empty($panelUser['cardLast4']);
$pendingProvisioning = false;
foreach ($serviceRows as $service) {
    if ((string) ($service['status'] ?? '') === 'paid_pending_provisioning') {
        $pendingProvisioning = true;
        break;
    }
}
if ($hostingStatus === 'pending' && $counts['pago'] > 0) {
    $pendingProvisioning = true;
}

$fmtMoney = static function (float $n): string {
    return 'R$ ' . number_format($n, 2, ',', '.');
};

$statusLabel = static function (string $status): array {
    return match ($status) {
        'active', 'ready' => ['Ativo', 'ok'],
        'paid_pending_provisioning' => ['Provisionando', 'warn'],
        'suspended' => ['Suspenso', 'danger'],
        default => ['Pendente', 'warn'],
    };
};
?>
<section class="panel-home">
    <div class="home-greeting-row">
        <h2 class="home-greeting">Olá, <span><?= e($firstName) ?></span>!</h2>
    </div>

    <article class="home-hero-banner">
        <div class="home-hero-copy">
            <h2>Sua ideia no ar.<br><em>Seu projeto em crescimento.</em></h2>
            <ul class="home-hero-pills">
                <li><i data-lucide="server"></i> Hospedagem de alta performance</li>
                <li><i data-lucide="shield-check"></i> Segurança e estabilidade</li>
                <li><i data-lucide="zap"></i> Suporte especializado</li>
            </ul>
        </div>
        <img class="home-hero-banner-img" src="<?= e(asset('assets/images/banner-painel-inicio.png')) ?>" alt="GeekHost — mascotes Geeko" width="1400" height="360">
    </article>

    <div class="home-kpi-grid">
        <article class="gh-card home-kpi">
            <div class="home-kpi-icon"><i data-lucide="layers"></i></div>
            <p class="home-kpi-label">Sites hospedados</p>
            <p class="home-kpi-value"><?= count($sites) ?></p>
            <a class="home-kpi-action" href="<?= e(url('/painel/sites')) ?>">Gerenciar sites →</a>
        </article>
        <article class="gh-card home-kpi">
            <div class="home-kpi-icon"><i data-lucide="globe-2"></i></div>
            <p class="home-kpi-label">Domínios</p>
            <p class="home-kpi-value"><?= count($domains) ?></p>
            <a class="home-kpi-action" href="<?= e(url('/painel/dominios')) ?>">Gerenciar domínios →</a>
        </article>
        <article class="gh-card home-kpi">
            <div class="home-kpi-icon"><i data-lucide="file-text"></i></div>
            <p class="home-kpi-label">Faturas em aberto</p>
            <p class="home-kpi-value"><?= e($fmtMoney($openAmount)) ?></p>
            <a class="home-kpi-action" href="<?= e(url('/painel/faturas?status=em_aberto')) ?>">Ver faturas →</a>
        </article>
        <article class="gh-card home-kpi">
            <div class="home-kpi-icon"><i data-lucide="credit-card"></i></div>
            <p class="home-kpi-label">Cartão de renovação</p>
            <p class="home-kpi-value" style="font-size:22px">
                <?= $hasCard ? '•••• ' . e((string) $panelUser['cardLast4']) : 'Não cadastrado' ?>
            </p>
            <a class="home-kpi-action" href="<?= e(url('/painel/carteira')) ?>">
                <?= $hasCard ? 'Gerenciar cartão →' : 'Adicionar cartão →' ?>
            </a>
        </article>
    </div>

    <div class="home-split">
        <article class="gh-card gh-card-pad">
            <div class="gh-card-head">
                <h3>Meus serviços</h3>
                <a class="gh-link" href="<?= e(url('/planos.php')) ?>">Ver todos →</a>
            </div>
            <?php if ($serviceRows === [] && $sites === []): ?>
                <div class="home-empty">
                    <strong>Nenhum serviço provisionado</strong>
                    <p>Após o pagamento, o pedido fica em fila até o provisionamento real. Nada é marcado como ativo antes disso.</p>
                    <a class="gh-btn gh-btn-primary" href="<?= e(url('/planos.php')) ?>">Contratar hospedagem</a>
                </div>
            <?php else: ?>
                <div class="gh-table-wrap" style="box-shadow:none;border:0">
                    <table class="gh-table" style="min-width:560px">
                        <thead>
                        <tr>
                            <th>Serviço</th>
                            <th>Plano</th>
                            <th>Domínio</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if ($serviceRows !== []): foreach ($serviceRows as $service):
                            [$label, $tone] = $statusLabel((string) ($service['status'] ?? ''));
                            $planName = geekhost_plan((string) $service['plan_code'])['name'] ?? (string) $service['plan_code'];
                            ?>
                            <tr>
                                <td>
                                    <div class="home-service-cell">
                                        <span class="home-service-icon"><i data-lucide="server"></i></span>
                                        <strong>Hospedagem</strong>
                                    </div>
                                </td>
                                <td><?= e((string) $planName) ?></td>
                                <td><?= e((string) ($service['domain'] ?: '—')) ?></td>
                                <td><span class="gh-badge gh-badge-<?= e($tone) ?> gh-badge-dot"><?= e($label) ?></span></td>
                                <td>
                                    <?php if ((string) $service['status'] === 'paid_pending_provisioning'): ?>
                                        <a class="gh-btn gh-btn-secondary gh-btn-sm" href="<?= e(url('/painel/faturas')) ?>">Acompanhar</a>
                                    <?php else: ?>
                                        <a class="gh-btn gh-btn-secondary gh-btn-sm" href="<?= e(url('/painel/sites')) ?>">Gerenciar</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; else: foreach ($sites as $site): ?>
                            <tr>
                                <td>
                                    <div class="home-service-cell">
                                        <span class="home-service-icon"><i data-lucide="layers"></i></span>
                                        <strong>Site</strong>
                                    </div>
                                </td>
                                <td><?= e((string) ($plan['name'] ?? $planCode)) ?></td>
                                <td><?= e((string) $site['domain']) ?></td>
                                <td><span class="gh-badge gh-badge-ok gh-badge-dot">Cadastrado</span></td>
                                <td><a class="gh-btn gh-btn-secondary gh-btn-sm" href="<?= e(url('/painel/sites')) ?>">Gerenciar</a></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </article>

        <article class="gh-card gh-card-pad">
            <div class="gh-card-head"><h3>Ações rápidas</h3></div>
            <div class="home-quick">
                <a href="<?= e(url('/planos.php')) ?>">
                    <span class="home-quick-icon"><i data-lucide="plus"></i></span>
                    <strong>Contratar hospedagem</strong>
                </a>
                <a href="<?= e(url('/painel/dominios')) ?>">
                    <span class="home-quick-icon"><i data-lucide="globe-2"></i></span>
                    <strong>Cadastrar domínio</strong>
                </a>
                <a href="<?= e(url('/painel/sites')) ?>">
                    <span class="home-quick-icon"><i data-lucide="layers"></i></span>
                    <strong>Criar novo site</strong>
                </a>
                <?php if (!empty($companyWhatsapp)): ?>
                    <a href="https://wa.me/<?= e($companyWhatsapp) ?>" target="_blank" rel="noopener noreferrer">
                        <span class="home-quick-icon"><i data-lucide="headset"></i></span>
                        <strong>Abrir suporte</strong>
                    </a>
                <?php else: ?>
                    <a href="<?= e(url('/')) ?>">
                        <span class="home-quick-icon"><i data-lucide="headset"></i></span>
                        <strong>Abrir suporte</strong>
                    </a>
                <?php endif; ?>
            </div>
        </article>
    </div>

    <?php if ($pendingProvisioning): ?>
        <article class="gh-card home-provision">
            <div class="home-provision-icon"><i data-lucide="loader-circle"></i></div>
            <div class="home-provision-copy">
                <strong>Provisionamento em andamento</strong>
                <span>Seu serviço está sendo configurado. O processo pode levar alguns minutos — o status muda só após a confirmação real.</span>
            </div>
            <div class="home-provision-bar">
                <div class="gh-progress" style="width:100%"><span style="width:60%"></span></div>
                <small>Em fila</small>
            </div>
        </article>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
