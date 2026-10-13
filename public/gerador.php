<?php

$pageTitle = 'Gerador de link e QR code para WhatsApp';
$pageDescription = 'Crie um link direto para o WhatsApp e gere um QR code para divulgar seu atendimento.';
$currentPage = '';
$loadQrCode = true;
require __DIR__ . '/includes/header.php';
?>

<section class="page-hero page-width generator-hero">
    <p class="eyebrow">Ferramenta gratuita</p>
    <h1>Um toque. Uma conversa.</h1>
    <p class="page-hero-copy">Monte um link direto para o WhatsApp e crie um QR code para compartilhar onde seu público estiver.</p>
</section>

<section class="generator-layout page-width">
    <form class="generator-form" data-generator-form>
        <div class="form-heading"><span class="form-step">01</span><div><h2>Configure seu link</h2><p>Os dados são usados no seu navegador para criar o link.</p></div></div>
        <label for="country-code">Código do país</label>
        <div class="phone-input-row">
            <div class="country-code-wrap"><span aria-hidden="true">+</span><input id="country-code" name="country-code" inputmode="numeric" value="55" maxlength="4" aria-label="Código do país" required></div>
            <div class="phone-number-wrap"><input id="phone-number" name="phone" type="tel" autocomplete="tel-national" inputmode="tel" placeholder="DDD e número" maxlength="16" required><span>WhatsApp com DDD</span></div>
        </div>
        <label for="whatsapp-message">Mensagem inicial <span class="optional-label">opcional</span></label>
        <textarea id="whatsapp-message" name="message" rows="4" maxlength="500" placeholder="Olá! Quero saber mais sobre seus serviços."></textarea>
        <button class="button button-dark button-wide" type="submit">Gerar link e QR code <span aria-hidden="true">↗</span></button>
        <p class="privacy-note"><span aria-hidden="true">◉</span> O número e a mensagem não são enviados para um servidor por esta página.</p>
        <p class="form-status" data-generator-status aria-live="polite"></p>
    </form>
    <div class="generator-result" aria-live="polite">
        <div class="result-head"><div><span class="plan-number">02 / SEU RESULTADO</span><h2>Pronto para compartilhar?</h2></div><span class="result-spark" aria-hidden="true">✳</span></div>
        <div class="qr-stage">
            <div class="qr-placeholder" data-qr-placeholder><span class="qr-placeholder-mark">gh</span><span>Seu QR code<br>aparece aqui</span></div>
            <div class="qr-code" data-qr-code hidden></div>
        </div>
        <p class="result-hint" data-result-hint>Preencha os campos ao lado para montar seu link.</p>
        <div class="generated-link-wrap" data-generated-link-wrap hidden>
            <label for="generated-link">Seu link de WhatsApp</label>
            <div class="generated-link-row"><input id="generated-link" data-generated-link type="text" readonly aria-label="Link gerado do WhatsApp"><button class="copy-button" type="button" data-copy-link aria-label="Copiar link">Copiar</button></div>
            <div class="result-actions"><a class="button button-lime" data-open-whatsapp href="#" target="_blank" rel="noopener noreferrer">Abrir WhatsApp <span aria-hidden="true">↗</span></a><button class="button button-outline-dark" data-download-qr type="button">Baixar QR code</button></div>
        </div>
        <p class="qr-error" data-qr-error hidden>Não foi possível carregar o gerador de QR code. Verifique sua conexão e tente novamente.</p>
    </div>
</section>

<dialog class="hosting-dialog" data-hosting-dialog aria-labelledby="hosting-dialog-title">
    <div class="hosting-dialog-inner">
        <button class="hosting-dialog-close" type="button" data-dialog-close aria-label="Fechar oferta" title="Fechar oferta"><span aria-hidden="true">×</span></button>
        <div class="hosting-dialog-copy">
            <p class="eyebrow">Seu próximo passo</p>
            <h2 id="hosting-dialog-title">Sua marca também merece um site próprio.</h2>
            <p>Seu link e QR code do WhatsApp são gratuitos e não têm prazo de validade configurado. Para apresentar sua marca, seus serviços e seus projetos em um endereço próprio, conheça a hospedagem GeekHost.</p>
            <ul class="hosting-benefits">
                <li>Hospedagem otimizada em SSD</li>
                <li>Teste grátis por 7 dias*</li>
                <li>Portabilidade gratuita*</li>
                <li>Suporte humano pelo WhatsApp</li>
            </ul>
            <div class="hosting-dialog-actions">
                <a class="button button-dark" href="planos.php">Conhecer os planos <span aria-hidden="true">↗</span></a>
                <button class="dialog-continue" type="button" data-dialog-continue>Não, gerar meu link grátis</button>
            </div>
            <p class="hosting-dialog-note">*Consulte as condições do teste e da portabilidade.</p>
        </div>
        <div class="hosting-dialog-visual">
            <img src="assets/images/geeko-wordpress.png" alt="Mascote GeekHost ao lado de uma página WordPress" width="524" height="437">
            <span>Do primeiro contato ao seu site.</span>
        </div>
    </div>
</dialog>

<section class="generator-footnote page-width"><p><strong>Antes de divulgar:</strong> teste o link no celular e confirme se o código do país, o número e a mensagem estão corretos. O WhatsApp precisa estar ativo nesse número.</p></section>

<?php require __DIR__ . '/includes/footer.php'; ?>