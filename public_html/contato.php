<?php

$pageTitle = 'Entre em contato';
$pageDescription = 'Entre em contato com a GeekHost para tirar dúvidas sobre hospedagem e encontrar uma opção para seu projeto.';
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
    <p class="eyebrow">Contato</p>
    <h1>Conta pra gente<br>o que você imagina.</h1>
    <p class="page-hero-copy">Dúvidas sobre hospedagem ou sobre o próximo passo do seu site? Envie uma mensagem para a equipe.</p>
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
                <div><span class="contact-label">WhatsApp</span><a class="button button-outline button-support" href="whatsapp.php" target="_blank" rel="noopener noreferrer"><span>Iniciar conversa</span><span class="support-favicon" aria-hidden="true"><img src="assets/images/favicon.png" alt="" width="24" height="24"></span></a></div>
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

<?php require __DIR__ . '/includes/footer.php'; ?>