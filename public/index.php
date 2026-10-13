<?php

$pageTitle = 'Hospedagem para o seu próximo projeto';
$pageDescription = 'Conheça as opções de hospedagem GeekHost e encontre um ponto de partida para seu site, negócio ou loja online.';
$currentPage = 'inicio';
require __DIR__ . '/includes/header.php';
?>

<section class="home-hero">
    <div class="hero-shade"></div>
    <div class="hero-content page-width">
        <p class="eyebrow eyebrow-light"><span></span> Hospedagem para programadores, agências e empresas</p>
        <h1>Seu projeto no ar.<br><em>Seu próximo passo começa aqui.</em></h1>
        <p class="hero-copy">Hospedagem otimizada em SSD, com foco em velocidade e segurança, links redundantes e suporte humano pelo WhatsApp. Teste grátis por 7 dias e conte com portabilidade gratuita.</p>
        <div class="hero-actions">
            <a class="button button-lime" href="planos.php">Veja os preços <span aria-hidden="true">↗</span></a>
            <a class="text-link text-link-light" href="contato.php">Fale com o suporte <span aria-hidden="true">→</span></a>
        </div>
        <p class="hero-footnote">Teste e portabilidade sujeitos às condições comerciais. Valores abaixo são de renovação.</p>
    </div>
    <div class="hero-index" aria-hidden="true"><span>01</span><span class="hero-index-line"></span><span>03</span></div>
</section>

<section class="intro-strip page-width" aria-label="GeekHost em poucas palavras">
    <p>ESTRUTURA PARA SEU PRÓXIMO PASSO</p>
    <p>Equipamentos de última geração em datacenters com links redundantes e mecanismos de segurança física e lógica.</p>
    <span class="intro-star" aria-hidden="true">✳</span>
</section>

<section class="section section-plans page-width">
    <div class="section-heading">
        <div>
            <p class="eyebrow">Planos e renovações</p>
            <h2>Escolha seu plano<br>com clareza.</h2>
        </div>
        <p class="section-lede">Compare os ciclos. O valor mensal equivalente do plano semestral ou anual aparece junto do total cobrado pelo período.</p>
    </div>
    <div class="plan-grid">
        <article class="plan-card plan-card-light" id="light">
            <span class="plan-number">01 / PRESENÇA</span>
            <h3>Plano Light</h3>
            <p>Para colocar seu site e suas ideias no ar.</p>
            <p class="plan-price"><strong>R$ 32,90</strong><span>/mês no anual</span></p>
            <p class="plan-billing">Total do ciclo de 12 meses: R$ 394,80<br>Renovação mensal: R$ 42,90/mês</p>
            <a class="arrow-link" href="planos.php#light">Ver ciclos e detalhes <span aria-hidden="true">↗</span></a>
        </article>
        <article class="plan-card plan-card-green" id="empresa">
            <span class="plan-number">02 / CRESCIMENTO</span>
            <h3>Plano Empresa</h3>
            <p>Para negócios e equipes que querem crescer online.</p>
            <p class="plan-price"><strong>R$ 39,90</strong><span>/mês no anual</span></p>
            <p class="plan-billing">Total do ciclo de 12 meses: R$ 478,80<br>Renovação mensal: R$ 55,00/mês</p>
            <a class="arrow-link" href="planos.php#empresa">Ver ciclos e detalhes <span aria-hidden="true">↗</span></a>
        </article>
        <article class="plan-card plan-card-lime" id="premium">
            <span class="plan-number">03 / COMÉRCIO</span>
            <h3>Plano Premium</h3>
            <p>Para projetos que precisam de uma estrutura mais completa.</p>
            <p class="plan-price"><strong>R$ 49,90</strong><span>/mês no anual</span></p>
            <p class="plan-billing">Total do ciclo de 12 meses: R$ 598,80<br>Renovação mensal: R$ 62,00/mês</p>
            <a class="arrow-link" href="planos.php#premium">Ver ciclos e detalhes <span aria-hidden="true">↗</span></a>
        </article>
    </div>
    <p class="fine-print">Valores de renovação informados. No ciclo semestral ou anual, o total do período é cobrado conforme as condições da contratação. Consulte eventuais ofertas e regras do teste grátis.</p>
</section>

<section class="feature-band">
    <div class="feature-inner page-width">
        <div class="feature-copy">
            <p class="eyebrow eyebrow-light">Mais conversa, menos barreira</p>
            <h2>Transforme uma visita<br>em uma conversa.</h2>
            <p>Crie um link direto para o WhatsApp e um QR code para compartilhar em anúncios, materiais impressos ou no seu próprio site.</p>
            <a class="button button-outline" href="gerador.php">Abrir gerador <span aria-hidden="true">↗</span></a>
        </div>
        <div class="feature-art" aria-hidden="true">
            <div class="art-orbit art-orbit-one"></div>
            <div class="art-orbit art-orbit-two"></div>
            <div class="art-phone"><div class="art-phone-camera"></div><div class="art-phone-screen"><span class="art-chat-dot"></span><span class="art-chat-line"></span><span class="art-chat-line short"></span><span class="art-send">↗</span></div></div>
            <div class="art-qr"><span></span><span></span><span></span><span></span><i></i></div>
            <span class="art-plus art-plus-one">+</span><span class="art-plus art-plus-two">+</span>
        </div>
    </div>
</section>

<section class="closing-cta page-width">
    <p class="eyebrow">Vamos tirar do papel?</p>
    <div class="closing-row"><h2>O próximo passo<br>começa com uma escolha.</h2><a class="button button-dark" href="contato.php">Fale com a gente <span aria-hidden="true">↗</span></a></div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>