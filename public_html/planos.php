<?php

$pageTitle = 'Planos de hospedagem de sites e VPS';
$pageDescription = 'Conheça os planos de hospedagem de sites e servidores VPS da GeekHost: Light, Empresa, Premium, VPS e Personalizado.';
$currentPage = 'planos';
$sharedPlanFeatures = [
    'SSL grátis em todos os planos',
    'Suporte premium humano',
    'PHP, NodeJS, Ruby e Python',
    'Painel cPanel/WHM',
    'Instalador de apps',
    'Proteção Imunify360',
    'Desempenho ultrarrápido com uptime de 99,9%',
    'Criadores de sites com IA',
    'Firewall WAF',
    'WordPress Toolkit',
    'Anti-Spam Profissional',
];
$vpsPlanFeatures = [
    'Armazenamento NVMe SSD',
    'Acesso root',
    'LiteSpeed Webserver',
    'SSL grátis',
    'Softaculous',
    'CloudLinux + CageFS',
    'Cloudflare CDN',
    'Suporte GeekHost',
];
require __DIR__ . '/includes/header.php';
?>

<section class="page-hero page-width">
    <p class="eyebrow">Hospedagem e VPS</p>
    <h1>Escolha a estrutura<br>para o seu projeto.</h1>
    <p class="page-hero-copy">Os planos Light, Empresa e Premium são de hospedagem de sites. O Plano VPS oferece recursos dedicados para quem precisa de mais desempenho e controle.</p>
</section>

