</main>
<?php if (empty($hideSupportCenter)): ?>
<section class="support-center" aria-labelledby="support-center-title">
    <div class="support-center-inner page-width">
        <div class="support-center-copy">
            <p class="eyebrow eyebrow-light"><span></span> Central de Atendimento</p>
            <h2 id="support-center-title">Suporte humano,<br>sempre perto de você.</h2>
            <p>Fale com quem entende de hospedagem. Tire dúvidas, acompanhe migrações e resolva o dia a dia do seu projeto com atendimento direto e sem enrolação.</p>
            <div class="support-center-actions">
                <?php if ($companyWhatsapp !== ''): ?>
                    <a class="button button-lime" href="https://wa.me/<?= e($companyWhatsapp) ?>" target="_blank" rel="noopener noreferrer">WhatsApp <span aria-hidden="true">↗</span></a>
                    <a class="button button-outline button-support-light" href="https://wa.me/<?= e($companyWhatsapp) ?>" target="_blank" rel="noopener noreferrer"><span>Chat com o suporte</span><span class="support-favicon" aria-hidden="true"><img src="assets/images/favicon.png" alt="" width="24" height="24"></span></a>
                <?php endif; ?>
                <?php if ($companyEmail !== ''): ?>
                    <a class="text-link text-link-light" href="mailto:<?= e($companyEmail) ?>"><?= e($companyEmail) ?> <span aria-hidden="true">↗</span></a>
                <?php endif; ?>
            </div>
            <?php if ($companyWhatsapp !== ''): ?>
                <?php
                $whatsappDigits = $companyWhatsapp;
                $whatsappDisplay = strlen($whatsappDigits) >= 12
                    ? sprintf('+%s (%s) %s-%s', substr($whatsappDigits, 0, 2), substr($whatsappDigits, 2, 2), substr($whatsappDigits, 4, 5), substr($whatsappDigits, 9))
                    : '+' . $whatsappDigits;
                ?>
                <p class="support-center-phone">Telefone / WhatsApp: <a href="https://wa.me/<?= e($companyWhatsapp) ?>" target="_blank" rel="noopener noreferrer"><?= e($whatsappDisplay) ?></a></p>
            <?php endif; ?>
        </div>
        <figure class="support-center-visual support-center-visual-mascots">
            <img src="assets/images/mascotes-suporte.png" alt="Mascotes GeekHost colaborando no computador para atender o suporte" width="1024" height="1536" loading="eager" decoding="async">
        </figure>
    </div>
</section>
<?php endif; ?>
<footer class="site-footer">
    <div class="footer-inner">
        <a class="brand brand-footer" href="index.php" aria-label="GeekHost, página inicial">
            <img class="footer-emblem" src="assets/images/LOGO-02.png" alt="" width="200" height="120">
        </a>
        <p>Seu projeto merece um bom começo.</p>
        <nav class="footer-links" aria-label="Links do rodapé">
            <a href="planos.php">Planos</a>
            <a href="vantagens.php">Vantagens</a>
            <a href="quem-somos.php">Quem Somos</a>
            <a href="contato.php">Contato</a>
            <a href="gerador.php">Gerador de WhatsApp</a>
        </nav>
        <small>&copy; <?= date('Y') ?> FBS Tecnologia - CNPJ 61.171.635/0001-46. Rua Pais Leme, 215, Conj. 1713, Pinheiros, São Paulo/SP, CEP 05424-150. Todos os direitos reservados.</small>
        <nav class="footer-social" aria-label="Redes sociais GeekHost">
            <a href="https://www.instagram.com/geekhost" target="_blank" rel="noopener noreferrer" aria-label="Instagram GeekHost" title="Instagram GeekHost">
                <img src="https://cdn.simpleicons.org/instagram/ffffff" alt="" width="16" height="16">
            </a>
            <a href="https://br.linkedin.com/company/geekhost" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn GeekHost" title="LinkedIn GeekHost">
                <img src="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/icons/linkedin.svg"
                alt="LinkedIn"
                width="16"
                height="16"
                style="filter: brightness(0) invert(1);">  
            </a>
            <?php if ($companyWhatsapp !== ''): ?>
                <a href="https://wa.me/<?= e($companyWhatsapp) ?>" target="_blank" rel="noopener noreferrer" aria-label="WhatsApp GeekHost" title="WhatsApp GeekHost">
                    <img src="https://cdn.simpleicons.org/whatsapp/ffffff" alt="" width="16" height="16">
                </a>
            <?php endif; ?>
        </nav>
    </div>
