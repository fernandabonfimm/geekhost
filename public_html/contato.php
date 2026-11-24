<?php

$pageTitle = 'Suporte';
$pageDescription = 'Fale com a GeekHost e consulte a base de conhecimento sobre painel, pagamento, domínio, site e DirectAdmin.';
$pageDocumentTitle = 'Suporte | GeekHost';
$currentPage = 'contato';
require __DIR__ . '/includes/header.php';

$subject = trim((string) ($_GET['assunto'] ?? ''));
$allowedSubjects = ['light', 'empresa', 'premium'];
if (!in_array($subject, $allowedSubjects, true)) {
    $subject = '';
}
$subjectLabels = [
    'light' => 'Plano Light',
    'empresa' => 'Plano Empresa',
    'premium' => 'Plano Premium',
];
?>

<section class="page-hero page-width contact-hero">
    <p class="eyebrow">Suporte</p>
    <h1>Conta pra gente<br>o que você imagina.</h1>
    <p class="page-hero-copy">Dúvidas sobre hospedagem de sites ou sobre o próximo passo do seu site? Envie uma mensagem para a equipe.</p>
</section>

<section class="contact-layout page-width">
    <div class="contact-aside">
        <p class="eyebrow">Vamos conversar</p>
        <h2>Uma boa conversa<br>começa com contexto.</h2>
        <p>Conte um pouco sobre o seu projeto e o que você precisa. Assim, a equipe pode orientar você com mais clareza.</p>
        <div class="contact-detail">
            <span class="contact-symbol" aria-hidden="true">@</span>
            <div><span class="contact-label">E-mail</span>
                <?php if ($companyEmail !== ''): ?>
                    <a href="mailto:<?= e($companyEmail) ?>"><?= e($companyEmail) ?></a>
                <?php else: ?>
                    <span>Canal a configurar</span>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($companyWhatsapp !== ''): ?>
            <div class="contact-detail">
                <span class="contact-symbol" aria-hidden="true">↗</span>
                <div><span class="contact-label">WhatsApp</span><a href="https://wa.me/<?= e($companyWhatsapp) ?>" target="_blank" rel="noopener noreferrer">+<?= e($companyWhatsapp) ?></a></div>
            </div>
        <?php endif; ?>
    </div>
    <div class="contact-form-wrap">
        <?php if ($companyEmail === ''): ?>
            <div class="setup-notice" role="status"><strong>Canal de atendimento pendente.</strong><p>Para receber mensagens, configure o endereço comercial com a variável de ambiente <code>GEEKHOST_CONTACT_EMAIL</code>. Nenhuma mensagem será enviada enquanto esse canal não estiver configurado.</p></div>
        <?php endif; ?>
        <form class="contact-form" data-contact-form data-contact-email="<?= e($companyEmail) ?>">
            <label for="contact-name">Seu nome</label>
            <input id="contact-name" name="name" autocomplete="name" required maxlength="100" placeholder="Como podemos chamar você?" <?= $companyEmail === '' ? 'disabled' : '' ?>>
            <label for="contact-email">Seu e-mail</label>
            <input id="contact-email" name="email" type="email" autocomplete="email" required maxlength="160" placeholder="voce@exemplo.com" <?= $companyEmail === '' ? 'disabled' : '' ?>>
            <label for="contact-subject">Assunto</label>
            <select id="contact-subject" name="subject" required <?= $companyEmail === '' ? 'disabled' : '' ?>>
                <option value="" <?= $subject === '' ? 'selected' : '' ?> disabled>Selecione um assunto</option>
                <option value="Plano Light" <?= $subject === 'light' ? 'selected' : '' ?>>Plano Light</option>
                <option value="Plano Empresa" <?= $subject === 'empresa' ? 'selected' : '' ?>>Plano Empresa</option>
                <option value="Plano Premium" <?= $subject === 'premium' ? 'selected' : '' ?>>Plano Premium</option>
                <option value="Outro assunto">Outro assunto</option>
            </select>
            <label for="contact-message">Sua mensagem</label>
            <textarea id="contact-message" name="message" rows="5" required maxlength="3000" placeholder="Escreva aqui o que você gostaria de saber." <?= $companyEmail === '' ? 'disabled' : '' ?>></textarea>
            <button class="button button-dark" type="submit" <?= $companyEmail === '' ? 'disabled' : '' ?>>Enviar e-mail <span aria-hidden="true">↗</span></button>
            <p class="form-status" data-contact-status aria-live="polite"></p>
            <p class="fine-print">O envio abrirá o aplicativo de e-mail configurado no seu dispositivo.</p>
        </form>
    </div>
</section>

<section class="kb-cta page-width" id="base-de-conhecimento">
    <div class="kb-cta-card">
        <div class="kb-cta-copy">
            <p class="eyebrow">Base de conhecimento</p>
            <h2>Travou em algum passo? A resposta já está escrita.</h2>
            <p>Guias diretos para pagar fatura, cadastrar domínio, publicar o site, entrar no DirectAdmin e criar o banco de dados.</p>
            <a class="button button-lime" href="<?= e(url('/base-de-conhecimento')) ?>">Abrir a base de conhecimento <span aria-hidden="true">↗</span></a>
        </div>
        <img class="kb-cta-art" src="<?= e(asset('assets/images/mascotes-suporte.png')) ?>" alt="Mascotes GeekHost mostrando um passo no notebook" width="420" height="520">
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>