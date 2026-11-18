<?php

declare(strict_types=1);

$panelTitle = 'Início';
$panelSection = 'inicio';
require __DIR__ . '/_layout.php';
require_once dirname(__DIR__) . '/includes/OrderService.php';
require_once dirname(__DIR__) . '/includes/db.php';

$orders = new OrderService();
$counts = $orders->invoiceCounts((int) $panelUser['id']);
$pdo = geekhost_db();
$services = $pdo->prepare('SELECT * FROM services WHERE user_id = :u ORDER BY id DESC LIMIT 8');
$services->execute([':u' => $panelUser['id']]);
$serviceRows = $services->fetchAll() ?: [];
$firstName = explode(' ', (string) $panelUser['name'])[0] ?: 'cliente';
$hostingLabel = match ((string) ($panelUser['hostingStatus'] ?? 'pending')) {
    'active' => 'Ativo',
    'suspended' => 'Suspenso',
    default => 'Pendente',
};
$hostingClass = match ((string) ($panelUser['hostingStatus'] ?? 'pending')) {
    'active' => 'is-ok',
    'suspended' => 'is-bad',
    default => 'is-warn',
};
?>
<section class="panel-home">
    <div class="home-hero">
        <div>
            <p class="home-breadcrumb">Portal / Área do cliente</p>
            <h2 class="home-greeting">Olá, <?= e($firstName) ?>!</h2>
            <div class="home-stats">
                <div><strong><?= count($serviceRows) ?></strong><span>Serviços</span></div>
                <div><strong><?= (int) $counts['em_aberto'] ?></strong><span>Faturas abertas</span></div>
                <div><strong><?= (int) $counts['pago'] ?></strong><span>Faturas pagas</span></div>
                <div><strong class="home-status-pill <?= e($hostingClass) ?>"><?= e($hostingLabel) ?></strong><span>Hospedagem</span></div>
            </div>
        </div>
        <div class="home-hero-actions">
            <a class="button button-lime" href="<?= e(url('/planos.php')) ?>">+ Contratar</a>
            <a class="button button-outline-dark" href="<?= e(url('/painel/faturas')) ?>">Ver faturas</a>
        </div>
    </div>

    <div class="home-banner">
        <div>
            <p class="eyebrow">GeekHost</p>
            <h3>Seu painel, do checkout à renovação</h3>
            <ul>
                <li>Faturas com filtros e detalhes</li>
                <li>Carteira com cartão de renovação Asaas</li>
                <li>Status real — sem ativar hospedagem fictícia</li>
            </ul>
        </div>
        <a class="button button-lime" href="<?= e(url('/painel/carteira')) ?>">Configurar carteira</a>
    </div>

    <div class="home-grid">
        <div class="home-col-main">
            <article class="home-card">
                <div class="home-card-head">
                    <h3>Meus serviços</h3>
                    <a href="<?= e(url('/planos.php')) ?>">Contratar</a>
                </div>
                <?php if ($serviceRows === []): ?>
                    <div class="home-empty">
                        <p>Nenhum serviço provisionado ainda.</p>
                        <p class="panel-lead">Após o pagamento, o pedido fica em fila (<code>paid_pending_provisioning</code>) até o provisionamento real.</p>
                        <a class="button button-lime" href="<?= e(url('/planos.php')) ?>">Ver planos</a>
                    </div>
                <?php else: ?>
                    <ul class="home-service-list">
                        <?php foreach ($serviceRows as $service): ?>
                            <li>
                                <div>
                                    <strong><?= e((string) $service['domain']) ?></strong>
                                    <span><?= e((string) $service['plan_code']) ?></span>
                                </div>
                                <span class="panel-badge panel-badge-<?= e((string) $service['status'] === 'paid_pending_provisioning' ? 'em_aberto' : 'pago') ?>">
                                    <?= e((string) $service['status']) ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </article>

            <article class="home-card">
                <div class="home-card-head">
                    <h3>Atalhos rápidos</h3>
                </div>
                <div class="home-resources">
                    <a href="<?= e(url('/painel/faturas')) ?>"><i class="bi bi-receipt"></i><strong>Faturas</strong><span>Pagamentos e histórico</span></a>
                    <a href="<?= e(url('/painel/carteira')) ?>"><i class="bi bi-credit-card-2-front"></i><strong>Carteira</strong><span>Cartão de renovação</span></a>
                    <a href="<?= e(url('/painel/perfil')) ?>"><i class="bi bi-person"></i><strong>Perfil</strong><span>Dados e senha</span></a>
                    <a href="<?= e(url('/')) ?>"><i class="bi bi-globe2"></i><strong>Site</strong><span>Voltar à GeekHost</span></a>
                </div>
            </article>
        </div>

        <aside class="home-col-side">
            <article class="home-card">
                <div class="home-card-head"><h3>Resumo financeiro</h3></div>
                <ul class="home-mini-stats">
                    <li><span>Em aberto</span><strong><?= (int) $counts['em_aberto'] ?></strong></li>
                    <li><span>Pagas</span><strong><?= (int) $counts['pago'] ?></strong></li>
                    <li><span>Total</span><strong><?= (int) $counts['total'] ?></strong></li>
                </ul>
                <a class="button button-outline-dark home-card-btn" href="<?= e(url('/painel/faturas')) ?>">Abrir faturamento</a>
            </article>
            <article class="home-card">
                <div class="home-card-head"><h3>Suporte</h3></div>
                <p class="panel-lead">Fale com a GeekHost pelo WhatsApp para dúvidas de conta, domínio ou provisionamento.</p>
                <?php if (!empty($companyWhatsapp)): ?>
                    <a class="button button-lime home-card-btn" href="https://wa.me/<?= e($companyWhatsapp) ?>" target="_blank" rel="noopener noreferrer">WhatsApp</a>
                <?php endif; ?>
            </article>
        </aside>
    </div>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
