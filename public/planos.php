<?php

$pageTitle = 'Planos de hospedagem';
$pageDescription = 'Compare os planos Light, Empresa e Premium da GeekHost, com valores mensais, semestrais e anuais apresentados com o total de cada ciclo.';
$currentPage = 'planos';
require __DIR__ . '/includes/header.php';
?>

<section class="page-hero page-width">
    <p class="eyebrow">Planos e renovações GeekHost</p>
    <h1>Hospedagem para<br>quem faz a web.</h1>
    <p class="page-hero-copy">Confira o preço mensal e, nos ciclos semestral e anual, veja também o total correspondente ao período. Os valores abaixo são de renovação.</p>
</section>

<section class="detail-plans page-width" aria-label="Perfis de hospedagem">
    <article class="detail-plan" id="light">
        <div class="detail-plan-top"><span class="plan-number">01 / PRESENÇA</span><span class="detail-index">A</span></div>
        <div class="detail-plan-body"><div><h2>Plano Light</h2><p class="detail-audience">Para colocar seu projeto no ar.</p></div><p class="detail-description">Uma opção para sites pessoais, portfólios e páginas de apresentação de profissionais e pequenos negócios.</p></div>
        <dl class="price-options" aria-label="Preços de renovação do Plano Light">
            <div><dt>Mensal</dt><dd><strong>R$ 42,90</strong><span>por mês</span><small>Total mensal: R$ 42,90</small></dd></div>
            <div><dt>Semestral</dt><dd><strong>R$ 39,90</strong><span>por mês</span><small>Total do ciclo de 6 meses: R$ 239,40</small></dd></div>
            <div><dt>Anual</dt><dd><strong>R$ 32,90</strong><span>por mês</span><small>Total do ciclo de 12 meses: R$ 394,80</small></dd></div>
        </dl>
        <div class="detail-plan-bottom"><p><strong>Valores de renovação.</strong> Consulte os recursos incluídos, condições do teste de 7 dias e portabilidade.</p><a class="button button-dark" href="contato.php?assunto=light">Falar sobre o Light <span aria-hidden="true">↗</span></a></div>
    </article>
    <article class="detail-plan detail-plan-featured" id="empresa">
        <div class="detail-plan-top"><span class="plan-number">02 / CRESCIMENTO</span><span class="detail-index">B</span></div>
        <div class="detail-plan-body"><div><h2>Plano Empresa</h2><p class="detail-audience">Para crescer com consistência.</p></div><p class="detail-description">Uma opção para negócios que querem consolidar sua presença digital e organizar a próxima etapa da operação.</p></div>
        <dl class="price-options" aria-label="Preços de renovação do Plano Empresa">
            <div><dt>Mensal</dt><dd><strong>R$ 55,00</strong><span>por mês</span><small>Total mensal: R$ 55,00</small></dd></div>
            <div><dt>Semestral</dt><dd><strong>R$ 49,90</strong><span>por mês</span><small>Total do ciclo de 6 meses: R$ 299,40</small></dd></div>
            <div><dt>Anual</dt><dd><strong>R$ 39,90</strong><span>por mês</span><small>Total do ciclo de 12 meses: R$ 478,80</small></dd></div>
        </dl>
        <div class="detail-plan-bottom"><p><strong>Valores de renovação.</strong> Consulte os recursos incluídos, condições do teste de 7 dias e portabilidade.</p><a class="button button-light" href="contato.php?assunto=empresa">Falar sobre o Empresa <span aria-hidden="true">↗</span></a></div>
    </article>
    <article class="detail-plan" id="premium">
        <div class="detail-plan-top"><span class="plan-number">03 / COMÉRCIO</span><span class="detail-index">C</span></div>
        <div class="detail-plan-body"><div><h2>Plano Premium</h2><p class="detail-audience">Para projetos em expansão.</p></div><p class="detail-description">Uma opção para projetos que precisam avaliar uma estrutura de hospedagem mais completa para sua operação.</p></div>
        <dl class="price-options" aria-label="Preços de renovação do Plano Premium">
            <div><dt>Mensal</dt><dd><strong>R$ 62,00</strong><span>por mês</span><small>Total mensal: R$ 62,00</small></dd></div>
            <div><dt>Semestral</dt><dd><strong>R$ 59,90</strong><span>por mês</span><small>Total do ciclo de 6 meses: R$ 359,40</small></dd></div>
            <div><dt>Anual</dt><dd><strong>R$ 49,90</strong><span>por mês</span><small>Total do ciclo de 12 meses: R$ 598,80</small></dd></div>
        </dl>
        <div class="detail-plan-bottom"><p><strong>Valores de renovação.</strong> Consulte os recursos incluídos, condições do teste de 7 dias e portabilidade.</p><a class="button button-dark" href="contato.php?assunto=premium">Falar sobre o Premium <span aria-hidden="true">↗</span></a></div>
    </article>
</section>

<section class="plan-notes page-width">
    <span class="note-mark" aria-hidden="true">i</span>
    <div><h2>Sobre os preços</h2><p>Os valores exibidos são os preços de renovação informados. Nos ciclos semestral e anual, o preço mensal é equivalente: o total indicado corresponde ao ciclo completo. Consulte o valor de contratação inicial, recursos de cada plano, regras do teste grátis e condições de portabilidade antes de contratar.</p></div>
</section>

<section class="simple-cta page-width"><p>Quer ajuda para escolher seu plano?</p><a class="text-link" href="contato.php">Fale com a equipe <span aria-hidden="true">→</span></a></section>

<?php require __DIR__ . '/includes/footer.php'; ?>