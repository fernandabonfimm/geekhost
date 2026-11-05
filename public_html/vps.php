<?php

$pageTitle = 'Servidores VPS NVMe';
$pageDescription = 'Servidores VPS GeekHost com NVMe, LiteSpeed, acesso root, SSL grátis e suporte humano. Escolha o plano ideal para o seu projeto.';
$currentPage = 'vps';
require __DIR__ . '/includes/vps-plans.php';
require __DIR__ . '/includes/header.php';
$whatsappBase = 'https://wa.me/5516997399174?text=';
?>

<section class="vps-hero">
    <div class="page-width vps-hero-layout">
        <div class="vps-hero-copy">
            <p class="eyebrow eyebrow-light"><span></span> Servidores VPS GeekHost</p>
            <h1>Servidores VPS NVMe<br>com desempenho de verdade.</h1>
            <p>Recursos dedicados, disco ultrarrápido e controle total do ambiente. Ideal para sites exigentes, aplicações e projetos que pedem mais do que a hospedagem compartilhada.</p>
            <div class="vps-hero-actions">
                <a class="button button-lime" href="#planos-vps">Ver planos VPS <span aria-hidden="true">↘</span></a>
                <a class="button button-outline button-support-light" href="<?= e($whatsappBase . rawurlencode('Quero conhecer os servidores VPS GeekHost')) ?>">Falar no WhatsApp</a>
            </div>
            <ul class="vps-hero-chips" aria-label="Destaques técnicos">
                <li>NVMe SSD</li>
                <li>LiteSpeed</li>
                <li>Acesso root</li>
                <li>CloudLinux</li>
                <li>SSL grátis</li>
            </ul>
        </div>
        <aside class="vps-hero-card" aria-label="Destaque de preço VPS">
            <p class="vps-hero-card-kicker">Melhor custo-benefício</p>
            <h2>VPS Plus</h2>
            <p class="vps-hero-card-price"><strong>R$ 149,90</strong><span>/mês</span></p>
            <ul>
                <li>2 vCPU · 4 GB RAM</li>
                <li>80 GB NVMe SSD</li>
                <li>3 TB de banda</li>
                <li>Root + LiteSpeed + SSL</li>
            </ul>
            <a class="button button-lime" href="<?= e($whatsappBase . rawurlencode('Quero contratar o VPS Plus')) ?>">Contratar agora</a>
        </aside>
    </div>
</section>

<section class="vps-promo page-width" aria-label="Chamada para planos VPS">
    <div class="vps-promo-inner">
        <p><strong>Servidor VPS a partir de R$ 89,90/mês.</strong> Performance dedicada, SSL grátis e suporte GeekHost.</p>
        <a class="button button-dark" href="#planos-vps">Escolher plano <span aria-hidden="true">↘</span></a>
    </div>
</section>

<section class="vps-plans-section page-width" id="planos-vps" aria-labelledby="vps-plans-title">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Escolha seu servidor</p>
            <h2 id="vps-plans-title">Planos VPS para cada estágio.</h2>
        </div>
        <p class="section-lede">Compare recursos dedicados e escolha o plano certo para o seu projeto. A contratação é feita com a equipe GeekHost.</p>
    </div>
    <div class="vps-plan-grid">
        <?php foreach ($vpsPlans as $plan): ?>
            <article class="vps-plan-card<?= !empty($plan['highlight']) ? ' is-highlight' : '' ?>" id="<?= e($plan['id']) ?>">
                <?php if (!empty($plan['highlight'])): ?><span class="plan-recommendation">✓ <?= e($plan['tag']) ?></span><?php else: ?><span class="vps-plan-tag"><?= e($plan['tag']) ?></span><?php endif; ?>
                <h3><?= e($plan['name']) ?></h3>
                <p class="plan-price"><strong><?= e($plan['price']) ?></strong><span><?= e($plan['period']) ?></span></p>
                <ul class="vps-plan-specs">
                    <?php foreach ($plan['specs'] as $spec): ?><li><?= e($spec) ?></li><?php endforeach; ?>
                </ul>
                <ul class="plan-features">
                    <?php foreach ($plan['features'] as $feature): ?><li><?= e($feature) ?></li><?php endforeach; ?>
                </ul>
                <a class="arrow-link" href="<?= e($whatsappBase . rawurlencode('Quero contratar o ' . $plan['name'])) ?>">Contratar <?= e($plan['name']) ?> <span aria-hidden="true">↗</span></a>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="vps-stats" aria-label="Indicadores de desempenho VPS">
    <div class="page-width vps-stats-grid">
        <article><strong>NVMe</strong><span>Disco de alta velocidade</span></article>
        <article><strong>99,9%</strong><span>Meta de uptime</span></article>
        <article><strong>Root</strong><span>Controle total do servidor</span></article>
        <article><strong>24/7</strong><span>Orientação via WhatsApp</span></article>
    </div>