<section class="detail-plan-grid page-width" aria-label="Planos GeekHost">
    <article class="plan-card plan-card-light detail-plan-card" id="light" data-plan-card>
        <div class="detail-plan-top"><div class="detail-plan-labels"><span class="plan-type-label">Hospedagem de sites</span><span class="plan-number">01 / PRESENÇA</span></div><img class="detail-plan-emblem" src="assets/images/emblema.png" alt="" aria-hidden="true" width="34" height="34"></div>
        <div class="detail-plan-content">
            <div class="detail-plan-intro"><h2>Plano Light</h2><p>Para colocar seu site e suas ideias no ar.</p></div>
            <div class="detail-plan-offer"><p class="plan-price"><strong>R$ 19,90</strong><span>/no 1º mês</span></p><p class="plan-regular-price">A partir do 2º mês: <strong>R$ 35,90/mês</strong></p><div class="detail-plan-period-prices"><p>Semestral: <strong>R$ 239,00</strong></p><p>Anual: <strong>R$ 299,90</strong></p></div><div class="plan-first-month"><span>7 dias grátis para começar</span></div><div class="plan-savings"><img class="plan-savings-emblem" src="assets/images/emblema.png" alt="" aria-hidden="true" width="24" height="24"><span><strong>45% OFF | Economize R$ 16,00</strong><small>no 1º mês</small></span></div></div>
            <ul class="plan-features"><li>5 contas de e-mail</li><li>10 GB de espaço</li><li>Banco de dados ilimitado</li></ul>
            <ul class="plan-includes-grid detail-plan-includes" aria-label="Recursos incluídos no Plano Light">
                <?php foreach ($sharedPlanFeatures as $feature): ?><li><?= e($feature) ?></li><?php endforeach; ?>
            </ul>
        </div>
        <a class="arrow-link" href="https://wa.me/5516997399174?text=Quero%20conhecer%20o%20Plano%20Light">Assinar o Light <span aria-hidden="true">↗</span></a>
    </article>
    <article class="plan-card plan-card-green plan-card-recommended detail-plan-card" id="empresa" data-plan-card>
        <span class="plan-recommendation">✓ Recomendado</span>
        <div class="detail-plan-top"><div class="detail-plan-labels"><span class="plan-type-label">Hospedagem de sites</span><span class="plan-number">02 / CRESCIMENTO</span></div><img class="detail-plan-emblem" src="assets/images/emblema.png" alt="" aria-hidden="true" width="34" height="34"></div>
        <div class="detail-plan-content">
            <div class="detail-plan-intro"><h2>Plano Empresa</h2><p>Para negócios e equipes que querem crescer online.</p></div>
            <div class="detail-plan-offer"><p class="plan-price"><strong>R$ 29,90</strong><span>/no 1º mês</span></p><p class="plan-regular-price">A partir do 2º mês: <strong>R$ 45,90/mês</strong></p><div class="detail-plan-period-prices"><p>Semestral: <strong>R$ 299,00</strong></p><p>Anual: <strong>R$ 394,00</strong></p></div><div class="plan-first-month"><span>7 dias grátis para começar</span></div><div class="plan-savings"><img class="plan-savings-emblem" src="assets/images/emblema.png" alt="" aria-hidden="true" width="24" height="24"><span><strong>35% OFF | Economize R$ 16,00</strong><small>no 1º mês</small></span></div></div>
            <ul class="plan-features"><li>10 contas de e-mail</li><li>15 GB de espaço</li><li>Banco de dados ilimitado</li></ul>
            <ul class="plan-includes-grid detail-plan-includes" aria-label="Recursos incluídos no Plano Empresa">
                <?php foreach ($sharedPlanFeatures as $feature): ?><li style="color: white"><?= e($feature) ?></li><?php endforeach; ?>
            </ul>
        </div>
        <a class="arrow-link" href="https://wa.me/5516997399174?text=Quero%20conhecer%20o%20Plano%20Empresa">Assinar o Empresa <span aria-hidden="true">↗</span></a>
    </article>
    <article class="plan-card plan-card-lime detail-plan-card" id="premium" data-plan-card>
        <div class="detail-plan-top"><div class="detail-plan-labels"><span class="plan-type-label">Hospedagem de sites</span><span class="plan-number">03 / COMÉRCIO</span></div><img class="detail-plan-emblem" src="assets/images/emblema.png" alt="" aria-hidden="true" width="34" height="34"></div>
        <div class="detail-plan-content">
            <div class="detail-plan-intro"><h2>Plano Premium</h2><p>Para projetos que precisam de uma estrutura mais completa.</p></div>
            <div class="detail-plan-offer"><p class="plan-price"><strong>R$ 39,90</strong><span>/no 1º mês</span></p><p class="plan-regular-price">A partir do 2º mês: <strong>R$ 55,90/mês</strong></p><div class="detail-plan-period-prices"><p>Semestral: <strong>R$ 359,00</strong></p><p>Anual: <strong>R$ 460,00</strong></p></div><div class="plan-first-month"><span>7 dias grátis para começar</span></div><div class="plan-savings"><img class="plan-savings-emblem" src="assets/images/emblema.png" alt="" aria-hidden="true" width="24" height="24"><span><strong>29% OFF | Economize R$ 16,00</strong><small>no 1º mês</small></span></div></div>
            <ul class="plan-features"><li>E-mails ilimitados</li><li>25 GB de espaço</li><li>Banco de dados ilimitado</li></ul>
            <ul class="plan-includes-grid detail-plan-includes" aria-label="Recursos incluídos no Plano Premium">
                <?php foreach ($sharedPlanFeatures as $feature): ?><li><?= e($feature) ?></li><?php endforeach; ?>
            </ul>
        </div>
        <a class="arrow-link" href="https://wa.me/5516997399174?text=Quero%20conhecer%20o%20Plano%20Premium">Assinar o Premium <span aria-hidden="true">↗</span></a>
    </article>
    <article class="plan-card plan-card-vps detail-plan-card" id="vps" data-plan-card>
        <div class="detail-plan-top"><div class="detail-plan-labels"><span class="plan-type-label">Servidor VPS</span><span class="plan-number">04 / SERVIDOR</span></div><img class="detail-plan-emblem" src="assets/images/emblema.png" alt="" aria-hidden="true" width="34" height="34"></div>
        <div class="detail-plan-content">
            <div class="detail-plan-intro"><h2>Plano VPS</h2><p>Servidores com recursos dedicados, NVMe e acesso root para projetos que pedem mais desempenho.</p></div>
            <div class="detail-plan-offer"><p class="plan-price"><strong>R$ 89,90</strong><span>/a partir de</span></p><p class="plan-regular-price">Compare os planos Start, Plus e Pro na página de VPS.</p><div class="plan-first-month"><span>Foco em performance e controle</span></div></div>
            <ul class="plan-features"><li>NVMe SSD</li><li>Acesso root</li><li>LiteSpeed + SSL</li></ul>
            <ul class="plan-includes-grid detail-plan-includes" aria-label="Recursos incluídos no Plano VPS">
                <?php foreach ($vpsPlanFeatures as $feature): ?><li><?= e($feature) ?></li><?php endforeach; ?>
            </ul>
        </div>
        <a class="arrow-link" href="vps.php">Ver planos VPS <span aria-hidden="true">↗</span></a>
    </article>
    <article class="plan-card plan-card-custom detail-plan-card" id="personalizado" data-plan-card>
        <div class="detail-plan-top"><span class="plan-number">05 / SOB MEDIDA</span><img class="detail-plan-emblem" src="assets/images/emblema.png" alt="" aria-hidden="true" width="34" height="34"></div>
        <div class="detail-plan-content detail-plan-content-custom">
            <div class="detail-plan-intro"><h2>Plano Personalizado</h2><p>Precisa de mais? Entre em contato!</p></div>
            <p class="plan-custom-note">Converse com a equipe para avaliar uma solução de hospedagem ou VPS adequada às necessidades do seu projeto.</p>
        </div>
        <a class="arrow-link" href="https://wa.me/5516997399174?text=Quero%20conversar%20sobre%20um%20Plano%20Personalizado">Fale com a equipe <span aria-hidden="true">↗</span></a>
    </article>
</section>

<p class="plan-commitment page-width" style="margin-bottom: 42px"><span>PLANOS SEM FIDELIDADE. Cancele quando quiser.</span></p>

<?php require __DIR__ . '/includes/footer.php'; ?>