</footer>
<?php if ($companyWhatsapp !== ''): ?>
    <a class="whatsapp-float" href="https://wa.me/<?= e($companyWhatsapp) ?>" target="_blank" rel="noopener noreferrer" aria-label="Fale com a GeekHost pelo WhatsApp" title="Fale com a GeekHost pelo WhatsApp">
        <img src="https://cdn.simpleicons.org/whatsapp/ffffff" alt="" width="30" height="30">
    </a>
<?php endif; ?>
<button class="scroll-top" type="button" data-scroll-top aria-label="Voltar ao topo" title="Voltar ao topo" hidden>
    <span aria-hidden="true">↑</span>
</button>
<dialog class="exit-intent" data-exit-intent aria-labelledby="exit-intent-title">
    <div class="exit-intent-panel">
        <aside class="exit-intent-visual">
            <img src="assets/images/mascotes-suporte.png" alt="" width="420" height="630" loading="lazy" decoding="async">
            <span class="exit-intent-badge">Infraestrutura sob medida</span>
        </aside>
        <div class="exit-intent-content">
            <button class="exit-intent-close" type="button" data-exit-intent-close aria-label="Fechar">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
            <p class="exit-intent-status"><span class="exit-intent-status-icon" aria-hidden="true"><i class="bi bi-lightning-charge-fill"></i><i class="exit-intent-online-dot"></i></span> Especialistas online</p>
            <h2 id="exit-intent-title">Como podemos acelerar seu projeto hoje?</h2>
            <p class="exit-intent-copy">Nossa equipe está pronta para indicar a hospedagem ideal para o seu site, sem complicação.</p>
            <div class="exit-intent-channels">
                <?php if ($companyWhatsapp !== ''): ?>
                    <a class="exit-intent-card" href="https://wa.me/<?= e($companyWhatsapp) ?>" target="_blank" rel="noopener noreferrer">
                        <span class="exit-intent-card-icon exit-intent-card-icon-whatsapp" aria-hidden="true"><i class="bi bi-whatsapp"></i></span>
                        <span class="exit-intent-card-copy"><strong>WhatsApp</strong><small>Fale agora com o suporte GeekHost</small></span>
                        <span class="exit-intent-pill exit-intent-pill-online">Online</span>
                    </a>
                <?php endif; ?>
                <a class="exit-intent-card" href="https://www.instagram.com/geekhost" target="_blank" rel="noopener noreferrer">
                    <span class="exit-intent-card-icon exit-intent-card-icon-instagram" aria-hidden="true"><i class="bi bi-instagram"></i></span>
                    <span class="exit-intent-card-copy"><strong>Instagram</strong><small>Novidades e bastidores da equipe</small></span>
                    <span class="exit-intent-pill">Seguir</span>
                </a>
                <a class="exit-intent-card" href="https://br.linkedin.com/company/geekhost" target="_blank" rel="noopener noreferrer">
                    <span class="exit-intent-card-icon exit-intent-card-icon-linkedin" aria-hidden="true"><i class="bi bi-linkedin"></i></span>
                    <span class="exit-intent-card-copy"><strong>LinkedIn</strong><small>Conteúdo e oportunidades GeekHost</small></span>
                    <span class="exit-intent-pill">Conectar</span>
                </a>
            </div>
            <p class="exit-intent-meta">Resposta média: <strong>3 min</strong> · Equipe online: <strong>24 horas</strong></p>
        </div>
    </div>
</dialog>
<div class="cookie-banner" data-cookie-banner hidden>
    <div class="cookie-banner-inner page-width">
        <div class="cookie-banner-copy">
            <strong>Privacidade e cookies</strong>
            <p>Usamos cookies essenciais para o funcionamento do site e, com o seu consentimento, cookies de preferências para lembrar suas escolhas (LGPD). Você pode aceitar, recusar os não essenciais ou ajustar as preferências.</p>
        </div>
        <div class="cookie-banner-actions">
            <button class="button button-lime" type="button" data-cookie-accept>Aceitar</button>
            <button class="button button-outline-dark cookie-btn-reject" type="button" data-cookie-reject>Recusar</button>
            <button class="text-link" type="button" data-cookie-preferences>Preferências</button>
        </div>
        <div class="cookie-preferences" data-cookie-panel hidden>
            <label class="cookie-option">
                <input type="checkbox" checked disabled>
                <span><strong>Essenciais</strong> Necessários para navegação e segurança. Sempre ativos.</span>
            </label>
            <label class="cookie-option">
                <input type="checkbox" data-cookie-prefs checked>
                <span><strong>Preferências</strong> Lembram escolhas como idioma e layout para melhorar sua experiência.</span>
            </label>
            <button class="button button-dark" type="button" data-cookie-save>Salvar preferências</button>
        </div>
    </div>
</div>
<?php if (!empty($loadQrCode)): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" defer></script>
<?php endif; ?>
<script src="assets/js/site.js" defer></script>
</body>
</html>
