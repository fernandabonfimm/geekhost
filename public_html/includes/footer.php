</main>
<footer class="site-footer">
    <div class="footer-inner">
        <a class="brand brand-footer" href="index.php" aria-label="GeekHost, página inicial">
            <img class="footer-emblem" src="assets/images/emblema.png" alt="" width="60" height="60">
            <span class="brand-name">geekhost</span>
        </a>
        <p>Seu projeto merece um bom começo.</p>
        <nav class="footer-links" aria-label="Links do rodapé">
            <a href="planos.php">Planos</a>
            <a href="contato.php">Contato</a>
            <a href="gerador.php">Gerador de WhatsApp</a>
        </nav>
        <small>&copy; <?= date('Y') ?> FBS Tecnologia - CNPJ 61.171.635/0001-46. Rua Pais Leme, 215, Conj. 1713, Pinheiros, São Paulo/SP, CEP 05424-150. Todos os direitos reservados.</small>
        <nav class="footer-social" aria-label="Redes sociais GeekHost">
            <a href="https://www.instagram.com/geekhost" target="_blank" rel="noopener noreferrer" aria-label="Instagram GeekHost" title="Instagram GeekHost">
                <img src="https://cdn.simpleicons.org/instagram/ffffff" alt="" width="16" height="16">
            </a>
            <a href="https://br.linkedin.com/company/geekhost" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn GeekHost" title="LinkedIn GeekHost">
                <img src="https://cdn.simpleicons.org/linkedin/ffffff" alt="" width="16" height="16">
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
<?php if (!empty($loadQrCode)): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" defer></script>
<?php endif; ?>
<script src="assets/js/site.js" defer></script>
</body>
</html>