</section>

<section class="vps-usecases page-width" aria-labelledby="vps-usecases-title">
    <div class="vps-usecases-layout">
        <div>
            <p class="eyebrow">Para vários projetos</p>
            <h2 id="vps-usecases-title">VPS para quem precisa de mais controle.</h2>
            <p>Do site institucional ao ambiente de aplicação: o VPS GeekHost entrega recursos dedicados e liberdade para configurar o stack do seu jeito.</p>
        </div>
        <ul class="vps-usecases-list">
            <?php foreach ($vpsUseCases as $useCase): ?><li><?= e($useCase) ?></li><?php endforeach; ?>
        </ul>
    </div>
</section>

<section class="vps-features-section" aria-labelledby="vps-features-title">
    <div class="page-width">
        <div class="section-heading">
            <div>
                <p class="eyebrow">Recursos e vantagens</p>
                <h2 id="vps-features-title">O essencial para vender e rodar no VPS.</h2>
            </div>
            <p class="section-lede">Selecionamos o que importa para performance, segurança e publicação — sem dispersar o foco do servidor.</p>
        </div>
        <div class="vps-feature-grid">
            <?php foreach ($vpsFeatures as $index => $feature): ?>
                <article class="vps-feature-item">
                    <span><?= e(str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT)) ?></span>
                    <h3><?= e($feature['title']) ?></h3>
                    <p><?= e($feature['text']) ?></p>
                </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="vps-compare page-width" aria-labelledby="vps-compare-title">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Comparativo</p>
            <h2 id="vps-compare-title">Veja as diferenças entre os planos.</h2>
        </div>
    </div>
    <div class="vps-table-wrap">
        <table class="vps-compare-table">
            <thead>
                <tr>
                    <th scope="col">Recurso</th>
                    <?php foreach ($vpsPlans as $plan): ?><th scope="col"><?= e($plan['name']) ?></th><?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <th scope="row">vCPU</th>
                    <td>1</td><td>2</td><td>4</td>
                </tr>
                <tr>
                    <th scope="row">RAM</th>
                    <td>2 GB</td><td>4 GB</td><td>8 GB</td>
                </tr>
                <tr>
                    <th scope="row">NVMe</th>
                    <td>40 GB</td><td>80 GB</td><td>160 GB</td>
                </tr>
                <tr>
                    <th scope="row">Banda</th>
                    <td>2 TB</td><td>3 TB</td><td>4 TB</td>
                </tr>
                <tr>
                    <th scope="row">Preço</th>
                    <td>R$ 89,90</td><td>R$ 149,90</td><td>R$ 249,90</td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

<section class="vps-steps page-width" aria-labelledby="vps-steps-title">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Como funciona</p>
            <h2 id="vps-steps-title">Do plano à ativação em poucos passos.</h2>
        </div>
    </div>
    <div class="vps-steps-grid">
        <article><span>01</span><h3>Escolha o plano</h3><p>Compare Start, Plus e Pro e selecione o que combina com a demanda do projeto.</p></article>
        <article><span>02</span><h3>Fale com a GeekHost</h3><p>Confirme configuração, prazo e detalhes de ativação pelo WhatsApp.</p></article>
        <article><span>03</span><h3>Servidor no ar</h3><p>Receba o acesso e comece a publicar com NVMe, LiteSpeed e SSL.</p></article>
    </div>
</section>

<section class="faq-section" aria-labelledby="vps-faq-title">
    <div class="page-width faq-layout">
        <div class="faq-heading">
            <p class="eyebrow">Perguntas frequentes</p>
            <h2 id="vps-faq-title">Dúvidas comuns<br>sobre VPS.</h2>
            <a class="button button-outline button-support" href="<?= e($whatsappBase . rawurlencode('Tenho dúvidas sobre VPS GeekHost')) ?>"><span>Fale com a equipe</span><span class="support-favicon" aria-hidden="true"><img src="assets/images/favicon.png" alt="" width="24" height="24"></span></a>
        </div>
        <div class="faq-list">
            <?php foreach ($vpsFaqs as $faq): ?>
                <details>
                    <summary><?= e($faq['q']) ?></summary>
                    <p><?= e($faq['a']) ?></p>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="closing-cta page-width">
    <p class="eyebrow">Pronto para escalar?</p>
    <div class="closing-row">
        <h2>Contrate seu servidor<br>VPS GeekHost agora.</h2>
        <div class="vps-home-actions">
            <a class="button button-dark" href="#planos-vps">Ver planos VPS <span aria-hidden="true">↗</span></a>
            <a class="button button-outline-dark" href="<?= e($whatsappBase . rawurlencode('Quero contratar um servidor VPS GeekHost')) ?>">WhatsApp</a>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
